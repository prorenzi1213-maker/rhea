<?php
require_once 'config.php';

if (isset($_POST['add_tool'])) {
    $name = htmlspecialchars($_POST['tool_name']);
    $cat = htmlspecialchars($_POST['category']);
    $stock = (int)$_POST['stock'];
    $status = $_POST['status'];

    try {
        $stmt = $pdo->prepare("INSERT INTO inventory (tool_name, category, stock, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $cat, $stock, $status]);
        header("Location: inventory.php?success=1");
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}