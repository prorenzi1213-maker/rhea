<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$message = '';

// Handle Image Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
    $upload_dir = 'uploads/profiles/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $file_name = $_SESSION['user_id'] . '_' . time() . '.jpg';
    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $file_name)) {
        try {
            // First check if column exists
            $check_column = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_pic'");
            if ($check_column->rowCount() > 0) {
                $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?")->execute([$file_name, $_SESSION['user_id']]);
                $message = '<div class="alert alert-success">Profile picture updated!</div>';
            } else {
                $message = '<div class="alert alert-warning">Profile picture uploaded but database column missing. Please contact administrator to add profile_pic column to users table.</div>';
            }
        } catch(PDOException $e) {
            $message = '<div class="alert alert-danger">Database error: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Failed to upload file.</div>';
    }
}

// Handle Info Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    try {
        $updates = [];
        $params = [];
        
        if (isset($_POST['full_name'])) {
            $updates[] = "full_name = ?";
            $params[] = $_POST['full_name'];
        }
        if (isset($_POST['student_number'])) {
            $updates[] = "student_number = ?";
            $params[] = $_POST['student_number'];
        }
        if (isset($_POST['course_section'])) {
            $updates[] = "course_section = ?";
            $params[] = $_POST['course_section'];
        }
        if (isset($_POST['year_level'])) {
            $updates[] = "year_level = ?";
            $params[] = $_POST['year_level'];
        }
        
        if (!empty($updates)) {
            $params[] = $_SESSION['user_id'];
            $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
            $pdo->prepare($sql)->execute($params);
            $message = '<div class="alert alert-success">Student information updated!</div>';
        }
    } catch(PDOException $e) {
        $message = '<div class="alert alert-danger">Error updating information: ' . $e->getMessage() . '</div>';
    }
}

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Safely get values with defaults
$full_name = isset($user['full_name']) ? $user['full_name'] : '';
$username = isset($user['username']) ? $user['username'] : '';
$course_section = isset($user['course_section']) ? $user['course_section'] : '';
$student_number = isset($user['student_number']) ? $user['student_number'] : '';
$year_level = isset($user['year_level']) ? $user['year_level'] : '';
$pic = isset($user['profile_pic']) && $user['profile_pic'] ? $user['profile_pic'] : 'default.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile | BorrowTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .sidebar { height: 100vh; background: #0f172a; color: white; position: fixed; width: 250px; }
        .main-content { margin-left: 250px; padding: 40px; }
        .nav-link { color: #94a3b8; transition: 0.3s; padding: 12px 20px; }
        .nav-link:hover, .nav-link.active { color: white; background: #1e293b; border-radius: 8px; }
        .card { border-radius: 12px; border: none; }
        .avatar-lg { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid #f8fafc; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<div class="sidebar p-3">
    <h4 class="text-center my-4 fw-bold text-success"><i class="fas fa-handshake me-2"></i>BorrowTrack</h4>
    <ul class="nav flex-column gap-1">
        <li class="nav-item"><a href="user_dashboard.php" class="nav-link"><i class="fas fa-home me-2"></i> Dashboard</a></li>
        <li class="nav-item"><a href="browse_items.php" class="nav-link"><i class="fas fa-search me-2"></i> Browse Items</a></li>
        <li class="nav-item"><a href="my_history.php" class="nav-link"><i class="fas fa-history me-2"></i> My History</a></li>
        <li class="nav-item"><a href="notifications.php" class="nav-link"><i class="fas fa-bell me-2"></i> Notifications</a></li>
        <li class="nav-item"><a href="profile.php" class="nav-link active"><i class="fas fa-user-circle me-2"></i> Profile</a></li>
    </ul>
    <div class="position-absolute bottom-0 start-0 w-100 p-3">
        <a href="logout.php" class="btn btn-outline-danger w-100 btn-sm"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </div>
</div>

<main class="main-content">
    <header class="mb-5">
        <h2 class="fw-bold">Student Profile</h2>
        <p class="text-muted">Manage your personal and academic information.</p>
    </header>
    
    <?= $message ?>
    
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card p-4 text-center shadow-sm">
                <img src="uploads/profiles/<?= htmlspecialchars($pic) ?>" class="avatar-lg mx-auto mb-3" alt="Profile" onerror="this.src='https://via.placeholder.com/120?text=User'">
                <h5 class="fw-bold"><?= htmlspecialchars($full_name ?: $username) ?></h5>
                <p class="text-muted small text-uppercase mb-4"><?= htmlspecialchars($course_section ?: 'No Course Set') ?></p>
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="profile_pic" class="form-control form-control-sm mb-2" accept="image/*">
                    <button class="btn btn-success btn-sm w-100">Update Photo</button>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card p-4 shadow-sm">
                <h5 class="fw-bold mb-4"><i class="fas fa-id-card me-2 text-success"></i>Academic Information</h5>
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted fw-bold">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($full_name) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted fw-bold">Student Number</label>
                            <input type="text" name="student_number" class="form-control" value="<?= htmlspecialchars($student_number) ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted fw-bold">Course & Section</label>
                            <input type="text" name="course_section" class="form-control" value="<?= htmlspecialchars($course_section) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted fw-bold">Year Level</label>
                            <select name="year_level" class="form-select">
                                <option value="1st Year" <?= $year_level == '1st Year' ? 'selected' : '' ?>>1st Year</option>
                                <option value="2nd Year" <?= $year_level == '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
                                <option value="3rd Year" <?= $year_level == '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
                                <option value="4th Year" <?= $year_level == '4th Year' ? 'selected' : '' ?>>4th Year</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="update_info" class="btn btn-success px-4 mt-2">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>