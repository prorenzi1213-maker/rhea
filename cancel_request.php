<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$request_id = $_GET['id'] ?? null;

if ($request_id) {
    $stmt = $pdo->prepare("UPDATE borrow_records SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'pending'");
    $stmt->execute([$request_id, $_SESSION['user_id']]);
}

header('Location: my_requests.php');
exit();
?>