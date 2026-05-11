<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// App URL — used for email links
define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://localhost/borrowtrack', '/'));

// ── Database Connection ───────────────────────────────────────────────────────
// Priority 1: MYSQL_URL (Railway public URL — most reliable)
if (getenv('MYSQL_URL')) {
    $url      = parse_url(getenv('MYSQL_URL'));
    $host     = $url['host']                    ?? 'localhost';
    $port     = $url['port']                    ?? 3306;
    $username = $url['user']                    ?? 'root';
    $password = $url['pass']                    ?? '';
    $dbname   = ltrim($url['path'] ?? '/railway', '/');
}
// Priority 2: Individual env vars (fallback)
else {
    $host     = getenv('MYSQLHOST')     ?: getenv('MYSQL_HOST')     ?: 'localhost';
    $port     = getenv('MYSQLPORT')     ?: getenv('MYSQL_PORT')     ?: 3306;
    $username = getenv('MYSQLUSER')     ?: getenv('MYSQL_USER')     ?: 'root';
    $password = getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '';
    $dbname   = getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: 'borrowtrack';
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    error_log("DB connection failed: " . $e->getMessage());
    http_response_code(500);
    die("Service temporarily unavailable. Please try again later.");
}
?>
