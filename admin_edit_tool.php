<?php
require_once 'config.php';

$id = $_GET['id'] ?? 0;

// Fetch current item data
$stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tool'])) {
    $tool_name = $_POST['tool_name'];
    $category = $_POST['category'];
    $stock = $_POST['stock'];
    $image_name = $item['image']; // Keep existing image by default

    // Handle New Image Upload
    if (!empty($_FILES['tool_image']['name'])) {
        $upload_dir = 'uploads/items/';
        $image_name = time() . '_' . basename($_FILES['tool_image']['name']);
        move_uploaded_file($_FILES['tool_image']['tmp_name'], $upload_dir . $image_name);
        
        // Optional: Delete old image from server to save space
        if ($item['image'] !== 'default_tool.png' && file_exists($upload_dir . $item['image'])) {
            unlink($upload_dir . $item['image']);
        }
    }

    $stmt = $pdo->prepare("UPDATE inventory SET tool_name=?, category=?, stock=?, image=? WHERE id=?");
    $stmt->execute([$tool_name, $category, $stock, $image_name, $id]);
    
    header('Location: manage_inventory.php?updated=1');
    exit();
}
?>

<form method="POST" enctype="multipart/form-data" class="card p-4 shadow-sm">
    <h5 class="fw-bold mb-3">Edit Tool</h5>
    
    <div class="mb-3">
        <img src="uploads/items/<?= htmlspecialchars($item['image']) ?>" width="100" class="rounded mb-2">
        <br>
        <label>Change Image</label>
        <input type="file" name="tool_image" class="form-control" accept="image/*">
    </div>

    <div class="mb-3">
        <label>Tool Name</label>
        <input type="text" name="tool_name" class="form-control" value="<?= htmlspecialchars($item['tool_name']) ?>" required>
    </div>
    
    <div class="row">
        <div class="col-md-6 mb-3">
            <label>Category</label>
            <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($item['category']) ?>" required>
        </div>
        <div class="col-md-6 mb-3">
            <label>Stock</label>
            <input type="number" name="stock" class="form-control" value="<?= (int)$item['stock'] ?>" required>
        </div>
    </div>
    
    <button type="submit" name="update_tool" class="btn btn-primary">Save Changes</button>
</form>