<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

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
    $stmt->execute([$_SESSION['user_id']]);
    $overdue_items = $stmt->fetchAll();
} catch (PDOException $e) { $overdue_items = []; }

$overdue_count = count($overdue_items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications | BorrowTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .sidebar { height: 100vh; background: #0f172a; color: white; position: fixed; width: 250px; }
        .main-content { margin-left: 250px; padding: 40px; }
        .nav-link { color: #94a3b8; transition: 0.3s; padding: 12px 20px; }
        .nav-link:hover, .nav-link.active { color: white; background: #1e293b; border-radius: 8px; }
    </style>
</head>
<body>

<div class="sidebar p-3">
    <h4 class="text-center my-4 fw-bold text-success"><i class="fas fa-handshake me-2"></i>BorrowTrack</h4>
    <ul class="nav flex-column gap-1">
        <li class="nav-item"><a href="user_dashboard.php" class="nav-link"><i class="fas fa-home me-2"></i> Dashboard</a></li>
        <li class="nav-item"><a href="browse_items.php" class="nav-link"><i class="fas fa-search me-2"></i> Browse Items</a></li>
        <li class="nav-item"><a href="my_history.php" class="nav-link"><i class="fas fa-history me-2"></i> My History</a></li>
        <li class="nav-item"><a href="notifications.php" class="nav-link active"><i class="fas fa-bell me-2"></i> Notifications</a></li>
        <li class="nav-item"><a href="profile.php" class="nav-link"><i class="fas fa-user-circle me-2"></i> Profile</a></li>
    </ul>
    <div class="position-absolute bottom-0 start-0 w-100 p-3">
        <a href="logout.php" class="btn btn-outline-danger w-100 btn-sm"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </div>
</div>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-bell me-2 text-warning"></i>Notifications</h2>
        <?php if ($overdue_count > 0): ?>
            <span class="badge bg-danger rounded-pill fs-6 px-3 py-2"><?= $overdue_count ?> Overdue</span>
        <?php endif; ?>
    </div>

    <?php if (empty($overdue_items)): ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
            <i class="fas fa-check-circle fa-4x mb-3 text-success"></i>
            <h5 class="text-muted">No overdue items. All good!</h5>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Tool Name</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Days Overdue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overdue_items as $item): ?>
                            <?php
                            $due = new DateTime($item['due_date']);
                            $now = new DateTime();
                            $days_overdue = (int) $now->diff($due)->format('%r%a');
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?= htmlspecialchars($item['tool_name']) ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($item['due_date'])) ?></td>
                                <td>
                                    <span class="badge rounded-pill bg-danger">Overdue</span>
                                </td>
                                <td class="text-end pe-4">
                                    <span class="badge bg-warning text-dark rounded-pill"><?= abs($days_overdue) ?> day<?= abs($days_overdue) !== 1 ? 's' : '' ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
