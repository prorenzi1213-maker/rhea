<?php
require_once 'config.php';
// Add your admin check here (e.g., if($_SESSION['role'] !== 'admin') { header('Location: index.php'); exit(); })

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tool'])) {
    $tool_name = $_POST['tool_name'];
    $category = $_POST['category'];
    $stock = $_POST['stock'];
    
    // Handle Image Upload
    $image_name = 'default_tool.png'; // Default if no file
    if (!empty($_FILES['tool_image']['name'])) {
        $upload_dir = 'uploads/items/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $image_name = time() . '_' . basename($_FILES['tool_image']['name']);
        move_uploaded_file($_FILES['tool_image']['tmp_name'], $upload_dir . $image_name);
    }

    $stmt = $pdo->prepare("INSERT INTO inventory (tool_name, category, stock, image) VALUES (?, ?, ?, ?)");
    $stmt->execute([$tool_name, $category, $stock, $image_name]);
    
    header('Location: manage_inventory.php?success=1');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .form-card { border-radius: 20px; border: none; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        .form-floating > .form-control { border-radius: 12px; }
        .btn-modern { padding: 12px 24px; border-radius: 12px; font-weight: 600; }
        .upload-area { border: 2px dashed #cbd5e1; border-radius: 16px; padding: 20px; text-align: center; color: #64748b; transition: 0.3s; }
        .upload-area:hover { border-color: #3b82f6; background: #eff6ff; }
    </style>
</head>
<body class="p-5">

<div class="container" style="max-width: 600px;">
    <form method="POST" enctype="multipart/form-data" class="form-card p-5 bg-white">
        <h4 class="fw-bold mb-4"><i class="fas fa-plus-circle text-primary me-2"></i>Add New Inventory Item</h4>
        
        <div class="form-floating mb-3">
            <input type="text" name="tool_name" class="form-control" id="tName" placeholder="Drill" required>
            <label for="tName">Tool Name</label>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="form-floating">
                    <input type="text" name="category" class="form-control" id="tCat" placeholder="Hardware" required>
                    <label for="tCat">Category</label>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="form-floating">
                    <input type="number" name="stock" class="form-control" id="tStock" placeholder="0" required>
                    <label for="tStock">Initial Stock</label>
                </div>
            </div>
        </div>

        <div class="upload-area mb-4">
            <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-primary"></i>
            <p class="small mb-1">Click or drag image to upload</p>
            <input type="file" name="tool_image" class="form-control-file" accept="image/*">
        </div>

        <div class="d-flex gap-2">
            <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-modern flex-grow-1">Cancel</a>
            <button type="submit" name="add_tool" class="btn btn-primary btn-modern flex-grow-1">Save Item</button>
        </div>
    </form>
</div>

</body>
</html>