<?php
session_start();
require_once 'config.php';

// Initialize variables
$message = '';
$message_type = '';
$item = null;

// 2. Access Control
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// 3. Get Item ID and VALIDATE IT
$item_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$item_id) {
    // Instead of just dying, let's give a nice link back
    die("<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>
            <h2>No Item Selected</h2>
            <p>Please select a tool from the inventory first.</p>
            <a href='browse_items.php'>Go back to Inventory</a>
         </div>");
}

// 4. Fetch Item Data
try {
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ? AND stock > 0");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        header('Location: browse_items.php?error=Item unavailable');
        exit();
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// 5. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $tool_id = $_POST['tool_id'];
    $quantity = (int)$_POST['quantity'];
    $purpose = trim($_POST['purpose']);
    $remarks = trim($_POST['remarks'] ?? '');
    
    // Combine Date and Time into SQL Format: YYYY-MM-DD HH:MM:SS
    $borrow_dt = $_POST['borrow_date'] . ' ' . $_POST['borrow_time'] . ':00';
    $due_dt = $_POST['due_date'] . ' ' . $_POST['due_time'] . ':00';
    
    if ($quantity <= 0 || $quantity > $item['stock']) {
        $message = "Invalid quantity. Maximum available is " . $item['stock'];
        $message_type = "danger";
    } elseif (strtotime($due_dt) <= strtotime($borrow_dt)) {
        $message = "Return time must be later than pick-up time.";
        $message_type = "danger";
    } else {
        try {
            // Fetch borrower name from users table
            $user_stmt = $pdo->prepare("SELECT full_name, username FROM users WHERE id = ?");
            $user_stmt->execute([$_SESSION['user_id']]);
            $user_data = $user_stmt->fetch();
            $borrower_name = $user_data['full_name'] ?: $user_data['username'];

            $stmt = $pdo->prepare("INSERT INTO borrow_records 
                (user_id, tool_id, quantity, purpose, remarks, status, borrow_date, due_date, borrower_name) 
                VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?)");
            
            $stmt->execute([
                $_SESSION['user_id'], 
                $tool_id, 
                $quantity, 
                $purpose, 
                $remarks, 
                $borrow_dt, 
                $due_dt,
                $borrower_name
            ]);

            $message = "<i class='fas fa-check-circle me-2'></i> <strong>Success!</strong> Request submitted. Redirecting...";
            $message_type = "success";
            
            // Redirect using Meta Tag (More reliable than Header Refresh in some environments)
            echo '<meta http-equiv="refresh" content="2;url=my_requests.php">';
        } catch (Exception $e) {
            $message = "Submission Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Item | BorrowTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .request-container { max-width: 700px; margin: 40px auto; }
        .card { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .header-gradient { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 12px 12px 0 0; }
        .form-label { font-weight: 600; font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-primary { background: #764ba2; border: none; font-weight: 600; padding: 12px; transition: 0.3s; }
        .btn-primary:hover { background: #667eea; transform: translateY(-2px); }
    </style>
</head>
<body>

<div class="container request-container">
    <div class="card">
        <div class="header-gradient p-4 d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-calendar-alt me-2"></i> Borrowing Schedule</h4>
            <a href="user_dashboard.php" class="btn btn-sm btn-outline-light rounded-pill px-3">Cancel</a>
        </div>
        
        <div class="card-body p-4">
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> py-3 border-0 mb-4 shadow-sm">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php if ($item && $message_type !== 'success'): ?>
                <form method="POST">
                    <input type="hidden" name="tool_id" value="<?= htmlspecialchars($item['id']) ?>">
                    
                    <div class="mb-4 p-3 bg-light rounded border-start border-4 border-primary">
                        <h5 class="mb-1 text-primary"><?= htmlspecialchars($item['tool_name']) ?></h5>
                        <p class="text-muted small mb-0">Currently in stock: <strong><?= $item['stock'] ?> units</strong></p>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Pick-up Date</label>
                            <input type="date" name="borrow_date" class="form-control" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pick-up Time</label>
                            <input type="time" name="borrow_time" class="form-control" value="<?= date('H:i') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Return Date</label>
                            <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Return Time</label>
                            <input type="time" name="due_time" class="form-control" value="17:00" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Quantity to Borrow</label>
                            <input type="number" name="quantity" class="form-control" value="1" min="1" max="<?= $item['stock'] ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Purpose</label>
                            <select name="purpose" class="form-select" required>
                                <option value="Class Project">Class Project</option>
                                <option value="Laboratory">Laboratory</option>
                                <option value="Thesis Work">Thesis Work</option>
                                <option value="Event/Activity">School Event</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3 mb-4">
                        <label class="form-label">Additional Remarks (Optional)</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="e.g. Please check if batteries are included"></textarea>
                    </div>

                    <button type="submit" name="submit_request" class="btn btn-primary w-100 rounded-pill">
                        Submit Request
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>