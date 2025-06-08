<?php
// Enhanced session configuration
session_start([
    'use_strict_mode' => true,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict'
]);

require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Check if user is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$error = null;
$student = [];
$loans = [];
$totalLoans = 0;
$totalPaid = 0;
$totalPending = 0;
$activeLoans = [];
$pendingLoans = [];
$repaidLoans = [];

try {
    $pdo = DatabaseConnection::getInstance()->getConnection();
    
    // Get student info
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_id, s.full_name, s.fnb_account_number, 
               u.name AS university_name, u.id AS university_id
        FROM students s
        JOIN universities u ON s.university_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$_SESSION['student_db_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception("Student not found");
    }
    
    // Get all loans for the student
    $stmt = $pdo->prepare("
        SELECT l.*, 
               (SELECT COALESCE(SUM(amount), 0) FROM repayments WHERE loan_id = l.id) AS amount_paid,
               (l.amount - (SELECT COALESCE(SUM(amount), 0) FROM repayments WHERE loan_id = l.id)) AS amount_remaining
        FROM loans l
        WHERE l.student_id = ? AND l.university_id = ?
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$_SESSION['student_db_id'], $student['university_id']]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    foreach ($loans as $loan) {
        $totalLoans += (float)$loan['amount'];
        $totalPaid += (float)$loan['amount_paid'];
        $totalPending += ((float)$loan['amount'] - (float)$loan['amount_paid']);
        
        // Categorize loans
        if ($loan['status'] === 'approved' && $loan['amount_remaining'] > 0) {
            $activeLoans[] = $loan;
        } elseif ($loan['status'] === 'pending') {
            $pendingLoans[] = $loan;
        } elseif ($loan['status'] === 'repaid') {
            $repaidLoans[] = $loan;
        }
    }
    
} catch (Exception $e) {
    error_log("Loans page error: " . $e->getMessage());
    $error = "Error loading loan data. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Loans - UniFlow Student Loans</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        /* Loan Cards */
        .loan-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .loan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            background: rgba(255, 255, 255, 0.08);
        }

        .loan-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #FFC107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .status-approved {
            background: rgba(40, 167, 69, 0.2);
            color: #28A745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .status-repaid {
            background: rgba(108, 117, 125, 0.2);
            color: #6C757D;
            border: 1px solid rgba(108, 117, 125, 0.3);
        }

        .status-defaulted {
            background: rgba(220, 53, 69, 0.2);
            color: #DC3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .loan-amount {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .progress {
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            margin: 15px 0;
        }

        .progress-bar {
            background: linear-gradient(90deg, #FF6B6B, #4ECDC4);
        }

        /* Stats Cards */
        .stats-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ffffff, #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 5px;
        }

        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(255, 107, 107, 0.3);
        }

        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(255, 107, 107, 0.4);
            background: linear-gradient(135deg, #FF8E53, #FF6B6B);
        }

        .btn-outline-custom {
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 600;
            background: transparent;
            transition: all 0.3s ease;
        }

        .btn-outline-custom:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-3px);
            color: white;
        }

        /* Loan Details Modal */
        .modal-content {
            background: rgba(26, 32, 44, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }

        .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .loan-amount {
                font-size: 1.2rem;
            }
            
            .stats-number {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animated"></div>
    
    <!-- Include Navbar -->
    <?php include($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/student/navbar.php'); ?>
    
    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="mb-3" style="background: linear-gradient(135deg, #ffffff, #e0e7ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                    <i class="fas fa-file-invoice-dollar me-2"></i> My Loans
                </h1>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger glass mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Stats Overview -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card glass">
                    <div class="stats-number">P<?php echo number_format($totalLoans, 2); ?></div>
                    <p class="mb-0">Total Borrowed</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card glass">
                    <div class="stats-number">P<?php echo number_format($totalPaid, 2); ?></div>
                    <p class="mb-0">Total Repaid</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card glass">
                    <div class="stats-number">P<?php echo number_format($totalPending, 2); ?></div>
                    <p class="mb-0">Pending Balance</p>
                </div>
            </div>
        </div>
        
        <!-- Active Loans Section -->
        <?php if (!empty($activeLoans)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h4 class="mb-3"><i class="fas fa-clock me-2"></i> Active Loans</h4>
                    
                    <?php foreach ($activeLoans as $loan): ?>
                        <div class="loan-card glass">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-hand-holding-usd fa-3x" style="background: linear-gradient(135deg, #FF6B6B, #4ECDC4); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-1">Loan #<?php echo htmlspecialchars($loan['id']); ?></h5>
                                            <span class="loan-status status-approved">Approved</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <div class="loan-amount">P<?php echo number_format($loan['amount'], 2); ?></div>
                                        <small class="text-muted">Disbursed: <?php echo date('M j, Y', strtotime($loan['disbursement_date'])); ?></small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-end">
                                        <div class="mb-2">
                                            <small class="text-muted">Due: <?php echo date('M j, Y', strtotime($loan['due_date'])); ?></small>
                                        </div>
                                        <button class="btn btn-primary-custom btn-sm" data-bs-toggle="modal" data-bs-target="#loanModal<?php echo $loan['id']; ?>">
                                            View Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Paid: P<?php echo number_format($loan['amount_paid'], 2); ?></small>
                                    <small>Remaining: P<?php echo number_format($loan['amount_remaining'], 2); ?></small>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?php echo ($loan['amount_paid'] / $loan['amount']) * 100; ?>%" 
                                         aria-valuenow="<?php echo ($loan['amount_paid'] / $loan['amount']) * 100; ?>" 
                                         aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Loan Details Modal -->
                        <div class="modal fade" id="loanModal<?php echo $loan['id']; ?>" tabindex="-1" aria-labelledby="loanModalLabel<?php echo $loan['id']; ?>" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="loanModalLabel<?php echo $loan['id']; ?>">
                                            Loan #<?php echo htmlspecialchars($loan['id']); ?> Details
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <h6>Loan Information</h6>
                                                <table class="table table-borderless">
                                                    <tr>
                                                        <td>Amount</td>
                                                        <td class="text-end">P<?php echo number_format($loan['amount'], 2); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Interest Rate</td>
                                                        <td class="text-end"><?php echo htmlspecialchars($loan['interest_rate']); ?>%</td>
                                                    </tr>
                                                    <tr>
                                                        <td>Disbursement Date</td>
                                                        <td class="text-end"><?php echo date('M j, Y', strtotime($loan['disbursement_date'])); ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Repayment Information</h6>
                                                <table class="table table-borderless">
                                                    <tr>
                                                        <td>Due Date</td>
                                                        <td class="text-end"><?php echo date('M j, Y', strtotime($loan['due_date'])); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Amount Paid</td>
                                                        <td class="text-end">P<?php echo number_format($loan['amount_paid'], 2); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Amount Remaining</td>
                                                        <td class="text-end">P<?php echo number_format($loan['amount_remaining'], 2); ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <h6 class="mb-3">Repayment History</h6>
                                        <?php
                                        // Get repayment history for this loan
                                        $stmt = $pdo->prepare("
                                            SELECT * FROM repayments 
                                            WHERE loan_id = ? 
                                            ORDER BY created_at DESC
                                        ");
                                        $stmt->execute([$loan['id']]);
                                        $repayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        ?>
                                        
                                        <?php if (!empty($repayments)): ?>
                                            <div class="table-responsive">
                                                <table class="table table-borderless">
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
                                                            <tr>
                                                                <td><?php echo date('M j, Y', strtotime($repayment['created_at'])); ?></td>
                                                                <td>P<?php echo number_format($repayment['amount'], 2); ?></td>
                                                                <td><?php echo ucfirst(str_replace('_', ' ', $repayment['method'])); ?></td>
                                                                <td><?php echo htmlspecialchars($repayment['transaction_reference']); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-secondary">
                                                No repayment history found for this loan.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <a href="make-payment.php?loan_id=<?php echo $loan['id']; ?>" class="btn btn-primary-custom">
                                            <i class="fas fa-money-bill-wave me-1"></i> Make Payment
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Pending Loans Section -->
        <?php if (!empty($pendingLoans)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h4 class="mb-3"><i class="fas fa-hourglass-half me-2"></i> Pending Loans</h4>
                    
                    <?php foreach ($pendingLoans as $loan): ?>
                        <div class="loan-card glass">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-clock fa-3x" style="color: #FFC107;"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-1">Loan #<?php echo htmlspecialchars($loan['id']); ?></h5>
                                            <span class="loan-status status-pending">Pending</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <div class="loan-amount">P<?php echo number_format($loan['amount'], 2); ?></div>
                                        <small class="text-muted">Applied: <?php echo date('M j, Y', strtotime($loan['created_at'])); ?></small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-end">
                                        <div class="mb-2">
                                            <small class="text-muted">AI Confidence: <?php echo htmlspecialchars($loan['ai_approval_confidence']); ?>%</small>
                                        </div>
                                        <button class="btn btn-outline-custom btn-sm" data-bs-toggle="modal" data-bs-target="#loanModal<?php echo $loan['id']; ?>">
                                            View Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Loan Details Modal -->
                        <div class="modal fade" id="loanModal<?php echo $loan['id']; ?>" tabindex="-1" aria-labelledby="loanModalLabel<?php echo $loan['id']; ?>" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="loanModalLabel<?php echo $loan['id']; ?>">
                                            Loan #<?php echo htmlspecialchars($loan['id']); ?> Details
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td>Amount</td>
                                                <td class="text-end">P<?php echo number_format($loan['amount'], 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Interest Rate</td>
                                                <td class="text-end"><?php echo htmlspecialchars($loan['interest_rate']); ?>%</td>
                                            </tr>
                                            <tr>
                                                <td>Application Date</td>
                                                <td class="text-end"><?php echo date('M j, Y', strtotime($loan['created_at'])); ?></td>
                                            </tr>
                                            <tr>
                                                <td>AI Approval Confidence</td>
                                                <td class="text-end"><?php echo htmlspecialchars($loan['ai_approval_confidence']); ?>%</td>
                                            </tr>
                                            <tr>
                                                <td>Repayment Method</td>
                                                <td class="text-end"><?php echo ucfirst($loan['repayment_method']); ?></td>
                                            </tr>
                                            <?php if ($loan['is_emergency_topup']): ?>
                                                <tr>
                                                    <td>Emergency Top-up</td>
                                                    <td class="text-end text-warning">Yes</td>
                                                </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Repaid Loans Section -->
        <?php if (!empty($repaidLoans)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h4 class="mb-3"><i class="fas fa-check-circle me-2"></i> Repaid Loans</h4>
                    
                    <?php foreach ($repaidLoans as $loan): ?>
                        <div class="loan-card glass">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-check-circle fa-3x" style="color: #6C757D;"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-1">Loan #<?php echo htmlspecialchars($loan['id']); ?></h5>
                                            <span class="loan-status status-repaid">Repaid</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <div class="loan-amount">P<?php echo number_format($loan['amount'], 2); ?></div>
                                        <small class="text-muted">Repaid: <?php echo date('M j, Y', strtotime($loan['status_updated_at'])); ?></small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-end">
                                        <button class="btn btn-outline-custom btn-sm" data-bs-toggle="modal" data-bs-target="#loanModal<?php echo $loan['id']; ?>">
                                            View Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Loan Details Modal -->
                        <div class="modal fade" id="loanModal<?php echo $loan['id']; ?>" tabindex="-1" aria-labelledby="loanModalLabel<?php echo $loan['id']; ?>" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="loanModalLabel<?php echo $loan['id']; ?>">
                                            Loan #<?php echo htmlspecialchars($loan['id']); ?> Details
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <h6>Loan Information</h6>
                                                <table class="table table-borderless">
                                                    <tr>
                                                        <td>Amount</td>
                                                        <td class="text-end">P<?php echo number_format($loan['amount'], 2); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Interest Rate</td>
                                                        <td class="text-end"><?php echo htmlspecialchars($loan['interest_rate']); ?>%</td>
                                                    </tr>
                                                    <tr>
                                                        <td>Disbursement Date</td>
                                                        <td class="text-end"><?php echo date('M j, Y', strtotime($loan['disbursement_date'])); ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Repayment Information</h6>
                                                <table class="table table-borderless">
                                                    <tr>
                                                        <td>Due Date</td>
                                                        <td class="text-end"><?php echo date('M j, Y', strtotime($loan['due_date'])); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Total Paid</td>
                                                        <td class="text-end">P<?php echo number_format($loan['amount_paid'], 2); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Repayment Date</td>
                                                        <td class="text-end"><?php echo date('M j, Y', strtotime($loan['status_updated_at'])); ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <h6 class="mb-3">Repayment History</h6>
                                        <?php
                                        // Get repayment history for this loan
                                        $stmt = $pdo->prepare("
                                            SELECT * FROM repayments 
                                            WHERE loan_id = ? 
                                            ORDER BY created_at DESC
                                        ");
                                        $stmt->execute([$loan['id']]);
                                        $repayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        ?>
                                        
                                        <?php if (!empty($repayments)): ?>
                                            <div class="table-responsive">
                                                <table class="table table-borderless">
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
                                                            <tr>
                                                                <td><?php echo date('M j, Y', strtotime($repayment['created_at'])); ?></td>
                                                                <td>P<?php echo number_format($repayment['amount'], 2); ?></td>
                                                                <td><?php echo ucfirst(str_replace('_', ' ', $repayment['method'])); ?></td>
                                                                <td><?php echo htmlspecialchars($repayment['transaction_reference']); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-secondary">
                                                No repayment history found for this loan.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- No Loans Message -->
        <?php if (empty($loans)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="text-center py-5 glass">
                        <i class="fas fa-file-invoice-dollar fa-4x mb-4" style="background: linear-gradient(135deg, #FF6B6B, #4ECDC4); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                        <h3 class="mb-3">No Loans Found</h3>
                        <p class="mb-4">You haven't applied for any loans yet. Click the button below to apply for your first loan.</p>
                        <a href="apply-loan.php" class="btn btn-primary-custom">
                            <i class="fas fa-plus-circle me-2"></i> Apply for Loan
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Include Footer -->
    <?php include($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/student/footer.php'); ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>