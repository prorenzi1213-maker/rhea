<?php
// TEMPORARY DEBUG FILE — DELETE AFTER FIXING

echo "<pre>";

// Show all env vars related to MySQL
echo "=== MySQL ENV VARS ===\n";
echo "MYSQL_URL:        " . (getenv('MYSQL_URL')        ?: 'NOT SET') . "\n";
echo "MYSQL_PUBLIC_URL: " . (getenv('MYSQL_PUBLIC_URL') ?: 'NOT SET') . "\n";
echo "MYSQLHOST:        " . (getenv('MYSQLHOST')        ?: 'NOT SET') . "\n";
echo "MYSQLPORT:        " . (getenv('MYSQLPORT')        ?: 'NOT SET') . "\n";
echo "MYSQLUSER:        " . (getenv('MYSQLUSER')        ?: 'NOT SET') . "\n";
echo "MYSQLDATABASE:    " . (getenv('MYSQLDATABASE')    ?: 'NOT SET') . "\n";
echo "MYSQL_DATABASE:   " . (getenv('MYSQL_DATABASE')   ?: 'NOT SET') . "\n";
echo "APP_URL:          " . (getenv('APP_URL')          ?: 'NOT SET') . "\n";

echo "\n=== PARSED FROM MYSQL_URL ===\n";
if (getenv('MYSQL_URL')) {
    $url = parse_url(getenv('MYSQL_URL'));
    echo "host:   " . ($url['host'] ?? 'N/A') . "\n";
    echo "port:   " . ($url['port'] ?? 'N/A') . "\n";
    echo "user:   " . ($url['user'] ?? 'N/A') . "\n";
    echo "dbname: " . ltrim($url['path'] ?? '', '/') . "\n";
    echo "pass:   " . (isset($url['pass']) ? '(set)' : 'NOT SET') . "\n";
} else {
    echo "MYSQL_URL not set — cannot parse\n";
}

echo "\n=== CONNECTION TEST ===\n";
try {
    if (getenv('MYSQL_URL')) {
        $url      = parse_url(getenv('MYSQL_URL'));
        $host     = $url['host'] ?? 'localhost';
        $port     = $url['port'] ?? 3306;
        $username = $url['user'] ?? 'root';
        $password = $url['pass'] ?? '';
        $dbname   = ltrim($url['path'] ?? '/railway', '/');
    } else {
        $host     = getenv('MYSQLHOST')     ?: 'localhost';
        $port     = getenv('MYSQLPORT')     ?: 3306;
        $username = getenv('MYSQLUSER')     ?: 'root';
        $password = getenv('MYSQLPASSWORD') ?: '';
        $dbname   = getenv('MYSQLDATABASE') ?: 'railway';
    }

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username, $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Connected successfully!\n";
    echo "Tables found:\n";
    foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) {
        echo "  - $t\n";
    }
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
}

echo "</pre>";
