<?php
require_once 'config.php';

// Access Control
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: login.php');
    exit();
}

// Handle Delete Action
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: inventory.php?deleted=1");
    exit();
}

// Search & Sort Logic
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM inventory WHERE tool_name LIKE ? OR category LIKE ? ORDER BY category ASC";
$stmt = $pdo->prepare($query);
$stmt->execute(["%$search%", "%$search%"]);
$tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tool Inventory | BorrowTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .card { border: none; border-radius: 16px; }
        .status-badge { font-size: 0.75rem; padding: 6px 12px; border-radius: 20px; font-weight: 600; }
        .action-icons a { transition: 0.2s; font-size: 1.1rem; text-decoration: none; }
        .action-icons a:hover { transform: scale(1.2); }
        .table thead { background-color: #f1f5f9; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm py-3">
    <div class="container">
        <?php $back = ($_SESSION['role'] === 'superadmin') ? 'superadmin_dashboard.php' : 'admin_dashboard.php'; ?>
        <a class="navbar-brand fw-bold" href="<?= $back ?>"><i class="fas fa-arrow-left me-2"></i> Back to Dashboard</a>
    </div>
</nav>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-boxes text-warning me-2"></i>Tool Inventory</h2>
        <a href="admin_add_tool.php" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-plus me-1"></i> Add New Tool
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2">
                <div class="col-md-10">
                    <input type="text" name="search" class="form-control rounded-pill" placeholder="Search by tool name or category..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark w-100 rounded-pill">Search</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="text-uppercase text-muted small">
                        <tr>
                            <th class="ps-4">Category</th>
                            <th>Tool Name</th>
                            <th>Stock</th>
                            <th>Condition</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tools)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No tools found matching your search.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tools as $tool): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($tool['category']) ?></td>
                                    <td><?= htmlspecialchars($tool['tool_name']) ?></td>
                                    <td>
                                        <?= $tool['stock'] ?>
                                        <?php if($tool['stock'] <= 5): ?> 
                                            <span class="badge bg-danger ms-2">Low Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $badgeClass = ($tool['status'] == 'Expired') ? 'bg-danger' : 
                                                         (($tool['status'] == 'Malfunction') ? 'bg-warning text-dark' : 'bg-success');
                                        ?>
                                        <span class="badge <?= $badgeClass ?> status-badge"><?= $tool['status'] ?></span>
                                    </td>
                                    <td class="text-center action-icons">
                                        <a href="edit_tool.php?id=<?= $tool['id'] ?>" class="text-primary me-3" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?= $tool['id'] ?>" class="text-danger" onclick="return confirm('Remove this tool?')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>