<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$dbname = 'borrowtrack';
$username = 'root';  // ← YOUR DB USER
$password = '';      // ← YOUR DB PASS

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // Test connection
    $pdo->query("SELECT 1");
} catch(PDOException $e) {
    die("❌ DB ERROR: " . $e->getMessage() . "<br>Check config.php credentials!");
}
?>