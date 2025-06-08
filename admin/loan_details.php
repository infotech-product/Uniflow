<?php
// loan_details.php - Loan Application Details
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Start session and check admin authentication
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

// Get loan ID from URL
$loan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($loan_id <= 0) {
    header('Location: loans.php');
    exit();
}

// Get database connection
$pdo = DatabaseConnection::getInstance()->getConnection();

// Get loan details
$loan = [];
try {
    $stmt = $pdo->prepare("SELECT 
                            l.*,
                            s.full_name AS student_name,
                            s.student_id AS student_number,
                            s.fnb_account_number,
                            u.name AS university_name,
                            u.location AS university_location
                          FROM loans l
                          JOIN students s ON l.student_id = s.id
                          JOIN universities u ON l.university_id = u.id
                          WHERE l.id = ?");
    $stmt->execute([$loan_id]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loan) {
        header('Location: loans.php');
        exit();
    }
} catch (PDOException $e) {
    $error_message = "Error fetching loan details: " . $e->getMessage();
}

// Get repayment history
$repayments = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM repayments 
                          WHERE loan_id = ?
                          ORDER BY created_at DESC");
    $stmt->execute([$loan_id]);
    $repayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching repayment history: " . $e->getMessage();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $notes = trim($_POST['notes']);
    
    try {
        // Update loan status
        $stmt = $pdo->prepare("UPDATE loans SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $loan_id]);
        
        // Log the status change
        $stmt = $pdo->prepare("INSERT INTO admin_logs 
                              (action_type, target_id, details, performed_at)
                              VALUES ('loan_status_change', ?, ?, NOW())");
        $stmt->execute([$loan_id, "Status changed to $new_status. Notes: $notes"]);
        
        $_SESSION['success_message'] = "Loan status updated successfully";
        header("Location: loan_details.php?id=$loan_id");
        exit();
    } catch (PDOException $e) {
        $error_message = "Error updating loan status: " . $e->getMessage();
    }
}

// Calculate repayment progress
$repayment_progress = 0;
if ($loan['amount'] > 0) {
    $repayment_progress = min(100, ($loan['amount_paid'] / $loan['amount']) * 100);
}

// Status badge class
$status_badge_class = '';
switch($loan['status']) {
    case 'approved': $status_badge_class = 'badge-approved'; break;
    case 'pending': $status_badge_class = 'badge-pending'; break;
    case 'rejected': $status_badge_class = 'badge-rejected'; break;
    case 'repaid': $status_badge_class = 'badge-repaid'; break;
    case 'defaulted': $status_badge_class = 'badge-defaulted'; break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Details | Uniflow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
               * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
            background: linear-gradient(135deg, #0a1628 0%, #1e3a5f 25%, #2d5a87 50%, #1e3a5f 75%, #0a1628 100%);
            background-attachment: fixed;
            min-height: 100vh;
            color: white;
            padding-top: 70px;
        }

        /* Animated Background */
        .bg-animated {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .bg-animated::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 107, 107, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(42, 82, 152, 0.2) 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translate(-20px, -20px) rotate(0deg); }
            33% { transform: translate(20px, -30px) rotate(120deg); }
            66% { transform: translate(-30px, 20px) rotate(240deg); }
        }

        /* Glass morphism effects */
        .glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
        }

        .glass-dark {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
        }

        /* Navigation */
        .navbar-custom {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Dashboard Cards */
        .dashboard-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-bottom: 25px;
            height: 100%;
            opacity: 0;
            transform: translateY(20px);
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .dashboard-card.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .card-header {
            background: linear-gradient(135deg, rgba(255, 107, 107, 0.3), rgba(78, 205, 196, 0.3));
            color: white;
            font-weight: 600;
            padding: 15px 20px;
            border-bottom: none;
        }
        
        .card-body {
            padding: 25px;
        }
           
        .loan-header {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .detail-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .progress-container {
            height: 10px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .progress-bar {
            background: linear-gradient(90deg, #FF6B6B, #FF8E53);
            border-radius: 5px;
        }
        .badge-approved {
            background-color: rgba(78, 205, 196, 0.2);
            color: #4ECDC4;
        }
        .badge-pending {
            background-color: rgba(255, 193, 160, 0.2);
            color: #FFC3A0;
        }
        .badge-rejected {
            background-color: rgba(255, 107, 107, 0.2);
            color: #FF6B6B;
        }
        .badge-repaid {
            background-color: rgba(106, 141, 255, 0.2);
            color: #6A8DFF;
        }
        .badge-defaulted {
            background-color: rgba(161, 140, 209, 0.2);
            color: #A18CD1;
        }
        .repayment-card {
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            margin-bottom: 10px;
        }
        .repayment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .repayment-bank {
            border-left-color: #6A8DFF;
        }
        .repayment-mobile {
            border-left-color: #4ECDC4;
        }
        .repayment-auto {
            border-left-color: #FF8E53;
        }
        .table-responsive {
            border-radius: 15px;
            overflow: hidden;
        }
        .table {
            background: rgba(255, 255, 255, 0.05);
            margin-bottom: 0;
        }
        .table th {
            background: rgba(0, 0, 0, 0.2);
            color: rgba(255, 255, 255, 0.8);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .table td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animated"></div>

    <!-- Navigation -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/Uniflow/admin/navbar.php'; ?>

    <div class="container py-5">
        <!-- Alerts -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success glass" role="alert">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger glass" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Loan Header -->
        <div class="loan-header glass">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">Loan #<?php echo $loan_id; ?></h1>
                    <p class="welcome-subtitle mb-2">
                        <span class="badge <?php echo $status_badge_class; ?> me-2">
                            <?php echo ucfirst($loan['status']); ?>
                        </span>
                        <span class="text-muted">Created on <?php echo date('M d, Y', strtotime($loan['created_at'])); ?></span>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="loans.php" class="btn btn-outline-custom">
                        <i class="bi bi-arrow-left me-2"></i>Back to Loans
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-6">
                <!-- Loan Details Card -->
                <div class="detail-card glass">
                    <h5 class="mb-4"><i class="bi bi-file-text me-2"></i>Loan Details</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">Student</p>
                            <h6><?php echo htmlspecialchars($loan['student_name']); ?></h6>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">Student ID</p>
                            <h6><?php echo htmlspecialchars($loan['student_number']); ?></h6>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">University</p>
                            <h6><?php echo htmlspecialchars($loan['university_name']); ?></h6>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">FNB Account</p>
                            <h6><?php echo htmlspecialchars($loan['fnb_account_number']); ?></h6>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">Loan Amount</p>
                            <h4>P<?php echo number_format($loan['amount'], 2); ?></h4>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">Amount Paid</p>
                            <h4>P<?php echo number_format($loan['amount_paid'], 2); ?></h4>
                        </div>
                    </div>
                    <div class="mb-3">
                        <p class="mb-1 text-muted">Repayment Progress</p>
                        <div class="progress-container">
                            <div class="progress-bar" style="width: <?php echo $repayment_progress; ?>%"></div>
                        </div>
                        <p class="text-end mb-0"><?php echo number_format($repayment_progress, 1); ?>%</p>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">Interest Rate</p>
                            <h6><?php echo $loan['interest_rate']; ?>%</h6>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">Due Date</p>
                            <h6><?php echo $loan['due_date'] ? date('M d, Y', strtotime($loan['due_date'])) : 'Not set'; ?></h6>
                        </div>
                    </div>
                    <div class="mb-3">
                        <p class="mb-1 text-muted">Purpose</p>
                        <p><?php echo $loan['purpose'] ? htmlspecialchars($loan['purpose']) : 'No purpose specified'; ?></p>
                    </div>
                </div>

                <!-- Status Update Form -->
                <?php if (in_array($loan['status'], ['pending', 'approved'])): ?>
                    <div class="detail-card glass">
                        <h5 class="mb-4"><i class="bi bi-pencil-square me-2"></i>Update Status</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="status" class="form-label">New Status</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="approved" <?php echo $loan['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $loan['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    <?php if ($loan['status'] === 'approved'): ?>
                                        <option value="repaid">Mark as Repaid</option>
                                        <option value="defaulted">Mark as Defaulted</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                            <button type="submit" name="update_status" class="btn btn-primary-custom">
                                <i class="bi bi-save me-2"></i>Update Status
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Column -->
            <div class="col-lg-6">
                <!-- Repayment History -->
                <div class="detail-card glass">
                    <h5 class="mb-4"><i class="bi bi-clock-history me-2"></i>Repayment History</h5>
                    
                    <?php if (!empty($repayments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Reference</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($repayments as $repayment): ?>
                                        <tr class="repayment-card repayment-<?php echo str_replace('_', '-', $repayment['method']); ?>">
                                            <td><?php echo date('M d, Y H:i', strtotime($repayment['created_at'])); ?></td>
                                            <td>P<?php echo number_format($repayment['amount'], 2); ?></td>
                                            <td>
                                                <?php 
                                                $method = str_replace('_', ' ', $repayment['method']);
                                                echo ucwords($method);
                                                ?>
                                            </td>
                                            <td><?php echo $repayment['transaction_reference'] ?: 'N/A'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-cash-stack display-4 text-muted mb-3"></i>
                            <h5>No Repayments Found</h5>
                            <p class="text-muted">This loan doesn't have any repayment records yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Loan Timeline -->
                <div class="detail-card glass">
                    <h5 class="mb-4"><i class="bi bi-list-check me-2"></i>Loan Timeline</h5>
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-point"></div>
                            <div class="timeline-content">
                                <h6>Loan Created</h6>
                                <p class="text-muted"><?php echo date('M d, Y H:i', strtotime($loan['created_at'])); ?></p>
                                <p>Initial loan application submitted</p>
                            </div>
                        </div>
                        
                        <?php if ($loan['disbursement_date']): ?>
                            <div class="timeline-item">
                                <div class="timeline-point"></div>
                                <div class="timeline-content">
                                    <h6>Loan Disbursed</h6>
                                    <p class="text-muted"><?php echo date('M d, Y H:i', strtotime($loan['disbursement_date'])); ?></p>
                                    <p>Funds were released to student</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($loan['status_updated_at'] && $loan['status_updated_at'] != $loan['created_at']): ?>
                            <div class="timeline-item">
                                <div class="timeline-point"></div>
                                <div class="timeline-content">
                                    <h6>Status Changed</h6>
                                    <p class="text-muted"><?php echo date('M d, Y H:i', strtotime($loan['status_updated_at'])); ?></p>
                                    <p>Loan was marked as <?php echo $loan['status']; ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($repayments)): ?>
                            <div class="timeline-item">
                                <div class="timeline-point"></div>
                                <div class="timeline-content">
                                    <h6>First Repayment</h6>
                                    <p class="text-muted"><?php echo date('M d, Y H:i', strtotime($repayments[count($repayments)-1]['created_at'])); ?></p>
                                    <p>First repayment received</p>
                                </div>
                            </div>
                            
                            <?php if (count($repayments) > 1): ?>
                                <div class="timeline-item">
                                    <div class="timeline-point"></div>
                                    <div class="timeline-content">
                                        <h6>Latest Repayment</h6>
                                        <p class="text-muted"><?php echo date('M d, Y H:i', strtotime($repayments[0]['created_at'])); ?></p>
                                        <p>Most recent repayment received</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        .timeline-point {
            position: absolute;
            left: -30px;
            top: 5px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
        }
        .timeline-content {
            padding: 10px 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }
        .timeline-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: -22px;
            top: 25px;
            bottom: 0;
            width: 2px;
            background: rgba(255, 255, 255, 0.1);
        }
    </style>
</body>
</html>