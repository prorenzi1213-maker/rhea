<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

// ─────────────────────────────────────────────
//  SMTP CONFIGURATION — edit these values
// ─────────────────────────────────────────────
define('MAIL_HOST',       'smtp.gmail.com');
define('MAIL_USERNAME',   'rheadelana671@gmail.com');
define('MAIL_PASSWORD',   'pmno auji wjvv xzaa');
define('MAIL_PORT',       587);
define('MAIL_FROM_NAME',  'BorrowTrack');
define('MAIL_FROM_EMAIL', 'rheadelana671@gmail.com');
// ─────────────────────────────────────────────

/**
 * Creates and returns a pre-configured PHPMailer instance.
 */
function createMailer(): PHPMailer
{
    $mail = new PHPMailer(true); // true = throw exceptions

    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
    $mail->Port       = MAIL_PORT;

    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';

    return $mail;
}

/**
 * Send a 6-digit LOGIN verification OTP to the user's email.
 */
function sendLoginOtpEmail(string $toEmail, string $toName, string $otp): bool
{
    try {
        $mail = createMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = 'BorrowTrack — Login Verification Code';
        $mail->Body    = emailTemplate(
            'Login Verification',
            "Hi <strong>" . htmlspecialchars($toName) . "</strong>,",
            "A sign-in attempt was made to your BorrowTrack account.<br>
             Use the code below to complete your login. It expires in <strong>10 minutes</strong>.<br><br>
             <div style='text-align:center;margin:24px 0;'>
                 <span style='font-size:36px;font-weight:900;letter-spacing:10px;
                              color:#0f172a;background:#f1f5f9;padding:16px 28px;
                              border-radius:12px;border:2px dashed #22c55e;
                              display:inline-block;'>
                     {$otp}
                 </span>
             </div>
             If you did not attempt to log in, please ignore this email and secure your account.",
            '#22c55e',
            '🔑'
        );
        $mail->AltBody = "Your BorrowTrack login code is: $otp (expires in 10 minutes)";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Login OTP email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send a 6-digit OTP verification code to the user's email.
 *
 * @param string $toEmail  Recipient email
 * @param string $toName   Recipient name
 * @param string $otp      The 6-digit OTP code
 * @return bool
 */
function sendOtpEmail(string $toEmail, string $toName, string $otp): bool
{
    try {
        $mail = createMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = 'BorrowTrack — Email Verification Code';
        $mail->Body    = emailTemplate(
            'Email Verification',
            "Hi <strong>" . htmlspecialchars($toName) . "</strong>,",
            "Use the verification code below to confirm your email address.<br>
             This code expires in <strong>10 minutes</strong>.<br><br>
             <div style='text-align:center;margin:24px 0;'>
                 <span style='font-size:36px;font-weight:900;letter-spacing:10px;
                              color:#0f172a;background:#f1f5f9;padding:16px 28px;
                              border-radius:12px;border:2px dashed #38bdf8;
                              display:inline-block;'>
                     {$otp}
                 </span>
             </div>
             If you did not register on BorrowTrack, please ignore this email.",
            '#38bdf8',
            '🔐'
        );
        $mail->AltBody = "Your BorrowTrack verification code is: $otp (expires in 10 minutes)";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("OTP email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send a registration confirmation email to the new user.
 *
 * @param string $toEmail   Recipient email address
 * @param string $toName    Recipient full name
 * @param string $username  Their chosen username
 * @return bool
 */
function sendRegistrationEmail(string $toEmail, string $toName, string $username): bool
{
    try {
        $mail = createMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = 'BorrowTrack — Registration Received';
        $mail->Body    = emailTemplate(
            'Registration Received',
            "Hi <strong>" . htmlspecialchars($toName) . "</strong>,",
            "Your account (<strong>" . htmlspecialchars($username) . "</strong>) has been submitted 
             and is currently <span style='color:#f59e0b;font-weight:700;'>pending approval</span>.<br><br>
             You will be able to log in once an administrator reviews and approves your account.",
            '#f59e0b',
            '⏳'
        );
        $mail->AltBody = "Hi $toName, your BorrowTrack account ($username) is pending admin approval.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Registration email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send an account approval notification email.
 *
 * @param string $toEmail   Recipient email address
 * @param string $toName    Recipient full name
 * @param string $username  Their username
 * @return bool
 */
function sendApprovalEmail(string $toEmail, string $toName, string $username): bool
{
    try {
        $mail = createMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = 'BorrowTrack — Account Approved';
        $mail->Body    = emailTemplate(
            'Account Approved',
            "Hi <strong>" . htmlspecialchars($toName) . "</strong>,",
            "Great news! Your BorrowTrack account (<strong>" . htmlspecialchars($username) . "</strong>) 
             has been <span style='color:#22c55e;font-weight:700;'>approved</span>.<br><br>
             You can now log in and start borrowing equipment.",
            '#22c55e',
            '✅',
            'Log In Now',
            'http://localhost/borrowtrack/login.php'
        );
        $mail->AltBody = "Hi $toName, your BorrowTrack account ($username) has been approved. You can now log in.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Approval email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send a borrow request status update email.
 *
 * @param string $toEmail   Recipient email
 * @param string $toName    Recipient name
 * @param string $toolName  Name of the tool/item
 * @param string $status    New status (approved / rejected / returned)
 * @return bool
 */
function sendBorrowStatusEmail(string $toEmail, string $toName, string $toolName, string $status): bool
{
    $statusMap = [
        'approved' => ['color' => '#22c55e', 'icon' => '✅', 'label' => 'Approved',  'msg' => 'Your borrow request has been <strong>approved</strong>. Please pick up the item on your scheduled date.'],
        'rejected' => ['color' => '#ef4444', 'icon' => '❌', 'label' => 'Rejected',  'msg' => 'Unfortunately, your borrow request has been <strong>rejected</strong>. Please contact the administrator for more details.'],
        'returned' => ['color' => '#3b82f6', 'icon' => '📦', 'label' => 'Returned',  'msg' => 'The item has been marked as <strong>returned</strong>. Thank you for using BorrowTrack!'],
    ];

    $info = $statusMap[strtolower($status)] ?? ['color' => '#64748b', 'icon' => 'ℹ️', 'label' => ucfirst($status), 'msg' => "Your request status has been updated to <strong>$status</strong>."];

    try {
        $mail = createMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = "BorrowTrack — Request {$info['label']}: " . htmlspecialchars($toolName);
        $mail->Body    = emailTemplate(
            "Request {$info['label']}",
            "Hi <strong>" . htmlspecialchars($toName) . "</strong>,",
            $info['msg'] . "<br><br>
             <strong>Item:</strong> " . htmlspecialchars($toolName),
            $info['color'],
            $info['icon'],
            'View My Requests',
            'http://localhost/borrowtrack/my_requests.php'
        );
        $mail->AltBody = "Hi $toName, your borrow request for $toolName has been $status.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Borrow status email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Reusable HTML email template.
 */
function emailTemplate(
    string $title,
    string $greeting,
    string $body,
    string $accentColor = '#38bdf8',
    string $icon = '📧',
    string $btnText = '',
    string $btnUrl = ''
): string {
    $button = '';
    if ($btnText && $btnUrl) {
        $button = "
        <div style='text-align:center;margin-top:30px;'>
            <a href='{$btnUrl}' 
               style='background:{$accentColor};color:white;padding:12px 30px;
                      border-radius:25px;text-decoration:none;font-weight:700;
                      font-size:14px;display:inline-block;'>
                {$btnText}
            </a>
        </div>";
    }

    return "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'></head>
    <body style='margin:0;padding:0;background:#f1f5f9;font-family:Inter,Arial,sans-serif;'>
        <table width='100%' cellpadding='0' cellspacing='0' style='background:#f1f5f9;padding:40px 0;'>
            <tr><td align='center'>
                <table width='560' cellpadding='0' cellspacing='0' style='background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);'>
                    
                    <!-- Header -->
                    <tr>
                        <td style='background:#0f172a;padding:30px;text-align:center;'>
                            <div style='font-size:32px;margin-bottom:8px;'>{$icon}</div>
                            <h1 style='color:white;margin:0;font-size:20px;font-weight:700;'>BorrowTrack</h1>
                            <p style='color:#94a3b8;margin:4px 0 0;font-size:13px;'>{$title}</p>
                        </td>
                    </tr>

                    <!-- Accent bar -->
                    <tr><td style='height:4px;background:{$accentColor};'></td></tr>

                    <!-- Body -->
                    <tr>
                        <td style='padding:36px 40px;color:#1e293b;font-size:15px;line-height:1.7;'>
                            <p style='margin:0 0 16px;'>{$greeting}</p>
                            <p style='margin:0;'>{$body}</p>
                            {$button}
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style='background:#f8fafc;padding:20px 40px;text-align:center;border-top:1px solid #e2e8f0;'>
                            <p style='color:#94a3b8;font-size:12px;margin:0;'>
                                This is an automated message from BorrowTrack. Please do not reply to this email.
                            </p>
                        </td>
                    </tr>

                </table>
            </td></tr>
        </table>
    </body>
    </html>";
}
