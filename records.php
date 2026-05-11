<?php
require_once 'config.php';
require_once 'mailer.php';

// 1. Access Control
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: login.php");
    exit();
}

// 2. Handle Status Updates
if (isset($_GET['return_id'])) {
    $record_id = (int)$_GET['return_id'];
    
    $stmt = $pdo->prepare("UPDATE borrow_records SET status = 'returned' WHERE id = ?");
    $stmt->execute([$record_id]);

    // Send return confirmation email
    $info = $pdo->prepare("
        SELECT u.email, u.full_name, u.username, i.tool_name
        FROM borrow_records r
        JOIN users u ON r.user_id = u.id
        JOIN inventory i ON r.tool_id = i.id
        WHERE r.id = ?
    ");
    $info->execute([$record_id]);
    $borrow_info = $info->fetch();
    if ($borrow_info) {
        sendBorrowStatusEmail(
            $borrow_info['email'],
            $borrow_info['full_name'] ?: $borrow_info['username'],
            $borrow_info['tool_name'],
            'returned'
        );
    }
    
    header("Location: records.php?success=1");
    exit();
}

// 3. Fetch All Records with Tool Names
try {
    // We JOIN with inventory to get the tool_name because it's not in borrow_records
    $query = "SELECT r.*, u.username, i.tool_name 
              FROM borrow_records r 
              JOIN users u ON r.user_id = u.id 
              JOIN inventory i ON r.tool_id = i.id 
              ORDER BY r.id DESC";
    $stmt = $pdo->query($query);
    $records = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_msg = $e->getMessage();
    $records = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Global Records - BorrowTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .badge-pending { background-color: #e67e22; color: white; }
        .badge-approved { background-color: #3498db; color: white; }
        .badge-returned { background-color: #27ae60; color: white; }
        .badge-rejected { background-color: #e74c3c; color: white; }
        .table-container { background: white; border-radius: 15px; overflow: hidden; }
        @media print {
            .btn, .navbar, .alert { display: none !important; }
            .card { border: none !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4 shadow">
    <div class="container">
        <span class="navbar-brand fw-bold"><i class="fas fa-globe me-2 text-success"></i>GLOBAL BORROW RECORDS</span>
        <?php $back = ($_SESSION['role'] === 'superadmin') ? 'superadmin_dashboard.php' : 'admin_dashboard.php'; ?>
        <a href="<?= $back ?>" class="btn btn-outline-light btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</nav>

<div class="container">
    <?php if(isset($error_msg)): ?>
        <div class="alert alert-danger"><?= $error_msg ?></div>
    <?php endif; ?>

    <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i> Record updated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm table-container">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark">All Borrowing History</h5>
            <button class="btn btn-sm btn-primary px-3 shadow-sm" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Print Report
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Borrower</th>
                            <th>Item Name</th>
                            <th>Qty</th>
                            <th>Borrow Date</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($records as $row): ?>
                            <tr>
                                <td class="ps-4 text-muted small">#<?= $row['id'] ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($row['username']) ?></div>
                                    <small class="text-muted">User ID: <?= $row['user_id'] ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($row['tool_name']) ?></div>
                                    <small class="text-muted text-truncate" style="max-width: 150px; display: block;">
                                        <?= htmlspecialchars($row['purpose']) ?>
                                    </small>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= $row['quantity'] ?></span></td>
                                <td>
                                    <div class="small fw-bold"><?= date('M d, Y', strtotime($row['borrow_date'])) ?></div>
                                    <div class="small text-muted"><?= date('h:i A', strtotime($row['borrow_date'])) ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-<?= strtolower($row['status']) ?> px-3 py-2">
                                        <?= ucfirst($row['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($row['status'] !== 'returned'): ?>
                                        <a href="?return_id=<?= $row['id'] ?>" 
                                           class="btn btn-sm btn-success shadow-sm"
                                           onclick="return confirm('Confirm item has been returned?')">
                                            <i class="fas fa-undo me-1"></i> Mark Returned
                                        </a>
                                    <?php else: ?>
                                        <span class="text-success small fw-bold"><i class="fas fa-check-double me-1"></i> Closed</span>
                                    <?php endif; ?>
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