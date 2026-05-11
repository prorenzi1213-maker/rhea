<?php
require_once 'config.php';
require_once 'mailer.php';

// Superadmin ONLY
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: login.php');
    exit();
}

// ── Approve Admin account (superadmin exclusive) ──────────────────────────────
if (isset($_GET['approve_admin'])) {
    $id = (int)$_GET['approve_admin'];
    $check = $pdo->prepare("SELECT id, email, full_name, username, role FROM users WHERE id = ? AND role = 'admin'");
    $check->execute([$id]);
    $target = $check->fetch();
    if ($target) {
        $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role = 'admin'")->execute([$id]);
        sendApprovalEmail($target['email'], $target['full_name'] ?: $target['username'], $target['username']);
    }
    header('Location: superadmin_dashboard.php?tab=admins&msg=admin_approved');
    exit();
}

// ── Reject Admin (delete pending admin) ──────────────────────────────────────
if (isset($_GET['reject_admin'])) {
    $id = (int)$_GET['reject_admin'];
    $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'admin' AND status = 'pending'")->execute([$id]);
    header('Location: superadmin_dashboard.php?tab=admins&msg=admin_rejected');
    exit();
}

// ── Delete any user/admin ─────────────────────────────────────────────────────
if (isset($_GET['delete_user'])) {
    $id = (int)$_GET['delete_user'];
    if ($id === (int)$_SESSION['user_id']) {
        header('Location: superadmin_dashboard.php?msg=cannot_self');
        exit();
    }
    $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'superadmin'")->execute([$id]);
    header('Location: superadmin_dashboard.php?msg=deleted');
    exit();
}

// ── Suspend ───────────────────────────────────────────────────────────────────
if (isset($_GET['suspend'])) {
    $id = (int)$_GET['suspend'];
    $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role != 'superadmin'")->execute([$id]);
    header('Location: superadmin_dashboard.php?msg=suspended');
    exit();
}

// ── Unsuspend ─────────────────────────────────────────────────────────────────
if (isset($_GET['unsuspend'])) {
    $id = (int)$_GET['unsuspend'];
    $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role != 'superadmin'")->execute([$id]);
    header('Location: superadmin_dashboard.php?msg=unsuspended');
    exit();
}

// ── Stats ─────────────────────────────────────────────────────────────────────
$total_users    = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$total_admins   = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$pending_admins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'pending'")->fetchColumn();
$pending_users  = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'  AND status = 'pending'")->fetchColumn();
$total_borrows  = $pdo->query("SELECT COUNT(*) FROM borrow_records")->fetchColumn();

// ── Fetch Admins ──────────────────────────────────────────────────────────────
$admins = $pdo->query("
    SELECT id, username, full_name, email, status, created_at 
    FROM users WHERE role = 'admin' 
    ORDER BY status ASC, created_at DESC
")->fetchAll();

// ── Fetch Regular Users ───────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
if ($search) {
    $stmt = $pdo->prepare("SELECT id, username, full_name, email, status, created_at 
                           FROM users WHERE role = 'user'
                           AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)
                           ORDER BY status ASC, created_at DESC");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT id, username, full_name, email, status, created_at 
                         FROM users WHERE role = 'user' 
                         ORDER BY status ASC, created_at DESC");
}
$reg_users = $stmt->fetchAll();

// Active tab
$active_tab = $_GET['tab'] ?? 'admins';

// Flash messages
$messages = [
    'admin_approved' => ['success', 'Admin account approved. Notification email sent.'],
    'admin_rejected' => ['warning', 'Admin account rejected and removed.'],
    'deleted'        => ['success', 'Account deleted successfully.'],
    'suspended'      => ['warning', 'Account has been suspended.'],
    'unsuspended'    => ['success', 'Account has been unsuspended.'],
    'cannot_self'    => ['danger',  'You cannot delete your own account.'],
];
$flash = $messages[$_GET['msg'] ?? ''] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin | BorrowTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { background: #060d1a; font-family: 'Inter', sans-serif; color: #e2e8f0; min-height: 100vh; margin: 0; }

        /* ── Sidebar ── */
        .sidebar {
            width: 250px; height: 100vh; background: #0a1628;
            border-right: 1px solid #1a2744; position: fixed; top: 0; left: 0;
            display: flex; flex-direction: column; z-index: 100;
        }
        .sidebar-logo {
            padding: 22px 20px 18px; border-bottom: 1px solid #1a2744;
        }
        .sa-badge {
            display: inline-block; background: linear-gradient(135deg,#f59e0b,#ef4444);
            color: #fff; font-size: 0.6rem; font-weight: 800; letter-spacing: 1.5px;
            padding: 2px 8px; border-radius: 20px; margin-top: 4px;
        }
        .sidebar nav { padding: 12px 10px; flex: 1; overflow-y: auto; }
        .sidebar .nav-link {
            color: #4a6080; padding: 10px 14px; border-radius: 8px;
            font-size: 0.875rem; display: flex; align-items: center; gap: 10px;
            transition: 0.15s; margin-bottom: 2px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: #1a2744; color: #e2e8f0;
        }
        .sidebar .nav-link .badge { margin-left: auto; }
        .sidebar-footer {
            padding: 14px; border-top: 1px solid #1a2744;
        }

        /* ── Main ── */
        .main { margin-left: 250px; min-height: 100vh; }

        /* ── Topbar ── */
        .topbar {
            background: #0a1628; border-bottom: 1px solid #1a2744;
            padding: 16px 28px; display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 50;
        }

        /* ── Content ── */
        .content { padding: 28px; }

        /* ── Stat cards ── */
        .stat-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card {
            background: #0a1628; border: 1px solid #1a2744; border-radius: 14px;
            padding: 20px; display: flex; justify-content: space-between; align-items: flex-start;
            transition: 0.2s;
        }
        .stat-card:hover { border-color: #2a3f6a; transform: translateY(-2px); }
        .stat-label { font-size: 0.75rem; color: #4a6080; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .stat-value { font-size: 1.8rem; font-weight: 800; color: #f1f5f9; line-height: 1; }
        .stat-icon {
            width: 44px; height: 44px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
        }

        /* ── Tabs ── */
        .tab-bar {
            display: flex; gap: 4px; background: #0a1628;
            border: 1px solid #1a2744; border-radius: 10px; padding: 4px;
            margin-bottom: 20px; width: fit-content;
        }
        .tab-btn {
            padding: 8px 20px; border-radius: 7px; border: none; background: transparent;
            color: #4a6080; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: 0.15s;
            display: flex; align-items: center; gap: 8px;
        }
        .tab-btn.active { background: #1a2744; color: #f1f5f9; }
        .tab-btn .cnt {
            background: #ef4444; color: white; font-size: 0.65rem;
            padding: 1px 6px; border-radius: 10px; font-weight: 700;
        }

        /* ── Table ── */
        .data-card {
            background: #0a1628; border: 1px solid #1a2744; border-radius: 14px; overflow: hidden;
        }
        .data-card-header {
            padding: 16px 20px; border-bottom: 1px solid #1a2744;
            display: flex; justify-content: space-between; align-items: center;
        }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #0d1f3c; color: #4a6080; font-size: 0.7rem;
            text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 16px;
            font-weight: 700; border-bottom: 1px solid #1a2744;
        }
        tbody tr { border-bottom: 1px solid #111e35; transition: 0.1s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #0d1f3c; }
        tbody td { padding: 13px 16px; font-size: 0.875rem; color: #94a3b8; vertical-align: middle; }

        /* ── Status badges ── */
        .s-badge { font-size: 0.7rem; padding: 3px 10px; border-radius: 20px; font-weight: 700; }
        .s-approved  { background: rgba(34,197,94,0.12);  color: #4ade80; border: 1px solid rgba(34,197,94,0.3); }
        .s-pending   { background: rgba(245,158,11,0.12); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); }
        .s-suspended { background: rgba(239,68,68,0.12);  color: #f87171; border: 1px solid rgba(239,68,68,0.3); }

        /* ── Search ── */
        .search-box {
            background: #0d1f3c; border: 1px solid #1a2744; color: #e2e8f0;
            border-radius: 8px; padding: 7px 14px; font-size: 0.875rem; width: 240px;
        }
        .search-box:focus { outline: none; border-color: #3b82f6; }

        /* ── Pending admin alert banner ── */
        .pending-banner {
            background: linear-gradient(135deg, rgba(245,158,11,0.15), rgba(239,68,68,0.1));
            border: 1px solid rgba(245,158,11,0.3); border-radius: 12px;
            padding: 14px 20px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 12px;
        }
    </style>
</head>
<body>

<!-- ── Sidebar ─────────────────────────────────────────────────────────────── -->
<div class="sidebar">
    <div class="sidebar-logo">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-crown text-warning"></i>
            <span class="fw-bold text-white">BorrowTrack</span>
        </div>
        <div class="sa-badge">SUPER ADMIN</div>
    </div>

    <nav>
        <a href="superadmin_dashboard.php" class="nav-link active">
            <i class="fas fa-th-large"></i> Dashboard
        </a>
        <a href="records.php" class="nav-link">
            <i class="fas fa-history"></i> All Records
        </a>
        <a href="inventory.php" class="nav-link">
            <i class="fas fa-boxes"></i> Inventory
        </a>
        <a href="admin_borrows.php" class="nav-link">
            <i class="fas fa-hand-holding-heart"></i> Borrow Requests
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="small mb-2" style="color:#4a6080;">
            <i class="fas fa-user-shield me-1"></i>
            <?= htmlspecialchars($_SESSION['username']) ?>
        </div>
        <a href="logout.php" class="btn btn-sm btn-outline-danger w-100">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </a>
    </div>
</div>

<!-- ── Main ───────────────────────────────────────────────────────────────── -->
<div class="main">

    <!-- Topbar -->
    <div class="topbar">
        <div>
            <span class="fw-bold text-white" style="font-size:1.05rem;">
                <i class="fas fa-crown text-warning me-2"></i>Super Admin Dashboard
            </span>
            <div class="small" style="color:#4a6080; margin-top:2px;">Full system authority</div>
        </div>
        <div class="small" style="color:#4a6080;"><?= date('l, F j, Y') ?></div>
    </div>

    <div class="content">

        <!-- Flash -->
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash[0] ?> alert-dismissible fade show border-0 mb-4" role="alert">
                <?= $flash[1] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Pending admin banner -->
        <?php if ($pending_admins > 0): ?>
            <div class="pending-banner">
                <i class="fas fa-user-clock text-warning fs-5"></i>
                <div>
                    <strong class="text-warning"><?= $pending_admins ?> Admin account<?= $pending_admins > 1 ? 's' : '' ?> waiting for your approval.</strong>
                    <div class="small" style="color:#94a3b8;">Only you can approve or reject admin registrations.</div>
                </div>
                <a href="superadmin_dashboard.php?tab=admins" class="btn btn-sm btn-warning ms-auto rounded-pill px-3">
                    Review Now
                </a>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stat-grid">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?= $total_users ?></div>
                </div>
                <div class="stat-icon" style="background:rgba(34,197,94,0.1);">
                    <i class="fas fa-users text-success"></i>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-label">Total Admins</div>
                    <div class="stat-value"><?= $total_admins ?></div>
                </div>
                <div class="stat-icon" style="background:rgba(59,130,246,0.1);">
                    <i class="fas fa-user-shield" style="color:#60a5fa;"></i>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-label">Pending Admins</div>
                    <div class="stat-value" style="color:#fbbf24;"><?= $pending_admins ?></div>
                </div>
                <div class="stat-icon" style="background:rgba(245,158,11,0.1);">
                    <i class="fas fa-user-clock" style="color:#f59e0b;"></i>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-label">Total Borrows</div>
                    <div class="stat-value"><?= $total_borrows ?></div>
                </div>
                <div class="stat-icon" style="background:rgba(168,85,247,0.1);">
                    <i class="fas fa-hand-holding-heart" style="color:#a855f7;"></i>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-bar">
            <button class="tab-btn <?= $active_tab === 'admins' ? 'active' : '' ?>"
                    onclick="switchTab('admins')">
                <i class="fas fa-user-shield"></i> Admins
                <?php if ($pending_admins > 0): ?>
                    <span class="cnt"><?= $pending_admins ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-btn <?= $active_tab === 'users' ? 'active' : '' ?>"
                    onclick="switchTab('users')">
                <i class="fas fa-users"></i> Users
                <?php if ($pending_users > 0): ?>
                    <span class="cnt"><?= $pending_users ?></span>
                <?php endif; ?>
            </button>
        </div>

        <!-- ── ADMINS TAB ─────────────────────────────────────────────────── -->
        <div id="tab-admins" class="<?= $active_tab !== 'admins' ? 'd-none' : '' ?>">
            <div class="data-card">
                <div class="data-card-header">
                    <span class="fw-bold text-white">
                        <i class="fas fa-user-shield me-2" style="color:#60a5fa;"></i>
                        Admin Accounts
                    </span>
                    <span class="small" style="color:#4a6080;">Only you can approve or reject these accounts</span>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Admin</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th style="text-align:right;padding-right:20px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($admins)): ?>
                                <tr><td colspan="5" style="text-align:center;padding:40px;color:#4a6080;">No admin accounts found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($admins as $a): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-white"><?= htmlspecialchars($a['full_name'] ?: $a['username']) ?></div>
                                        <div class="small" style="color:#4a6080;">@<?= htmlspecialchars($a['username']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($a['email']) ?></td>
                                    <td>
                                        <span class="s-badge s-<?= $a['status'] ?>">
                                            <?= ucfirst($a['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
                                    <td style="text-align:right;padding-right:20px;">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <?php if ($a['status'] === 'pending'): ?>
                                                <a href="superadmin_dashboard.php?approve_admin=<?= $a['id'] ?>"
                                                   class="btn btn-sm btn-success rounded-pill px-3"
                                                   onclick="return confirm('Approve this admin account?')">
                                                    <i class="fas fa-check me-1"></i> Approve
                                                </a>
                                                <a href="superadmin_dashboard.php?reject_admin=<?= $a['id'] ?>"
                                                   class="btn btn-sm btn-outline-danger rounded-pill px-3"
                                                   onclick="return confirm('Reject and delete this admin account?')">
                                                    <i class="fas fa-times me-1"></i> Reject
                                                </a>
                                            <?php elseif ($a['status'] === 'approved'): ?>
                                                <a href="superadmin_dashboard.php?suspend=<?= $a['id'] ?>"
                                                   class="btn btn-sm btn-warning rounded-pill px-3"
                                                   onclick="return confirm('Suspend this admin?')">
                                                    <i class="fas fa-ban me-1"></i> Suspend
                                                </a>
                                                <a href="superadmin_dashboard.php?delete_user=<?= $a['id'] ?>"
                                                   class="btn btn-sm btn-outline-danger rounded-pill px-2"
                                                   onclick="return confirm('Permanently delete this admin?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php elseif ($a['status'] === 'suspended'): ?>
                                                <a href="superadmin_dashboard.php?unsuspend=<?= $a['id'] ?>"
                                                   class="btn btn-sm btn-info rounded-pill px-3"
                                                   onclick="return confirm('Unsuspend this admin?')">
                                                    <i class="fas fa-undo me-1"></i> Unsuspend
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── USERS TAB ──────────────────────────────────────────────────── -->
        <div id="tab-users" class="<?= $active_tab !== 'users' ? 'd-none' : '' ?>">
            <div class="data-card">
                <div class="data-card-header">
                    <span class="fw-bold text-white">
                        <i class="fas fa-users me-2 text-success"></i>
                        Standard Users
                    </span>
                    <form method="GET" class="d-flex gap-2" onsubmit="document.getElementById('tabInput').value='users'">
                        <input type="hidden" name="tab" id="tabInput" value="users">
                        <input type="text" name="search" class="search-box"
                               placeholder="Search users..."
                               value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-sm btn-outline-secondary">Go</button>
                        <?php if ($search): ?>
                            <a href="superadmin_dashboard.php?tab=users" class="btn btn-sm btn-outline-danger">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th style="text-align:right;padding-right:20px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reg_users)): ?>
                                <tr><td colspan="5" style="text-align:center;padding:40px;color:#4a6080;">No users found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($reg_users as $u): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-white"><?= htmlspecialchars($u['full_name'] ?: $u['username']) ?></div>
                                        <div class="small" style="color:#4a6080;">@<?= htmlspecialchars($u['username']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td>
                                        <span class="s-badge s-<?= $u['status'] ?>">
                                            <?= ucfirst($u['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                    <td style="text-align:right;padding-right:20px;">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <?php if ($u['status'] === 'approved'): ?>
                                                <a href="superadmin_dashboard.php?suspend=<?= $u['id'] ?>"
                                                   class="btn btn-sm btn-warning rounded-pill px-3"
                                                   onclick="return confirm('Suspend this user?')">
                                                    <i class="fas fa-ban me-1"></i> Suspend
                                                </a>
                                            <?php elseif ($u['status'] === 'suspended'): ?>
                                                <a href="superadmin_dashboard.php?unsuspend=<?= $u['id'] ?>"
                                                   class="btn btn-sm btn-info rounded-pill px-3"
                                                   onclick="return confirm('Unsuspend this user?')">
                                                    <i class="fas fa-undo me-1"></i> Unsuspend
                                                </a>
                                            <?php endif; ?>
                                            <a href="superadmin_dashboard.php?delete_user=<?= $u['id'] ?>"
                                               class="btn btn-sm btn-outline-danger rounded-pill px-2"
                                               onclick="return confirm('Permanently delete this user?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchTab(tab) {
    document.getElementById('tab-admins').classList.toggle('d-none', tab !== 'admins');
    document.getElementById('tab-users').classList.toggle('d-none',  tab !== 'users');
    document.querySelectorAll('.tab-btn').forEach((btn, i) => {
        btn.classList.toggle('active', (i === 0 && tab === 'admins') || (i === 1 && tab === 'users'));
    });
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
}
</script>
</body>
</html>
