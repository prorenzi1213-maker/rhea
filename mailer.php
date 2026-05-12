<?php

// ─────────────────────────────────────────────
//  EMAIL CONFIG — reads from environment variables
// ─────────────────────────────────────────────
define('RESEND_API_KEY',  getenv('RESEND_API_KEY')  ?: '');
define('MAIL_FROM_NAME',  getenv('MAIL_FROM_NAME')  ?: 'BorrowTrack');
define('MAIL_FROM_EMAIL', getenv('MAIL_FROM_EMAIL') ?: 'onboarding@resend.dev');
// ─────────────────────────────────────────────

/**
 * Send email via Resend HTTP API (no SMTP needed).
 */
function sendEmail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool
{
    $apiKey = RESEND_API_KEY;
    if (empty($apiKey)) {
        error_log("Resend API key not set.");
        return false;
    }

    $payload = json_encode([
        'from'    => MAIL_FROM_NAME . ' <' . MAIL_FROM_EMAIL . '>',
        'to'      => [$toEmail],
        'subject' => $subject,
        'html'    => $htmlBody,
        'text'    => $textBody ?: strip_tags($htmlBody),
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("Resend curl error: $curlError");
        return false;
    }

    if ($httpCode !== 200 && $httpCode !== 201) {
        error_log("Resend API error ($httpCode): $response");
        return false;
    }

    return true;
}

/**
 * Send login OTP email.
 */
function sendLoginOtpEmail(string $toEmail, string $toName, string $otp): bool
{
    $subject = 'BorrowTrack — Login Verification Code';
    $html    = emailTemplate(
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
         If you did not attempt to log in, please ignore this email.",
        '#22c55e', '🔑'
    );
    return sendEmail($toEmail, $toName, $subject, $html,
        "Your BorrowTrack login code is: $otp (expires in 10 minutes)");
}

/**
 * Send email verification OTP.
 */
function sendOtpEmail(string $toEmail, string $toName, string $otp): bool
{
    $subject = 'BorrowTrack — Email Verification Code';
    $html    = emailTemplate(
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
        '#38bdf8', '🔐'
    );
    return sendEmail($toEmail, $toName, $subject, $html,
        "Your BorrowTrack verification code is: $otp (expires in 10 minutes)");
}

/**
 * Send registration confirmation email.
 */
function sendRegistrationEmail(string $toEmail, string $toName, string $username): bool
{
    $subject = 'BorrowTrack — Registration Received';
    $html    = emailTemplate(
        'Registration Received',
        "Hi <strong>" . htmlspecialchars($toName) . "</strong>,",
        "Your account (<strong>" . htmlspecialchars($username) . "</strong>) has been submitted
         and is currently <span style='color:#f59e0b;font-weight:700;'>pending approval</span>.<br><br>
         You will be able to log in once an administrator reviews and approves your account.",
        '#f59e0b', '⏳'
    );
    return sendEmail($toEmail, $toName, $subject, $html,
        "Hi $toName, your BorrowTrack account ($username) is pending admin approval.");
}

/**
 * Send account approval email.
 */
function sendApprovalEmail(string $toEmail, string $toName, string $username): bool
{
    $appUrl  = defined('APP_URL') ? APP_URL : (getenv('APP_URL') ?: 'http://localhost/borrowtrack');
    $subject = 'BorrowTrack — Account Approved';
    $html    = emailTemplate(
        'Account Approved',
        "Hi <strong>" . htmlspecialchars($toName) . "</strong>,",
        "Great news! Your BorrowTrack account (<strong>" . htmlspecialchars($username) . "</strong>)
         has been <span style='color:#22c55e;font-weight:700;'>approved</span>.<br><br>
         You can now log in and start borrowing equipment.",
        '#22c55e', '✅', 'Log In Now', $appUrl . '/login.php'
    );
    return sendEmail($toEmail, $toName, $subject, $html,
        "Hi $toName, your BorrowTrack account ($username) has been approved.");
}

/**
 * Send borrow request status update email.
 */
function sendBorrowStatusEmail(string $toEmail, string $toName, string $toolName, string $status): bool
{
    $appUrl    = defined('APP_URL') ? APP_URL : (getenv('APP_URL') ?: 'http://localhost/borrowtrack');
    $statusMap = [
        'approved' => ['color' => '#22c55e', 'icon' => '✅', 'label' => 'Approved',
                       'msg'   => 'Your borrow request has been <strong>approved</strong>. Please pick up the item on your scheduled date.'],
        'rejected' => ['color' => '#ef4444', 'icon' => '❌', 'label' => 'Rejected',
                       'msg'   => 'Unfortunately, your borrow request has been <strong>rejected</strong>. Please contact the administrator for more details.'],
        'returned' => ['color' => '#3b82f6', 'icon' => '📦', 'label' => 'Returned',
                       'msg'   => 'The item has been marked as <strong>returned</strong>. Thank you for using BorrowTrack!'],
    ];
    $info = $statusMap[strtolower($status)] ?? [
        'color' => '#64748b', 'icon' => 'ℹ️',
        'label' => ucfirst($status),
        'msg'   => "Your request status has been updated to <strong>$status</strong>."
    ];

    $subject = "BorrowTrack — Request {$info['label']}: " . htmlspecialchars($toolName);
    $html    = emailTemplate(
        "Request {$info['label']}",
        "Hi <strong>" . htmlspecialchars($toName) . "</strong>,",
        $info['msg'] . "<br><br><strong>Item:</strong> " . htmlspecialchars($toolName),
        $info['color'], $info['icon'], 'View My Requests', $appUrl . '/my_requests.php'
    );
    return sendEmail($toEmail, $toName, $subject, $html,
        "Hi $toName, your borrow request for $toolName has been $status.");
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

    return "<!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'></head>
    <body style='margin:0;padding:0;background:#f1f5f9;font-family:Inter,Arial,sans-serif;'>
        <table width='100%' cellpadding='0' cellspacing='0' style='background:#f1f5f9;padding:40px 0;'>
            <tr><td align='center'>
                <table width='560' cellpadding='0' cellspacing='0'
                       style='background:white;border-radius:16px;overflow:hidden;
                              box-shadow:0 4px 20px rgba(0,0,0,0.08);'>
                    <tr>
                        <td style='background:#0f172a;padding:30px;text-align:center;'>
                            <div style='font-size:32px;margin-bottom:8px;'>{$icon}</div>
                            <h1 style='color:white;margin:0;font-size:20px;font-weight:700;'>BorrowTrack</h1>
                            <p style='color:#94a3b8;margin:4px 0 0;font-size:13px;'>{$title}</p>
                        </td>
                    </tr>
                    <tr><td style='height:4px;background:{$accentColor};'></td></tr>
                    <tr>
                        <td style='padding:36px 40px;color:#1e293b;font-size:15px;line-height:1.7;'>
                            <p style='margin:0 0 16px;'>{$greeting}</p>
                            <p style='margin:0;'>{$body}</p>
                            {$button}
                        </td>
                    </tr>
                    <tr>
                        <td style='background:#f8fafc;padding:20px 40px;text-align:center;
                                   border-top:1px solid #e2e8f0;'>
                            <p style='color:#94a3b8;font-size:12px;margin:0;'>
                                This is an automated message from BorrowTrack. Please do not reply.
                            </p>
                        </td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </body>
    </html>";
}
