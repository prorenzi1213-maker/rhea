<?php
require_once 'config.php';
require_once 'mailer.php';

define('RECAPTCHA_SITE_SECRET', '6LcdB-MsAAAAAC1RQBkuWKpHj0QhzYB6fZprHlFu');

/**
 * Verify reCAPTCHA v2 token with Google.
 */
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
$success = '';
$total_admin = 0;

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $total_admin = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Database count error: " . $e->getMessage());
}

$registration_closed = ($total_admin >= 5);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $full_name = trim($_POST['full_name'] ?? '');
    $student_number = trim($_POST['student_number'] ?? '');
    $course_section = trim($_POST['course_section'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');

    // Validate inputs
    $fields = [
        'username'       => $username,
        'email'          => $email,
        'password'       => $password,
        'full_name'      => $full_name,
        'student_number' => $student_number,
    ];

    $missing = false;
    foreach (['username', 'email', 'password', 'full_name'] as $f) {
        if (empty($fields[$f])) { $missing = true; break; }
    }
    // student_number only required for non-admin
    if (!$missing && $role !== 'admin' && empty($student_number)) {
        $missing = true;
    }

    if ($missing) {
        $error = "All fields are required.";
    } elseif (!verifyRecaptcha($_POST['g-recaptcha-response'] ?? '')) {
        $error = "Please complete the reCAPTCHA verification.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $error = "Password must contain at least one special character.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif ($role === 'admin' && $registration_closed) {
        $error = "Admin registration limit reached.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $error = "Username or email already exists.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                // Generate 6-digit OTP
                $otp         = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                $stmt = $pdo->prepare("INSERT INTO users 
                    (username, email, password, role, status, full_name, student_number, course_section, year_level, email_otp, otp_expires_at) 
                    VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?)");

                if ($stmt->execute([
                    $username, $email, $hash,
                    ($role === 'admin' ? 'admin' : 'user'),
                    $full_name, $student_number, $course_section, $year_level,
                    $otp, $otp_expires
                ])) {
                    $new_user_id = $pdo->lastInsertId();

                    // Store in session so verify_otp.php knows who to verify
                    $_SESSION['pending_verify_user_id'] = $new_user_id;

                    // Send OTP email
                    sendOtpEmail($email, $full_name ?: $username, $otp);

                    // Redirect to OTP verification page
                    header('Location: verify_otp.php');
                    exit();
                } else {
                    $error = "Registration failed.";
                }
            }
        } catch (PDOException $e) {
            $error = "System error. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BorrowTrack | System Access</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- reCAPTCHA v2 -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        :root { --accent-color: #38bdf8; }
        body { 
            background: radial-gradient(circle at 50% 50%, #1e293b 0%, #020617 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            color: #f1f5f9; font-family: 'Inter', sans-serif;
        }
        .register-card { 
            background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;
            width: 100%; max-width: 450px; padding: 2.5rem; box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }
        .form-control { background: rgba(15, 23, 42, 0.8); border: 1px solid #334155; color: white; }
        .form-control:focus { background: rgba(15, 23, 42, 0.9); border-color: var(--accent-color); color: white; }
        .btn-success { background: var(--accent-color); border: none; font-weight: 700; }
        .stat-badge { background: rgba(56, 189, 248, 0.1); color: var(--accent-color); border: 1px solid var(--accent-color); }
        .recaptcha-wrap { display: flex; justify-content: center; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-white"><i class="fas fa-terminal me-2"></i> BORROWTRACK</h3>
            <p class="text-secondary">Initialize Account Access</p>
        </div>

        <?php if ($error): ?><div class="alert alert-danger bg-dark border-danger text-danger small"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success bg-dark border-success text-success small"><?= $success ?></div>
            <a href="login.php" class="btn btn-primary w-100">Proceed to Login</a>
        <?php else: ?>
            <form method="POST">
                <div class="user-only mb-3">
                    <label class="small text-secondary mb-1">USERNAME</label>
                    <input type="text" class="form-control" name="username" required data-required>
                </div>
                <div class="mb-3">
                    <label class="small text-secondary mb-1">EMAIL_IDENTITY / GMAIL</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                <div class="mb-3">
                    <label class="small text-secondary mb-1">FULL_NAME</label>
                    <input type="text" class="form-control" name="full_name" required>
                </div>
                <div class="user-only mb-3">
                    <label class="small text-secondary mb-1">STUDENT_NUMBER</label>
                    <input type="text" class="form-control" name="student_number" required data-required>
                </div>
                <div class="user-only mb-3">
                    <label class="small text-secondary mb-1">COURSE_SECTION</label>
                    <input type="text" class="form-control" name="course_section">
                </div>
                <div class="user-only mb-3">
                    <label class="small text-secondary mb-1">YEAR_LEVEL</label>
                    <input type="text" class="form-control" name="year_level" placeholder="e.g. 1st Year, 2nd Year">
                </div>
                <div class="mb-3">
                    <label class="small text-secondary mb-1">ACCOUNT_TYPE</label>
                    <select class="form-control" name="role" onchange="toggleFields(this.value)">
                        <option value="user">Standard User</option>
                        <option value="admin" <?= $registration_closed ? 'disabled' : '' ?>>Administrator</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="small text-secondary mb-1">ACCESS_KEY</label>
                    <input type="password" class="form-control" name="password" required>
                </div>
                <div class="mb-4">
                    <label class="small text-secondary mb-1">CONFIRM_KEY</label>
                    <input type="password" class="form-control" name="confirm_password" required>
                </div>
                <!-- reCAPTCHA Widget -->
                <div class="recaptcha-wrap mb-3">
                    <div class="g-recaptcha"
                         data-sitekey="6LcdB-MsAAAAAJzPs-xGnrKGwx6THzsK2vwBD4fQ"
                         data-theme="dark">
                    </div>
                </div>

                <button type="submit" class="btn btn-success w-100 mb-3">CREATE ACCOUNT</button>
                <a href="login.php" class="btn btn-outline-secondary w-100 text-uppercase small">Access Existing Terminal</a>
            </form>
            <script>
            function toggleFields(role) {
                document.querySelectorAll('.user-only').forEach(el => {
                    el.style.display = role === 'admin' ? 'none' : '';
                    el.querySelectorAll('input, select, textarea').forEach(input => {
                        if (role === 'admin') input.removeAttribute('required');
                        else if (input.hasAttribute('data-required')) input.setAttribute('required', '');
                    });
                });
            }
            </script>
        <?php endif; ?>
    </div>
</body>
</html>