<?php
require_once 'config.php';

// ── Security: require secret key to access this utility ──────────────────────
$secret = getenv('DB_ADMIN_SECRET');
if (empty($secret) || ($_GET['secret'] ?? '') !== $secret) {
    http_response_code(403);
    die("403 Forbidden — This utility is restricted.");
}

// Handle actions
$action = $_GET['action'] ?? 'status';

switch($action) {
    case 'create':
        createDatabase();
        break;
    case 'reset':
        resetDatabase();
        break;
    case 'approve_all':
        approveAllUsers();
        break;
    case 'create_admin':
        createAdmin();
        break;
    case 'users':
        showUsers();
        break;
    default:
        showStatus();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BorrowTrack Database Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .db-container { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 20px; }
        .btn-action { border-radius: 12px; font-weight: 600; padding: 12px 24px; }
        .table { font-size: 0.95rem; }
        .status-card { border-radius: 15px; }
        .admin-creds { background: #d4edda; border-radius: 10px; padding: 15px; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-8">
                <div class="db-container shadow-lg p-4 p-md-5">
                    
                    <?php if($action === 'status'): ?>
                        <!-- STATUS DASHBOARD -->
                        <div class="text-center mb-5">
                            <h1 class="display-4 fw-bold text-primary mb-3">
                                <i class="fas fa-database me-3"></i>
                                BorrowTrack DB
                            </h1>
                            <p class="lead text-muted">Database Management Dashboard</p>
                        </div>
                        
                        <?php
                        $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                        $approved_users = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'approved'")->fetchColumn();
                        $pending_users = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
                        $admins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                        ?>
                        
                        <div class="row g-4 mb-5">
                            <div class="col-md-3">
                                <div class="card status-card border-primary">
                                    <div class="card-body text-center">
                                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                        <h2 class="display-5 fw-bold"><?php echo $total_users; ?></h2>
                                        <p class="text-muted mb-0">Total Users</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card status-card border-success">
                                    <div class="card-body text-center">
                                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                        <h2 class="display-5 fw-bold"><?php echo $approved_users; ?></h2>
                                        <p class="text-muted mb-0">Approved</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card status-card border-warning">
                                    <div class="card-body text-center">
                                        <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                                        <h2 class="display-5 fw-bold"><?php echo $pending_users; ?></h2>
                                        <p class="text-muted mb-0">Pending</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card status-card border-info">
                                    <div class="card-body text-center">
                                        <i class="fas fa-user-shield fa-3x text-info mb-3"></i>
                                        <h2 class="display-5 fw-bold"><?php echo $admins; ?></h2>
                                        <p class="text-muted mb-0">Admins</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <a href="?action=create_admin" class="btn btn-success btn-action btn-lg w-100">
                                    <i class="fas fa-user-plus me-2"></i>Create Admin
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="?action=approve_all" class="btn btn-warning btn-action btn-lg w-100">
                                    <i class="fas fa-check-double me-2"></i>Approve All Users
                                </a>
                            </div>
                        </div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <a href="?action=reset" class="btn btn-danger btn-action btn-lg w-100" onclick="return confirm('Reset ALL data? Admin will be recreated.')">
                                    <i class="fas fa-trash me-2"></i>Reset Database
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="login.php" class="btn btn-primary btn-action btn-lg w-100">
                                    <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                                </a>
                            </div>
                        </div>
                        
                    <?php elseif($action === 'users'): ?>
                        <!-- USERS TABLE -->
                        <h2 class="mb-4"><i class="fas fa-users me-2 text-primary"></i>All Users</h2>
                        <?php showUsers(); ?>
                        
                    <?php endif; ?>
                    
                    <!-- Action Responses -->
                    <?php if($action !== 'status'): ?>
                        <div class="alert alert-success border-0">
                            <i class="fas fa-check-circle me-2"></i>
                            Action completed! <a href="?action=status">View Status</a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-5 pt-4 border-top">
                        <small class="text-muted">
                            <a href="?action=users" class="text-decoration-none">View All Users</a> | 
                            <a href="login.php" class="text-decoration-none">Login Page</a>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    // FUNCTIONS
    function showStatus() {
        global $pdo;
        // Status already shown above
    }
    
    function createDatabase() {
        global $pdo;
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'user') DEFAULT 'user',
            status ENUM('pending', 'approved', 'blocked') DEFAULT 'approved',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql);
        echo "<div class='alert alert-success'>✅ Database tables created!</div>";
    }
    
    function resetDatabase() {
        global $pdo;
        $pdo->exec("DROP TABLE IF EXISTS users");
        createDatabase();
        createAdmin();
    }
    
    function createAdmin() {
        global $pdo;
        $pdo->exec("DELETE FROM users WHERE role = 'admin'");
        $hashed = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES ('admin', 'admin@borrowtrack.com', ?, 'admin', 'approved')");
        $stmt->execute([$hashed]);
    }
    
    function approveAllUsers() {
        global $pdo;
        $pdo->exec("UPDATE users SET status = 'approved'");
    }
    
    function showUsers() {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
        ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($user = $stmt->fetch()): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $user['status'] === 'approved' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    ?>
</body>
</html>