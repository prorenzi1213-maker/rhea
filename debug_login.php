<?php
require 'config.php';

$username = 'admin';
$password = 'admin123';

$stmt = $pdo->prepare("SELECT id, username, password, role, status, email_verified FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    echo "❌ User '$username' NOT FOUND in database.\n";
} else {
    echo "✅ User found:\n";
    echo "  id       = {$user['id']}\n";
    echo "  username = {$user['username']}\n";
    echo "  role     = {$user['role']}\n";
    echo "  status   = {$user['status']}\n";
    echo "  email_verified = {$user['email_verified']}\n";
    echo "  password_hash  = {$user['password']}\n\n";

    $verify = password_verify($password, $user['password']);
    echo "password_verify('$password', hash) = " . ($verify ? "✅ TRUE" : "❌ FALSE") . "\n";
}
