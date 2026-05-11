<?php
require_once 'config.php';
require_once 'mailer.php';

$error   = '';
$success = '';

// Must come from registration flow
if (empty($_SESSION['pending_verify_user_id'])) {
    header('Location: register.php');
    exit();
}

$user_id = (int)$_SESSION['pending_verify_user_id'];

// ── Resend OTP ────────────────────────────────────────────────────────────────
if (isset($_GET['resend'])) {
    $new_otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $new_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $stmt = $pdo->prepare("UPDATE users SET email_otp = ?, otp_expires_at = ? WHERE id = ?");
    $stmt->execute([$new_otp, $new_expires, $user_id]);

    // Fetch user info to send email
    $u = $pdo->prepare("SELECT email, full_name, username FROM users WHERE id = ?");
    $u->execute([$user_id]);
    $user = $u->fetch();

    sendOtpEmail($user['email'], $user['full_name'] ?: $user['username'], $new_otp);
    $success = "A new verification code has been sent to your email.";
}

// ── Verify OTP ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $entered_otp = trim($_POST['otp']);

    $stmt = $pdo->prepare("SELECT email_otp, otp_expires_at, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || empty($user['email_otp'])) {
        $error = "No verification code found. Please register again.";
    } elseif (strtotime($user['otp_expires_at']) < time()) {
        $error = "Your code has expired. Click 'Resend Code' to get a new one.";
    } elseif ($entered_otp !== $user['email_otp']) {
        $error = "Incorrect code. Please try again.";
    } else {
        // Mark email as verified, clear OTP
        $pdo->prepare("UPDATE users SET email_verified = 1, email_otp = NULL, otp_expires_at = NULL WHERE id = ?")
            ->execute([$user_id]);

        // Fetch user details for auto-login
        $u = $pdo->prepare("SELECT id, username, role, status FROM users WHERE id = ?");
        $u->execute([$user_id]);
        $verified_user = $u->fetch();

        unset($_SESSION['pending_verify_user_id']);

        // Auto-login: set session
        session_regenerate_id(true);
        $_SESSION['user_id']  = $verified_user['id'];
        $_SESSION['username'] = $verified_user['username'];
        $_SESSION['role']     = $verified_user['role'];

        // If still pending approval, show waiting screen
        // If somehow already approved (e.g. admin pre-approved), go to dashboard
        if ($verified_user['status'] === 'approved') {
            $redirect = match($verified_user['role']) {
                'superadmin' => 'superadmin_dashboard.php',
                'admin'      => 'admin_dashboard.php',
                default      => 'user_dashboard.php'
            };
            header("Location: $redirect");
            exit();
        }

        $success = "verified";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BorrowTrack | Verify Email</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --accent: #38bdf8; }
        body {
            background: radial-gradient(circle at 50% 50%, #1e293b 0%, #020617 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: 'Inter', sans-serif; color: #f1f5f9;
        }
        .verify-card {
            background: rgba(30,41,59,0.75); backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;
            width: 100%; max-width: 420px; padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }
        /* OTP input boxes */
        .otp-group { display: flex; gap: 10px; justify-content: center; margin: 1.5rem 0; }
        .otp-box {
            width: 52px; height: 60px; text-align: center; font-size: 1.6rem; font-weight: 700;
            background: rgba(15,23,42,0.8); border: 2px solid #334155; color: white;
            border-radius: 10px; transition: 0.2s;
        }
        .otp-box:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 3px rgba(56,189,248,0.2); }
        .btn-verify { background: var(--accent); border: none; font-weight: 700; color: #0f172a; }
        .btn-verify:hover { background: #0ea5e9; color: white; }
        .timer { font-size: 0.85rem; color: #94a3b8; }
        .timer span { color: var(--accent); font-weight: 700; }
    </style>
</head>
<body>
<div class="verify-card">

    <?php if ($success === 'verified'): ?>
        <!-- ✅ Success State — pending admin approval -->
        <div class="text-center">
            <div style="font-size:3rem;">✅</div>
            <h4 class="fw-bold mt-3 text-white">Email Verified!</h4>
            <p class="text-secondary small mb-4">
                Your email is confirmed. Your account is currently
                <span style="color:#f59e0b;font-weight:700;">pending admin approval.</span><br><br>
                You'll receive an email once your account is approved and you can start borrowing.
            </p>
            <div class="d-flex flex-column gap-2">
                <a href="login.php" class="btn btn-verify w-100 rounded-pill">
                    <i class="fas fa-sign-in-alt me-2"></i> Go to Login
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- 🔐 OTP Entry State -->
        <div class="text-center mb-4">
            <div style="font-size:2.5rem;">📧</div>
            <h4 class="fw-bold mt-2 text-white">Verify Your Email</h4>
            <p class="text-secondary small">
                We sent a 6-digit code to your email.<br>Enter it below to confirm your address.
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger bg-dark border-danger text-danger small text-center">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success bg-dark border-success text-success small text-center">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="otpForm">
            <!-- Hidden input that holds the combined OTP -->
            <input type="hidden" name="otp" id="otpHidden">

            <!-- 6 individual boxes -->
            <div class="otp-group">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <input type="text" class="otp-box" maxlength="1"
                           inputmode="numeric" pattern="[0-9]"
                           id="otp<?= $i ?>" autocomplete="off">
                <?php endfor; ?>
            </div>

            <!-- Countdown timer -->
            <p class="timer text-center mb-3">
                Code expires in <span id="countdown">10:00</span>
            </p>

            <button type="submit" class="btn btn-verify w-100 rounded-pill mb-3">
                <i class="fas fa-check-circle me-2"></i> Verify Code
            </button>
        </form>

        <div class="text-center">
            <a href="?resend=1" class="text-secondary small text-decoration-none">
                <i class="fas fa-redo me-1"></i> Resend Code
            </a>
        </div>
    <?php endif; ?>

</div>

<script>
// ── Auto-advance OTP boxes ────────────────────────────────────────────────────
const boxes = document.querySelectorAll('.otp-box');

boxes.forEach((box, idx) => {
    box.addEventListener('input', () => {
        // Only allow digits
        box.value = box.value.replace(/\D/g, '');
        if (box.value && idx < boxes.length - 1) boxes[idx + 1].focus();
    });
    box.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !box.value && idx > 0) boxes[idx - 1].focus();
    });
    // Handle paste on first box
    box.addEventListener('paste', (e) => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
        [...pasted].slice(0, 6).forEach((ch, i) => {
            if (boxes[i]) boxes[i].value = ch;
        });
        const last = Math.min(pasted.length, 5);
        boxes[last].focus();
    });
});

// Combine boxes into hidden input before submit
document.getElementById('otpForm')?.addEventListener('submit', (e) => {
    const code = [...boxes].map(b => b.value).join('');
    if (code.length < 6) {
        e.preventDefault();
        alert('Please enter all 6 digits.');
        return;
    }
    document.getElementById('otpHidden').value = code;
});

// ── Countdown timer (10 min) ──────────────────────────────────────────────────
let seconds = 600;
const countdownEl = document.getElementById('countdown');
if (countdownEl) {
    const timer = setInterval(() => {
        seconds--;
        if (seconds <= 0) {
            clearInterval(timer);
            countdownEl.textContent = '00:00';
            countdownEl.style.color = '#ef4444';
            return;
        }
        const m = String(Math.floor(seconds / 60)).padStart(2, '0');
        const s = String(seconds % 60).padStart(2, '0');
        countdownEl.textContent = `${m}:${s}`;
    }, 1000);
}
</script>
</body>
</html>
