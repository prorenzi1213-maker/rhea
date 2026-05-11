<?php
session_start(); 
require_once 'config.php';
require_once 'mailer.php';

// --- ACCESS CONTROL ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: login.php');
    exit();
}

// --- HANDLE APPROVAL ---
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_request'])) {
    $record_id = $_POST['record_id'];
    
    $stmt = $pdo->prepare("UPDATE borrow_records SET status = 'approved' WHERE id = ?");
    $stmt->execute([$record_id]);
    
    if ($stmt->rowCount() > 0) {
        // Send approval email to the borrower
        $info = $pdo->prepare("
            SELECT u.email, u.full_name, u.username, i.tool_name
            FROM borrow_records r
            JOIN users u ON r.user_id = u.id
            JOIN inventory i ON r.tool_id = i.id
            WHERE r.id = ?
        ");
        $info->execute([$record_id]);
        $borrow_info = $info->fetch();
        if ($borrow_info) {
            sendBorrowStatusEmail(
                $borrow_info['email'],
                $borrow_info['full_name'] ?: $borrow_info['username'],
                $borrow_info['tool_name'],
                'approved'
            );
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=approved");
        exit();
    } else {
        $error = "Database update failed. Ensure the record exists and is not already approved.";
    }
}

// --- QUERY DATA ---
$query = "
    SELECT 
        r.id AS record_id, r.status, r.borrow_date, r.due_date, 
        r.quantity, r.purpose,
        u.full_name, u.student_number, u.course_section, u.year_level,
        i.tool_name
    FROM borrow_records r
    JOIN users u ON r.user_id = u.id
    JOIN inventory i ON r.tool_id = i.id
    ORDER BY r.id DESC
";
$records = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management | Borrowing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; }
        .admin-card { border: none; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); background: white; }
        .info-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 700; margin-bottom: 2px; }
        .date-box { background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; font-weight: 600; color: #1e293b; text-align: center; }
        .badge-success { background-color: #dcfce7; color: #166534; }
        .badge-warning { background-color: #fef9c3; color: #854d0e; }
        .time-badge { font-size: 0.75rem; padding: 4px 8px; border-radius: 6px; display: inline-block; margin-top: 5px; }
        .bg-time-pickup { background-color: #e0e7ff; color: #4338ca; }
        .bg-time-return { background-color: #ffedd5; color: #9a3412; }
        .btn-back { text-decoration: none; color: #64748b; font-weight: 600; transition: 0.2s; }
        .btn-back:hover { color: #1e293b; }
    </style>
</head>
<body class="py-5">

<div class="container">
    <div class="mb-4">
        <?php $back = ($_SESSION['role'] === 'superadmin') ? 'superadmin_dashboard.php' : 'admin_dashboard.php'; ?>
        <a href="<?= $back ?>" class="btn-back">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-arrow-left me-2" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
            </svg>
            Back to Dashboard
        </a>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger shadow-sm border-0"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'approved'): ?>
        <div class="alert alert-success shadow-sm border-0">Request approved successfully!</div>
    <?php endif; ?>

    <div class="card admin-card p-3">
        <div class="d-flex justify-content-between align-items-center mb-4 px-2">
            <h3 class="fw-bold mb-0">Borrowing Requests</h3>
            <span class="badge bg-dark rounded-pill"><?= count($records) ?> Records</span>
        </div>

        <div class="table-responsive">
            <table id="borrowsTable" class="table table-hover align-middle mb-0">
                <thead class="bg-light text-secondary">
                    <tr>
                        <th class="ps-4">Student Info</th>
                        <th>Item Details</th>
                        <th>Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $row): 
                        $status = strtolower(trim($row['status'] ?? 'pending'));
                    ?>
                    <tr>
                        <td class="ps-4 py-4">
                            <div class="fw-bold text-dark"><?= htmlspecialchars($row['full_name']) ?></div>
                            <div class="text-primary small fw-bold"><?= htmlspecialchars($row['student_number']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($row['course_section']) ?></small>
                        </td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($row['tool_name']) ?></div>
                            <small class="text-muted">Qty: <?= htmlspecialchars($row['quantity']) ?></small>
                        </td>
                        <td>
                            <?php if ($status === 'approved'): ?>
                                <span class="badge badge-success px-3 rounded-pill">APPROVED</span>
                            <?php else: ?>
                                <span class="badge badge-warning px-3 rounded-pill">PENDING</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-dark btn-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modal<?= $row['record_id'] ?>">
                                Manage
                            </button>
                        </td>
                    </tr>

                    <div class="modal fade" id="modal<?= $row['record_id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow">
                                <form method="POST">
                                    <input type="hidden" name="record_id" value="<?= $row['record_id'] ?>">
                                    
                                    <div class="modal-header border-0 bg-light">
                                        <h6 class="modal-title fw-bold">Request Review</h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    
                                    <div class="modal-body p-4">
                                        <div class="mb-4">
                                            <div class="info-label">Purpose</div>
                                            <p class="mb-0 text-dark"><?= nl2br(htmlspecialchars($row['purpose'] ?: 'N/A')) ?></p>
                                        </div>

                                        <div class="row g-3 mb-4">
                                            <div class="col-6">
                                                <div class="info-label">Pick-up</div>
                                                <div class="date-box">
                                                    <div><?= date('M d, Y', strtotime($row['borrow_date'])) ?></div>
                                                    <div class="time-badge bg-time-pickup fw-bold">
                                                        <?= date('h:i A', strtotime($row['borrow_date'])) ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="info-label">Return</div>
                                                <div class="date-box">
                                                    <div><?= date('M d, Y', strtotime($row['due_date'])) ?></div>
                                                    <div class="time-badge bg-time-return fw-bold">
                                                        <?= date('h:i A', strtotime($row['due_date'])) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="bg-light p-3 rounded-3 text-center">
                                            <div class="info-label">Student Details</div>
                                            <div class="fw-bold"><?= htmlspecialchars($row['full_name']) ?></div>
                                            <div class="small text-muted">Year <?= htmlspecialchars($row['year_level']) ?></div>
                                        </div>
                                    </div>

                                    <div class="modal-footer border-0 p-4 pt-0">
                                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                                        <?php if ($status !== 'approved'): ?>
                                            <button type="submit" name="approve_request" class="btn btn-success rounded-pill px-4">Approve Now</button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#borrowsTable').DataTable({
        order: [[2, 'asc']],
        language: { search: 'Search:', searchPlaceholder: 'Type to filter...' }
    });
});
</script>
</body>
</html>