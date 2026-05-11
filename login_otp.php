<?php
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/',
    'secure' => false, 'httponly' => true, 'samesite' => 'Strict'
]);
session_start();
require_once 'config.php';
require_once 'mailer.php';

// Must come from login flow
if (empty($_SESSION['login_pending_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['login_pending_id'];
$error   = '';

// ── Resend login OTP ──────────────────────────────────────────────────────────
if (isset($_GET['resend'])) {
    $new_otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $new_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $pdo->prepare("UPDATE users SET email_otp = ?, otp_expires_at = ? WHERE id = ?")
        ->execute([$new_otp, $new_expires, $user_id]);

    $u = $pdo->prepare("SELECT email, full_name, username FROM users WHERE id = ?");
    $u->execute([$user_id]);
    $udata = $u->fetch();
    sendLoginOtpEmail($udata['email'], $udata['full_name'] ?: $udata['username'], $new_otp);

    $resent = true;
}

// ── Verify login OTP ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $entered = trim($_POST['otp']);

    $stmt = $pdo->prepare("SELECT email_otp, otp_expires_at, role, username, status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || empty($user['email_otp'])) {
        $error = "No verification code found. Please log in again.";
    } elseif (strtotime($user['otp_expires_at']) < time()) {
        $error = "Code expired. Click 'Resend Code' to get a new one.";
    } elseif ($entered !== $user['email_otp']) {
        $error = "Incorrect code. Please try again.";
    } else {
        // ✅ OTP correct — clear it and create full session
        $pdo->prepare("UPDATE users SET email_otp = NULL, otp_expires_at = NULL WHERE id = ?")
            ->execute([$user_id]);

        unset($_SESSION['login_pending_id']);
        session_regenerate_id(true);

        $_SESSION['user_id']  = $user_id;
        $_SESSION['role']     = $user['role'];
        $_SESSION['username'] = $user['username'];

        header("Location: " . match($user['role']) {
            'superadmin' => 'superadmin_dashboard.php',
            'admin'      => 'admin_dashboard.php',
            default      => 'user_dashboard.php'
        });
        exit;
    }
}

// Get masked email for display
$emailRow = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$emailRow->execute([$user_id]);
$emailData = $emailRow->fetch();
$maskedEmail = '';
if ($emailData) {
    [$local, $domain] = explode('@', $emailData['email']);
    $maskedEmail = substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 3)) . '@' . $domain;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BorrowTrack | Login Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --accent: #22c55e; }
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
        .otp-group { display: flex; gap: 10px; justify-content: center; margin: 1.5rem 0; }
        .otp-box {
            width: 52px; height: 60px; text-align: center; font-size: 1.6rem; font-weight: 700;
            background: rgba(15,23,42,0.8); border: 2px solid #334155; color: white;
            border-radius: 10px; transition: 0.2s;
        }
        .otp-box:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 3px rgba(34,197,94,0.2); }
        .btn-verify { background: var(--accent); border: none; font-weight: 700; color: #0f172a; }
        .btn-verify:hover { background: #16a34a; color: white; }
        .timer { font-size: 0.85rem; color: #94a3b8; }
        .timer span { color: var(--accent); font-weight: 700; }
        .email-badge {
            background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3);
            color: #4ade80; border-radius: 8px; padding: 8px 14px;
            font-size: 0.85rem; text-align: center; margin-bottom: 1rem;
        }
    </style>
</head>
<body>
<div class="verify-card">
    <div class="text-center mb-3">
        <div style="font-size:2.5rem;">🔑</div>
        <h4 class="fw-bold mt-2 text-white">Login Verification</h4>
        <p class="text-secondary small">We sent a 6-digit code to your email.</p>
    </div>

    <?php if ($maskedEmail): ?>
        <div class="email-badge">
            <i class="fas fa-envelope me-2"></i><?= htmlspecialchars($maskedEmail) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger bg-dark border-danger text-danger small text-center">
            <i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($resent)): ?>
        <div class="alert alert-success bg-dark border-success text-success small text-center">
            <i class="fas fa-check-circle me-1"></i>New code sent to your email.
        </div>
    <?php endif; ?>

    <form method="POST" id="otpForm">
        <input type="hidden" name="otp" id="otpHidden">

        <div class="otp-group">
            <?php for ($i = 1; $i <= 6; $i++): ?>
                <input type="text" class="otp-box" maxlength="1"
                       inputmode="numeric" pattern="[0-9]"
                       id="otp<?= $i ?>" autocomplete="off">
            <?php endfor; ?>
        </div>

        <p class="timer text-center mb-3">
            Code expires in <span id="countdown">10:00</span>
        </p>

        <button type="submit" class="btn btn-verify w-100 rounded-pill mb-3">
            <i class="fas fa-check-circle me-2"></i>Verify & Sign In
        </button>
    </form>

    <div class="d-flex justify-content-between">
        <a href="?resend=1" class="text-secondary small text-decoration-none">
            <i class="fas fa-redo me-1"></i>Resend Code
        </a>
        <a href="login.php" class="text-secondary small text-decoration-none">
            <i class="fas fa-arrow-left me-1"></i>Back to Login
        </a>
    </div>
</div>

<script>
// Auto-advance OTP boxes
const boxes = document.querySelectorAll('.otp-box');
boxes.forEach((box, idx) => {
    box.addEventListener('input', () => {
        box.value = box.value.replace(/\D/g, '');
        if (box.value && idx < boxes.length - 1) boxes[idx + 1].focus();
    });
    box.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !box.value && idx > 0) boxes[idx - 1].focus();
    });
    box.addEventListener('paste', e => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
        [...pasted].slice(0, 6).forEach((ch, i) => { if (boxes[i]) boxes[i].value = ch; });
        boxes[Math.min(pasted.length, 5)].focus();
    });
});

document.getElementById('otpForm').addEventListener('submit', e => {
    const code = [...boxes].map(b => b.value).join('');
    if (code.length < 6) { e.preventDefault(); alert('Please enter all 6 digits.'); return; }
    document.getElementById('otpHidden').value = code;
});

// Countdown timer
let seconds = 600;
const el = document.getElementById('countdown');
const timer = setInterval(() => {
    seconds--;
    if (seconds <= 0) {
        clearInterval(timer);
        el.textContent = '00:00';
        el.style.color = '#ef4444';
        return;
    }
    const m = String(Math.floor(seconds / 60)).padStart(2, '0');
    const s = String(seconds % 60).padStart(2, '0');
    el.textContent = `${m}:${s}`;
}, 1000);
</script>
</body>
</html>
