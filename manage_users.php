<?php
require_once 'config.php';
require_once 'mailer.php';

// Admin only — superadmin manages admins from their own dashboard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin') {
        header('Location: superadmin_dashboard.php');
    } else {
        header('Location: login.php');
    }
    exit();
}

// Approve a regular user (role = 'user' only — admins are approved by superadmin)
if (isset($_GET['approve_id'])) {
    $approve_id = (int)$_GET['approve_id'];

    // Safety: only allow approving 'user' role accounts
    $check = $pdo->prepare("SELECT id, email, full_name, username, role FROM users WHERE id = ? AND role = 'user'");
    $check->execute([$approve_id]);
    $target = $check->fetch();

    if ($target) {
        $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role = 'user'")
            ->execute([$approve_id]);
        sendApprovalEmail($target['email'], $target['full_name'] ?: $target['username'], $target['username']);
        header("Location: manage_users.php?success=1");
    } else {
        // Tried to approve an admin — not allowed
        header("Location: manage_users.php?error=unauthorized");
    }
    exit();
}

// Fetch only regular users (not admins, not superadmin)
$users = $pdo->query("
    SELECT id, username, full_name, email, role, status, created_at 
    FROM users 
    WHERE role = 'user' 
    ORDER BY status ASC, created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | BorrowTrack Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; }
        .card { border: none; border-radius: 16px; }
        .badge-pending  { background: #fef9c3; color: #854d0e; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-suspended { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark shadow-sm py-3">
    <div class="container">
        <a class="navbar-brand fw-bold" href="admin_dashboard.php">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
        <span class="text-secondary small">Admin: <?= htmlspecialchars($_SESSION['username']) ?></span>
    </div>
</nav>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0">User Management</h3>
            <small class="text-muted">Manage standard user accounts only. Admin accounts are managed by Super Admin.</small>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success border-0 shadow-sm">
            <i class="fas fa-check-circle me-2"></i> User approved successfully!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
        <div class="alert alert-danger border-0 shadow-sm">
            <i class="fas fa-ban me-2"></i> You are not authorized to approve admin accounts. Only the Super Admin can do that.
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-white py-3 px-4 border-0">
            <h6 class="mb-0 fw-bold">
                <i class="fas fa-users me-2 text-primary"></i>
                Standard Users
                <span class="badge bg-secondary ms-2"><?= count($users) ?></span>
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fas fa-users fa-2x mb-2 d-block opacity-25"></i>
                                No users found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></div>
                                <small class="text-muted">@<?= htmlspecialchars($user['username']) ?></small>
                            </td>
                            <td class="text-muted small"><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <?php
                                $badgeClass = match($user['status']) {
                                    'approved'  => 'badge-approved',
                                    'suspended' => 'badge-suspended',
                                    default     => 'badge-pending'
                                };
                                ?>
                                <span class="badge <?= $badgeClass ?> px-3 py-2 rounded-pill">
                                    <?= ucfirst($user['status']) ?>
                                </span>
                            </td>
                            <td class="text-muted small">
                                <?= date('M d, Y', strtotime($user['created_at'])) ?>
                            </td>
                            <td class="text-end pe-4">
                                <?php if ($user['status'] === 'pending'): ?>
                                    <a href="manage_users.php?approve_id=<?= $user['id'] ?>"
                                       class="btn btn-sm btn-success rounded-pill px-3"
                                       onclick="return confirm('Approve this user?')">
                                        <i class="fas fa-check me-1"></i> Approve
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" disabled>
                                        <i class="fas fa-check-double me-1"></i> Approved
                                    </button>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
