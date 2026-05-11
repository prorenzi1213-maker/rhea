<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$pdo->prepare("UPDATE borrow_records SET status = 'denied' WHERE status = 'pending' AND user_id = ? AND borrow_date < NOW() - INTERVAL 12 HOUR")->execute([$_SESSION['user_id']]);

try {
    $stmt = $pdo->prepare("SELECT r.*, i.tool_name FROM borrow_records r LEFT JOIN inventory i ON r.tool_id = i.id WHERE r.user_id = ? ORDER BY r.borrow_date DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $history = $stmt->fetchAll();
} catch (PDOException $e) { $history = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My History | BorrowTrack</title>
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
        <li class="nav-item"><a href="my_history.php" class="nav-link active"><i class="fas fa-history me-2"></i> My History</a></li>
        <li class="nav-item"><a href="notifications.php" class="nav-link"><i class="fas fa-bell me-2"></i> Notifications</a></li>
        <li class="nav-item"><a href="profile.php" class="nav-link"><i class="fas fa-user-circle me-2"></i> Profile</a></li>
    </ul>
    <div class="position-absolute bottom-0 start-0 w-100 p-3">
        <a href="logout.php" class="btn btn-outline-danger w-100 btn-sm"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
</div>
</div>

<main class="main-content">
    <h2 class="fw-bold mb-4">Request History</h2>
    <div class="card border-0 shadow-sm rounded-4">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Tool Name</th>
                    <th>Date Requested</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $row): ?>
                <tr>
                    <td class="ps-4 fw-bold"><?= htmlspecialchars($row['tool_name'] ?? 'N/A') ?></td>
                    <td><?= date('M d, Y', strtotime($row['borrow_date'])) ?></td>
                    <td>
                        <?php
                        $badgeClass = 'bg-success';
                        if ($row['status'] === 'pending') {
                            $badgeClass = 'bg-warning text-dark';
                        } elseif ($row['status'] === 'denied') {
                            $badgeClass = 'bg-danger';
                        }
                        ?>
                        <span class="badge rounded-pill <?= $badgeClass ?>">
                            <?= ucfirst(htmlspecialchars($row['status'])) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>