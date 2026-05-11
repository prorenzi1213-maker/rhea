<?php
require_once 'config.php';

// Access Control
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: login.php');
    exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$id) { header('Location: inventory.php'); exit(); }

// Fetch current tool
$stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
$stmt->execute([$id]);
$tool = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$tool) die("Tool not found.");

// Handle Form Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tool'])) {
    $image_name = $tool['image']; // Keep existing image by default

    // Handle Image Upload
    if (!empty($_FILES['tool_image']['name'])) {
        $upload_dir = 'uploads/items/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $image_name = time() . '_' . basename($_FILES['tool_image']['name']);
        move_uploaded_file($_FILES['tool_image']['tmp_name'], $upload_dir . $image_name);
    }

    $stmt = $pdo->prepare("UPDATE inventory SET tool_name = ?, category = ?, stock = ?, status = ?, image = ? WHERE id = ?");
    $stmt->execute([
        trim($_POST['tool_name']),
        $_POST['category'],
        (int)$_POST['stock'],
        $_POST['status'],
        $image_name,
        $id
    ]);
    header("Location: inventory.php?success=1");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Tool | BorrowTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .form-card { border-radius: 20px; border: none; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        .preview-img { width: 100px; height: 100px; object-fit: cover; border-radius: 12px; }
    </style>
</head>
<body class="py-5">

<div class="container" style="max-width: 600px;">
    <div class="form-card bg-white p-5">
        <h4 class="fw-bold mb-4">Edit: <?= htmlspecialchars($tool['tool_name']) ?></h4>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3 text-center">
                <?php 
                $image_path = 'uploads/items/' . ($tool['image'] ?? '');
                if (!empty($tool['image']) && file_exists($image_path)) {
                    echo '<img src="' . $image_path . '" class="preview-img mb-2 border shadow-sm">';
                } else {
                    echo '<img src="https://via.placeholder.com/100x100?text=No+Image" class="preview-img mb-2 border shadow-sm">';
                }
                ?>
                <input type="file" name="tool_image" class="form-control" accept="image/*">
                <small class="text-muted">Change image (optional)</small>
            </div>

            <div class="form-floating mb-3">
                <input type="text" name="tool_name" class="form-control" id="tName" value="<?= htmlspecialchars($tool['tool_name']) ?>" required>
                <label for="tName">Tool Name</label>
            </div>

            <div class="mb-3">
                <label class="form-label text-muted small">Category</label>
                <select name="category" class="form-select">
                    <?php foreach(['Electronics', 'Hardware', 'Power Tools', 'Measuring'] as $cat): ?>
                        <option value="<?= $cat ?>" <?= $tool['category'] == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small">Stock Count</label>
                    <input type="number" name="stock" class="form-control" value="<?= $tool['stock'] ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small">Condition</label>
                    <select name="status" class="form-select">
                        <?php foreach(['Good', 'Expired', 'Malfunction'] as $s): ?>
                            <option value="<?= $s ?>" <?= $tool['status'] == $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <a href="inventory.php" class="btn btn-outline-secondary flex-grow-1 rounded-pill">Cancel</a>
                <button type="submit" name="update_tool" class="btn btn-warning text-white flex-grow-1 rounded-pill">Save Changes</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>