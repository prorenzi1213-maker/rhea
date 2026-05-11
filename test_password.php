<?php
$password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
$input = 'admin123';

if (password_verify($input, $password)) {
    echo "✅ PASSWORD WORKS!";
} else {
    echo "❌ PASSWORD FAILS!";
    echo "<br>Try these: admin123, password, 123456";
}
?>