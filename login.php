<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();
require_once 'config.php';
require_once 'mailer.php';

define('RECAPTCHA_SITE_SECRET', '6LcdB-MsAAAAAC1RQBkuWKpHj0QhzYB6fZprHlFu');

function verifyRecaptcha(string $token): bool
{
    if (empty($token)) return false;
    $response = file_get_contents(
        'https://www.google.com/recaptcha/api/siteverify?secret='
        . RECAPTCHA_SITE_SECRET . '&response=' . urlencode($token)
    );
    $data = json_decode($response, true);
    return !empty($data['success']);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // 1. reCAPTCHA
    if (!verifyRecaptcha($_POST['g-recaptcha-response'] ?? '')) {
        $error = "Please complete the reCAPTCHA verification.";
    } else {
        try {
            // 2. Fetch user by email
            $stmt = $pdo->prepare("SELECT id, password, role, status, email_verified, email, full_name, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // 3. Verify password
            if ($user && password_verify($password, $user['password'])) {

                // 4. Block unverified emails
                if (empty($user['email_verified'])) {
                    $new_otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $new_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    $pdo->prepare("UPDATE users SET email_otp = ?, otp_expires_at = ? WHERE id = ?")
                        ->execute([$new_otp, $new_expires, $user['id']]);
                    sendOtpEmail($user['email'], $user['full_name'] ?: $user['username'], $new_otp);
                    $_SESSION['pending_verify_user_id'] = $user['id'];
                    header('Location: verify_otp.php');
                    exit;
                }

                // 5. Block unapproved accounts
                if ($user['status'] !== 'approved') {
                    $error = "Account pending administrative approval.";
                } else {
                    // 6. Send login OTP to Gmail
                    $login_otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $login_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                    $pdo->prepare("UPDATE users SET email_otp = ?, otp_expires_at = ? WHERE id = ?")
                        ->execute([$login_otp, $login_expires, $user['id']]);

                    sendLoginOtpEmail($user['email'], $user['full_name'] ?: $user['username'], $login_otp);

                    // Store user id in session temporarily — full session set after OTP
                    $_SESSION['login_pending_id'] = $user['id'];

                    header('Location: login_otp.php');
                    exit;
                }
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "System error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BorrowTrack | Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        :root { --accent: #38bdf8; }
        body {
            background: #020617;
            background-image: radial-gradient(circle at 50% 50%, #1e293b 0%, #020617 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            color: #f1f5f9; font-family: 'Inter', system-ui, sans-serif;
        }
        .login-card {
            background: rgba(30,41,59,0.7); backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;
            width: 100%; max-width: 400px; padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }
        .form-control {
            background: rgba(15,23,42,0.8); border: 1px solid #334155;
            color: white; border-radius: 8px;
        }
        .form-control:focus {
            background: rgba(15,23,42,0.9); border-color: var(--accent);
            box-shadow: 0 0 0 0.2rem rgba(56,189,248,0.25); color: white;
        }
        .form-control::placeholder { color: #475569; }
        .btn-primary { background: var(--accent); border: none; font-weight: 700; color: #0f172a; transition: 0.3s; }
        .btn-primary:hover { background: #0ea5e9; color: white; }
        .recaptcha-wrap { display: flex; justify-content: center; margin-bottom: 1rem; }
        .input-icon { position: relative; }
        .input-icon i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #475569; }
        .input-icon .form-control { padding-left: 36px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <div style="font-size:2rem; margin-bottom:8px;">🔐</div>
            <h4 class="fw-bold text-white mb-1">BorrowTrack</h4>
            <p class="text-secondary small">Sign in to your account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger bg-dark border-danger text-danger small">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="small text-secondary mb-1 text-uppercase">Email Address</label>
                <div class="input-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" class="form-control" name="email"
                           placeholder="you@gmail.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
            </div>
            <div class="mb-4">
                <label class="small text-secondary mb-1 text-uppercase">Password</label>
                <div class="input-icon">
                    <i class="fas fa-key"></i>
                    <input type="password" class="form-control" name="password"
                           placeholder="••••••••" required>
                </div>
            </div>

            <!-- reCAPTCHA -->
            <div class="recaptcha-wrap">
                <div class="g-recaptcha"
                     data-sitekey="6LcdB-MsAAAAAJzPs-xGnrKGwx6THzsK2vwBD4fQ"
                     data-theme="dark">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3 rounded-pill">
                <i class="fas fa-sign-in-alt me-2"></i>Continue
            </button>
            <div class="text-center">
                <a href="register.php" class="text-secondary small text-decoration-none">
                    Don't have an account? Register
                </a>
            </div>
        </form>
    </div>
</body>
</html>
