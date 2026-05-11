<?php
require_once 'config.php';

// Ensure session is started in config.php or here
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$msg = $_GET['msg'] ?? '';

// Fetch requests with tool names using a JOIN
$stmt = $pdo->prepare("
    SELECT r.*, i.tool_name 
    FROM borrow_records r 
    JOIN inventory i ON r.tool_id = i.id 
    WHERE r.user_id = ? 
    ORDER BY r.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$requests = $stmt->fetchAll();

/**
 * Returns the appropriate Bootstrap class for the status badge
 */
function getStatusBadge($status) {
    return match(strtolower($status)) {
        'pending'  => 'bg-warning text-dark',
        'approved' => 'bg-info text-white',
        'borrowed' => 'bg-primary text-white',
        'returned' => 'bg-success text-white',
        'rejected' => 'bg-danger text-white',
        default    => 'bg-secondary text-white'
    };
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests | BorrowTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .table-card { border-radius: 15px; border: none; overflow: hidden; }
        .status-badge { border-radius: 20px; padding: 5px 12px; font-size: 0.85rem; font-weight: 600; text-transform: capitalize; }
        .datetime-display { font-size: 0.85rem; color: #666; line-height: 1.4; }
        .btn-back { color: #6c757d; text-decoration: none; transition: 0.3s; font-weight: 500; }
        .btn-back:hover { color: #0d6efd; }
        .table thead { background-color: #f1f3f5; }
        .tool-name-cell { min-width: 200px; }
    </style>
</head>
<body>

<div class="container py-5">
    <?php if ($msg === 'cancelled'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle me-2"></i> 
                <span>Request cancelled successfully.</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="mb-3">
        <a href="user_dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 fw-bold"><i class="fas fa-list-ul text-primary me-2"></i> My Borrow Requests</h2>
        <a href="browse_items.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
            <i class="fas fa-plus me-1"></i> New Request
        </a>
    </div>

    <div class="card table-card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Item Details</th>
                            <th>Qty</th>
                            <th>Schedule</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-3x mb-3 opacity-25"></i>
                                    <p class="mb-0">You haven't made any requests yet.</p>
                                    <a href="browse_items.php" class="btn btn-link">Start browsing tools</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $row): ?>
                                <tr>
                                    <td class="ps-4 tool-name-cell">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['tool_name']) ?></div>
                                        <small class="text-muted text-truncate d-block" style="max-width: 250px;">
                                            <?= htmlspecialchars($row['purpose'] ?? 'No purpose stated') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border fw-normal">
                                            <?= (int)$row['quantity'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="datetime-display">
                                            <i class="far fa-calendar-check text-success me-1"></i>
                                            <?= date('M d, g:i A', strtotime($row['borrow_date'])) ?>
                                        </div>
                                        <div class="datetime-display mt-1">
                                            <i class="far fa-calendar-times text-danger me-1"></i>
                                            <?= date('M d, g:i A', strtotime($row['due_date'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= getStatusBadge($row['status']) ?>">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <a href="cancel_request.php?id=<?= $row['id'] ?>" 
                                               class="btn btn-outline-danger btn-sm rounded-pill px-3"
                                               onclick="return confirm('Are you sure you want to cancel this request?')">
                                                <i class="fas fa-times me-1"></i> Cancel
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-light btn-sm rounded-pill px-3" disabled>
                                                <i class="fas fa-lock me-1"></i> Processed
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>