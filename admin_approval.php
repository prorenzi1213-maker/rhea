<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Approve user
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role = 'user'");
    $stmt->execute([$_GET['approve']]);
    header('Location: admin_approve.php');
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Approval - BorrowTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2><i class="fas fa-user-check"></i> User Approvals</h2>
        
        <?php
        $stmt = $pdo->query("SELECT id, username, email, created_at, status FROM users WHERE role = 'user' ORDER BY created_at DESC");
        $users = $stmt->fetchAll();
        ?>
        
        <div class="row">
            <?php foreach ($users as $user): ?>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h6><?php echo htmlspecialchars($user['username']); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                            <br><small>Registered: <?php echo date('M j, Y H:i', strtotime($user['created_at'])); ?></small>
                            <span class="badge bg-<?php echo $user['status'] == 'approved' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                            <?php if ($user['status'] == 'pending'): ?>
                                <a href="?approve=<?php echo $user['id']; ?>" 
                                   class="btn btn-sm btn-success mt-2" 
                                   onclick="return confirm('Approve this user?')">
                                    <i class="fas fa-check"></i> Approve
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <a href="admin_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>
</body>
</html>