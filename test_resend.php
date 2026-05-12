<?php
// TEMPORARY — DELETE AFTER TESTING

$apiKey = getenv('RESEND_API_KEY');
$from   = getenv('MAIL_FROM_EMAIL') ?: 'onboarding@resend.dev';
$fromName = getenv('MAIL_FROM_NAME') ?: 'BorrowTrack';

echo "<pre>";
echo "API Key: " . (empty($apiKey) ? '❌ NOT SET' : '✅ Set (' . substr($apiKey, 0, 8) . '...)') . "\n";
echo "From: $fromName <$from>\n\n";

$payload = json_encode([
    'from'    => "$fromName <$from>",
    'to'      => ['rheadelana671@gmail.com'],
    'subject' => 'BorrowTrack Test Email',
    'html'    => '<p>This is a test email from BorrowTrack on Railway.</p>',
    'text'    => 'This is a test email from BorrowTrack on Railway.',
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
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Curl Error: " . ($curlError ?: 'none') . "\n";
echo "Response: $response\n";
echo "</pre>";
