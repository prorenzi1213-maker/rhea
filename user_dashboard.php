<?php
require_once 'config.php';

// 1. Force login check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// 2. Prevent Admins from accessing the User Dashboard
if ($_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Fetch User Stats
try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM borrow_records WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $stats = ['total' => 0, 'pending' => 0];
}

// Fetch Overdue Items (past due_date and not returned/cancelled/rejected)
try {
    $stmt = $pdo->prepare("
        SELECT r.*, i.tool_name 
        FROM borrow_records r 
        JOIN inventory i ON r.tool_id = i.id 
        WHERE r.user_id = ? 
        AND r.due_date < NOW() 
        AND r.status NOT IN ('returned', 'rejected', 'cancelled')
        ORDER BY r.due_date ASC
    ");
    $stmt->execute([$user_id]);
    $overdue_items = $stmt->fetchAll();
} catch (PDOException $e) {
    $overdue_items = [];
}

// Fetch Recent History
$stmt = $pdo->prepare("SELECT * FROM borrow_records WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$stmt->execute([$user_id]);
$my_records = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | BorrowTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .sidebar { height: 100vh; background: #0f172a; color: white; position: fixed; width: 250px; }
        .main-content { margin-left: 250px; padding: 40px; }
        .nav-link { color: #94a3b8; transition: 0.3s; padding: 12px 20px; }
        .nav-link:hover, .nav-link.active { color: white; background: #1e293b; border-radius: 8px; }
        .stat-card { border: none; border-radius: 12px; transition: 0.3s; }
        .badge-pill { padding: 0.5em 1em; font-size: 0.75em; }
    </style>
</head>
<body>

<div class="sidebar p-3">
    <h4 class="text-center my-4 fw-bold text-success"><i class="fas fa-handshake me-2"></i>BorrowTrack</h4>
    <ul class="nav flex-column gap-1">
        <li class="nav-item"><a href="user_dashboard.php" class="nav-link active"><i class="fas fa-home me-2"></i> Dashboard</a></li>
        <li class="nav-item"><a href="browse_items.php" class="nav-link"><i class="fas fa-search me-2"></i> Browse Items</a></li>
        <li class="nav-item"><a href="my_history.php" class="nav-link"><i class="fas fa-history me-2"></i> My History</a></li>
        <li class="nav-item"><a href="profile.php" class="nav-link"><i class="fas fa-user-circle me-2"></i> Profile</a></li>
        <li class="nav-item"><a href="notifications.php" class="nav-link"><i class="fas fa-bell me-2"></i> Notifications</a></li>
    </ul>
    <div class="position-absolute bottom-0 start-0 w-100 p-3">
        <a href="logout.php" class="btn btn-outline-danger w-100 btn-sm"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </div>
</div>

<main class="main-content">
    <header class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold">Welcome back, <?= htmlspecialchars($username); ?>! 👋</h2>
            <p class="text-muted">Manage your borrowed items and requests.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <a href="request_tool.php" class="btn btn-success px-4 py-2 rounded-pill shadow">
                <i class="fas fa-plus me-2"></i>New Request
            </a>
        </div>
    </header>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card stat-card p-4 shadow-sm border-start border-primary border-4">
                <h6 class="text-muted text-uppercase small fw-bold">Total Borrowed</h6>
                <h2 class="fw-bold"><?= $stats['total'] ?? 0 ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card p-4 shadow-sm border-start border-warning border-4">
                <h6 class="text-muted text-uppercase small fw-bold">Waiting Approval</h6>
                <h2 class="fw-bold"><?= $stats['pending'] ?? 0 ?></h2>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3 px-4 border-0">
            <h5 class="mb-0 fw-bold"><i class="fas fa-list me-2 text-success"></i>Recent Requests</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Item Reference</th>
                        <th>Date Requested</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($my_records)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">No records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($my_records as $row): ?>
                        <tr>
                            <td class="ps-4 fw-bold">#<?= htmlspecialchars($row['id']) ?></td>
                            <td><?= date('M d, Y', strtotime($row['borrow_date'])) ?></td>
                            <td>
                                <span class="badge rounded-pill badge-pill <?= $row['status'] === 'pending' ? 'bg-warning text-dark' : 'bg-success' ?>">
                                    <?= ucfirst(htmlspecialchars($row['status'])) ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="view_record.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>