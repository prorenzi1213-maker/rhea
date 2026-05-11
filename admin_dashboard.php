<?php
require_once 'config.php';

// Strictly admin only — superadmin has their own dashboard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin') {
        header('Location: superadmin_dashboard.php');
    } else {
        header('Location: login.php');
    }
    exit();
}

$tableName = 'borrow_records';

// Fetch Statistics
try {
    $stats = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending'  THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned
        FROM $tableName")->fetch(PDO::FETCH_ASSOC);

    // Only count pending regular users (not admins — that's superadmin's job)
    $pendingUsers  = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending' AND role = 'user'")->fetchColumn();
    $pendingBorrows = $pdo->query("SELECT COUNT(*) FROM $tableName WHERE status = 'pending'")->fetchColumn();

} catch (PDOException $e) {
    $stats = ['total' => 0, 'pending' => 0, 'returned' => 0];
    $pendingUsers  = 0;
    $pendingBorrows = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | BorrowTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; }
        .stat-card { border: none; border-radius: 16px; color: white; transition: 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .tool-btn {
            background: white; border: 1px solid #e2e8f0; border-radius: 16px;
            transition: 0.3s; display: block; text-decoration: none; color: #1e293b;
            height: 100%;
        }
        .tool-btn:hover { border-color: #38bdf8; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); transform: translateY(-2px); }
        .badge-notify { position: absolute; top: 15px; right: 15px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm py-3">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">
            <i class="fas fa-shield-alt text-warning me-2"></i>BORROWTRACK ADMIN
        </a>
        <div class="d-flex align-items-center">
            <span class="text-light me-3 small">Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
            <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
</nav>

<div class="container py-5">
    <h3 class="fw-bold mb-4">System Overview</h3>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card stat-card bg-primary p-4 shadow-sm">
                <div class="small opacity-75 text-uppercase fw-bold">Total Transactions</div>
                <div class="display-5 fw-bold"><?= (int)$stats['total'] ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card bg-warning p-4 shadow-sm">
                <div class="small opacity-75 text-uppercase fw-bold">Active Requests</div>
                <div class="display-5 fw-bold"><?= (int)$stats['pending'] ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card bg-success p-4 shadow-sm">
                <div class="small opacity-75 text-uppercase fw-bold">Successful Returns</div>
                <div class="display-5 fw-bold"><?= (int)$stats['returned'] ?></div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 py-4 px-4">
            <h5 class="mb-0 fw-bold text-dark">
                <i class="fas fa-tools me-2 text-primary"></i>Management Console
            </h5>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">

                <!-- Borrowing Requests -->
                <div class="col-md-4">
                    <a href="admin_borrows.php" class="tool-btn p-4 position-relative">
                        <?php if ($pendingBorrows > 0): ?>
                            <span class="badge bg-warning text-dark badge-notify"><?= $pendingBorrows ?> New</span>
                        <?php endif; ?>
                        <i class="fas fa-hand-holding-heart fs-2 text-danger mb-3 d-block"></i>
                        <h6 class="fw-bold">Borrowing Requests</h6>
                        <small class="text-muted">See WHO is borrowing and WHY. Approve or reject requests.</small>
                    </a>
                </div>

                <!-- User Management — regular users only -->
                <div class="col-md-4">
                    <a href="manage_users.php" class="tool-btn p-4 position-relative">
                        <?php if ($pendingUsers > 0): ?>
                            <span class="badge bg-danger badge-notify"><?= $pendingUsers ?></span>
                        <?php endif; ?>
                        <i class="fas fa-users-cog fs-2 text-primary mb-3 d-block"></i>
                        <h6 class="fw-bold">User Management</h6>
                        <small class="text-muted">Approve and manage standard user accounts.</small>
                    </a>
                </div>

                <!-- Inventory -->
                <div class="col-md-4">
                    <a href="inventory.php" class="tool-btn p-4">
                        <i class="fas fa-boxes fs-2 text-warning mb-3 d-block"></i>
                        <h6 class="fw-bold">Stock Control</h6>
                        <small class="text-muted">Edit items, update quantities, and upload tool photos.</small>
                    </a>
                </div>

                <!-- Records -->
                <div class="col-md-4">
                    <a href="records.php" class="tool-btn p-4">
                        <i class="fas fa-history fs-2 text-success mb-3 d-block"></i>
                        <h6 class="fw-bold">Transaction History</h6>
                        <small class="text-muted">Audit trail of every item borrowed and returned.</small>
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
