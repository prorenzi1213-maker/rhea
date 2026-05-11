<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$msg = $_GET['msg'] ?? '';

$stmt = $pdo->prepare("
    SELECT r.*, i.tool_name 
    FROM borrow_records r 
    JOIN inventory i ON r.tool_id = i.id 
    WHERE r.user_id = ? 
    ORDER BY r.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$requests = $stmt->fetchAll();

function getStatusBadge($status) {
    return match(strtolower($status)) {
        'pending'  => 'bg-warning text-dark',
        'approved' => 'bg-info text-white',
        'borrowed' => 'bg-primary text-white',
        'returned' => 'bg-success text-white',
        'rejected' => 'bg-danger text-white',
        default    => 'bg-secondary text-white'
    };
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests | BorrowTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .table-card { border-radius: 15px; border: none; overflow: hidden; }
        .status-badge { border-radius: 20px; padding: 5px 12px; font-size: 0.85rem; font-weight: 600; text-transform: capitalize; }
    </style>
</head>
<body>

<div class="container py-5">
    <?php if ($msg === 'cancelled'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i> Request cancelled successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="mb-3">
        <a href="user_dashboard.php" class="text-decoration-none text-muted">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 fw-bold">My Borrow Requests</h2>
        <a href="browse_items.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
            <i class="fas fa-plus me-1"></i> New Request
        </a>
    </div>

    <div class="card table-card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Item Name</th>
                            <th>Qty</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $row): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold"><?= htmlspecialchars($row['tool_name']) ?></div>
                                </td>
                                <td><?= $row['quantity'] ?></td>
                                <td>
                                    <span class="status-badge <?= getStatusBadge($row['status']) ?>">
                                        <?= $row['status'] ?>
                                    </span>
                                </td>
                                <td class="pe-4 text-end">
                                    <a href="view_request.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1">
                                        <i class="fas fa-eye"></i> View
                                    </a>

                                    <?php if ($row['status'] === 'pending'): ?>
                                        <a href="cancel_request.php?id=<?= $row['id'] ?>" 
                                           class="btn btn-outline-danger btn-sm rounded-pill px-3"
                                           onclick="return confirm('Cancel this request?')">
                                            Cancel
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>