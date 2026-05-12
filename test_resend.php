<?php
// TEMPORARY — DELETE AFTER TESTING

$apiKey   = getenv('BREVO_API_KEY');
$fromName = getenv('MAIL_FROM_NAME')  ?: 'BorrowTrack';
$fromEmail= getenv('MAIL_FROM_EMAIL') ?: '';

echo "<pre>";
echo "BREVO_API_KEY: " . (empty($apiKey) ? '❌ NOT SET' : '✅ ' . substr($apiKey, 0, 12) . '...') . "\n";
echo "MAIL_FROM_EMAIL: " . ($fromEmail ?: '❌ NOT SET') . "\n\n";

$payload = json_encode([
    'sender'      => ['name' => $fromName, 'email' => $fromEmail],
    'to'          => [['email' => 'rheadelana671@gmail.com', 'name' => 'Test']],
    'subject'     => 'BorrowTrack Brevo Test',
    'htmlContent' => '<p>Test email from BorrowTrack via Brevo.</p>',
    'textContent' => 'Test email from BorrowTrack via Brevo.',
]);

$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'api-key: ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT => 10,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Curl Error: " . ($curlError ?: 'none') . "\n";
echo "Response: $response\n";
echo "</pre>";
