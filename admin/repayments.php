<?php
// repayments.php - Loan Payment Management System
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Start session and check authentication
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Get database connection
$pdo = DatabaseConnection::getInstance()->getConnection();

// Initialize variables with default values
$paymentCount = 0;
$paymentSum = 0;
$paymentTrends = [];
$chartLabels = [];
$chartAmounts = [];
$chartCounts = [];
$repayments = [];
$loansNeedingPayment = [];
$error = null;

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $loanId = (int)$_POST['loan_id'];
    $amount = (float)$_POST['amount'];
    $paymentDate = $_POST['payment_date'];
    $method = $_POST['method'] ?? 'auto_debit';
    $adminId = $_SESSION['admin_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Get loan details including university_id
        $loanCheck = $pdo->prepare("
            SELECT l.id, l.student_id, l.university_id, l.amount, l.amount_paid 
            FROM loans l
            WHERE l.id = ?
        ");
        $loanCheck->execute([$loanId]);
        $loan = $loanCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$loan) {
            throw new Exception("Loan not found");
        }
        
        $remainingBalance = $loan['amount'] - $loan['amount_paid'];
        if ($amount > $remainingBalance) {
            throw new Exception("Payment amount cannot exceed remaining balance of P" . number_format($remainingBalance, 2));
        }
        
        // Record the payment with university_id
        $stmt = $pdo->prepare("
            INSERT INTO repayments (loan_id, university_id, amount, method, transaction_reference, is_partial)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $transactionRef = 'ADM' . time() . rand(100, 999);
        $isPartial = ($amount < $remainingBalance) ? 1 : 0;
        
        $stmt->execute([
            $loanId,
            $loan['university_id'],
            $amount,
            $method,
            $transactionRef,
            $isPartial
        ]);
        
        // The trigger will update loan.amount_paid and status automatically
        
        // Log admin action
        $logStmt = $pdo->prepare("
            INSERT INTO admin_logs (action_type, target_id, details) 
            VALUES ('payment_recorded', ?, ?)
        ");
        $logStmt->execute([$loanId, "Recorded payment of P" . number_format($amount, 2) . " for loan #$loanId"]);
        
        $pdo->commit();
        $_SESSION['success_message'] = "Payment recorded successfully! Transaction Reference: $transactionRef";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header("Location: repayments.php");
    exit();
}

// Get filter parameters
$timeframe = $_GET['timeframe'] ?? 'month';
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Calculate date ranges
$now = new DateTime();
$dateFormat = 'Y-m-d';

switch ($timeframe) {
    case 'week':
        $startDate = $now->modify('-1 week')->format($dateFormat);
        break;
    case 'year':
        $startDate = $now->modify('-1 year')->format($dateFormat);
        break;
    case 'all':
        $startDate = '1970-01-01';
        break;
    case 'month':
    default:
        $startDate = $now->modify('-1 month')->format($dateFormat);
        break;
}

// Get repayment data
try {
    // Total payments in timeframe
    $totalPayments = $pdo->prepare("
        SELECT COUNT(*), COALESCE(SUM(amount), 0) 
        FROM repayments 
        WHERE created_at >= ?
    ");
    $totalPayments->execute([$startDate]);
    list($paymentCount, $paymentSum) = $totalPayments->fetch(PDO::FETCH_NUM);
    
    // Payment trend data for chart
    $trendStmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m-%d') AS day,
            COALESCE(SUM(amount), 0) AS total_amount,
            COUNT(*) AS payment_count
        FROM repayments
        WHERE created_at >= ?
        GROUP BY day
        ORDER BY day ASC
    ");
    $trendStmt->execute([$startDate]);
    $paymentTrends = $trendStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    // Prepare chart data
    foreach ($paymentTrends as $trend) {
        $chartLabels[] = $trend['day'];
        $chartAmounts[] = (float)$trend['total_amount'];
        $chartCounts[] = (int)$trend['payment_count'];
    }
    
   // Get recent repayments with proper joins
try {
    $baseQuery = "
        SELECT 
            r.id,
            r.amount,
            r.method,
            r.transaction_reference,
            r.created_at,
            l.id AS loan_id,
            l.amount AS loan_amount,
            l.amount_paid,
            l.status AS loan_status,
            l.due_date,
            s.id AS student_id,
            s.full_name,
            s.student_id AS student_number,
            u.name AS university_name
        FROM repayments r
        JOIN loans l ON r.loan_id = l.id
        JOIN students s ON l.student_id = s.id
        JOIN universities u ON l.university_id = u.id
    ";

    $whereClauses = ["r.created_at >= ?"];
    $params = [$startDate];
    
    if ($statusFilter !== 'all') {
        $whereClauses[] = "l.status = ?";
        $params[] = $statusFilter;
    }
    
    if (!empty($searchQuery)) {
        $whereClauses[] = "(s.full_name LIKE ? OR s.student_id LIKE ? OR l.id LIKE ? OR r.transaction_reference LIKE ?)";
        $searchParam = "%$searchQuery%";
        array_push($params, $searchParam, $searchParam, $searchParam, $searchParam);
    }
    
    $query = $baseQuery . " WHERE " . implode(' AND ', $whereClauses) . " ORDER BY r.created_at DESC LIMIT 100";
    
    error_log("Final query: " . $query);
    error_log("Query params: " . print_r($params, true));
    
    $repaymentsStmt = $pdo->prepare($query);
    $repaymentsStmt->execute($params);
    $repayments = $repaymentsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
} catch (PDOException $e) {
    error_log("Repayment query failed: " . $e->getMessage());
    $error = "Error loading repayment data. Please try again.";
    $repayments = [];
}    
    // Get loans needing payment (approved but not fully paid)
    $loansStmt = $pdo->prepare("
        SELECT 
            l.*,
            s.full_name,
            s.student_id,
            u.name AS university_name,
            (l.amount - l.amount_paid) AS remaining_balance
        FROM loans l
        JOIN students s ON l.student_id = s.id
        JOIN universities u ON l.university_id = u.id
        WHERE l.status = 'approved' AND l.amount_paid < l.amount
        ORDER BY l.due_date ASC
        LIMIT 10
    ");
    $loansStmt->execute();
    $loansNeedingPayment = $loansStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
} catch (PDOException $e) {
    error_log("Repayments page error: " . $e->getMessage());
    $error = "Error loading repayment data. Please try again.";
    // Ensure variables are still initialized even if query fails
    $paymentCount = 0;
    $paymentSum = 0;
    $paymentTrends = [];
    $chartLabels = [];
    $chartAmounts = [];
    $chartCounts = [];
    $repayments = [];
    $loansNeedingPayment = [];
}

// Log admin activity
try {
    $logStmt = $pdo->prepare("
        INSERT INTO admin_logs (action_type, target_id, details) 
        VALUES ('system_change', ?, 'Accessed repayments management')
    ");
    $logStmt->execute([$_SESSION['admin_id'] ?? null]);
} catch (PDOException $e) {
    error_log("Failed to log admin activity: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - UniFlow Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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

        /* Form Styling */
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: white;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #FF6B6B;
            box-shadow: 0 0 0 0.2rem rgba(255, 107, 107, 0.25);
            color: white;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .form-select option {
            background: #1a202c;
            color: white;
        }

        .form-label {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
            margin-bottom: 8px;
        }

        /* Payment Method Cards */
        .payment-method-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }

        .payment-method-card:hover {
            transform: translateY(-3px);
            border-color: rgba(255, 107, 107, 0.5);
            background: rgba(255, 255, 255, 0.08);
        }

        .payment-method-card.selected {
            border-color: #FF6B6B;
            background: rgba(255, 107, 107, 0.1);
        }

        .payment-method-card input[type="radio"] {
            display: none;
        }

        .payment-method-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Loan Summary Card */
        .loan-summary {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .loan-amount {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
            border: none;
            padding: 12px 30px;
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
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            background: transparent;
            transition: all 0.3s ease;
        }

        .btn-outline-custom:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-3px);
            color: white;
            border-color: rgba(255, 255, 255, 0.5);
        }

        /* Alert Styling */
        .alert {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            color: white;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border-color: rgba(40, 167, 69, 0.3);
            color: #28A745;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            border-color: rgba(220, 53, 69, 0.3);
            color: #DC3545;
        }

        /* Progress Bar */
        .progress {
            height: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            margin: 15px 0;
        }

        .progress-bar {
            background: linear-gradient(90deg, #FF6B6B, #4ECDC4);
        }

        /* Input Group */
        .input-group-text {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 10px 0 0 10px;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }

        }
        .chart-container {
            height: 300px;
            position: relative;
        }
        .payment-card {
            transition: transform 0.3s ease;
        }
        .payment-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            font-weight: 600;
            padding: 0.5em 0.8em;
            border-radius: 0.35rem;
        }
        .status-approved {
            background-color: #4CAF50;
        }
        .status-pending {
            background-color: #FFC107;
            color: #000;
        }
        .status-repaid {
            background-color: #2196F3;
        }
        .status-defaulted {
            background-color: #F44336;
        }
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .method-badge {
            font-size: 0.8rem;
            padding: 0.3em 0.6em;
        }
        .method-mobile_money {
            background-color: #8E44AD;
        }
        .method-bank_transfer {
            background-color: #3498DB;
        }
        .method-auto_debit {
            background-color: #2ECC71;
        }
          .back-to-top.active {
            opacity: 1;
            visibility: visible;
        }

        .back-to-top:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
    </style>
</head>
<body class="bg-dark text-white">
    <?php include 'navbar.php'; ?>
    
    <div class="container py-4">
        <!-- Display Error Message if any -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Display Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Payment Management</h1>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                    <i class="fas fa-plus me-2"></i>Record Payment
                </button>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card glass payment-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Payments This Period</h5>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-0"><?= number_format($paymentCount) ?></h2>
                                <p class="mb-0 text-muted">Total Payments</p>
                            </div>
                            <i class="fas fa-money-bill-wave fa-3x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card glass payment-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Amount Collected</h5>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-0">P<?= number_format($paymentSum, 2) ?></h2>
                                <p class="mb-0 text-muted">Total Amount</p>
                            </div>
                            <i class="fas fa-coins fa-3x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card glass payment-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Loans Due</h5>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-0"><?= count($loansNeedingPayment) ?></h2>
                                <p class="mb-0 text-muted">Requiring Payment</p>
                            </div>
                            <i class="fas fa-clock fa-3x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payment Trends Chart -->
        <div class="card glass mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Payment Trends</h5>
                    <div>
                        <select id="timeframeSelect" class="form-select form-select-sm">
                            <option value="week" <?= $timeframe === 'week' ? 'selected' : '' ?>>Last Week</option>
                            <option value="month" <?= $timeframe === 'month' ? 'selected' : '' ?>>Last Month</option>
                            <option value="year" <?= $timeframe === 'year' ? 'selected' : '' ?>>Last Year</option>
                            <option value="all" <?= $timeframe === 'all' ? 'selected' : '' ?>>All Time</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($chartLabels)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-line fa-3x opacity-25 mb-3"></i>
                        <p class="text-muted">No payment data available for the selected timeframe.</p>
                    </div>
                <?php else: ?>
                    <div class="chart-container">
                        <canvas id="paymentTrendsChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Payments Section -->
        <div class="card glass mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Payments</h5>
                    <form method="get" class="d-flex">
                        <select name="status" class="form-select form-select-sm me-2">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Active Loans</option>
                            <option value="repaid" <?= $statusFilter === 'repaid' ? 'selected' : '' ?>>Repaid Loans</option>
                        </select>
                        <input type="text" name="search" class="form-control form-control-sm me-2" 
                               placeholder="Search..." value="<?= htmlspecialchars($searchQuery) ?>">
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($repayments)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-receipt fa-3x opacity-25 mb-3"></i>
                        <p class="text-muted">No payments found for the selected criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Loan ID</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Loan Status</th>
                                    <th>Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($repayments as $payment): ?>
                                    <tr>
                                        <td><?= date('M d, Y', strtotime($payment['created_at'])) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar bg-primary me-2">
                                                    <?= strtoupper(substr($payment['full_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($payment['full_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($payment['student_number']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>#<?= $payment['loan_id'] ?></td>
                                        <td class="fw-bold">P<?= number_format($payment['amount'], 2) ?></td>
                                        <td>
                                            <span class="method-badge method-<?= $payment['method'] ?>">
                                                <?= str_replace('_', ' ', ucfirst($payment['method'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $payment['loan_status'] ?>">
                                                <?= ucfirst($payment['loan_status']) ?>
                                            </span>
                                        </td>
                                        <td><small><?= htmlspecialchars($payment['transaction_reference']) ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Loans Needing Payment -->
        <div class="card glass">
            <div class="card-header">
                <h5 class="mb-0">Loans Needing Payment</h5>
            </div>
            <div class="card-body">
                <?php if (empty($loansNeedingPayment)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success opacity-25 mb-3"></i>
                        <p class="text-muted">No loans requiring payment at this time.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Loan ID</th>
                                    <th>Loan Amount</th>
                                    <th>Amount Paid</th>
                                    <th>Balance</th>
                                    <th>Due Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($loansNeedingPayment as $loan): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar bg-warning me-2">
                                                    <?= strtoupper(substr($loan['full_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($loan['full_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($loan['student_id']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>#<?= $loan['id'] ?></td>
                                        <td>P<?= number_format($loan['amount'], 2) ?></td>
                                        <td>P<?= number_format($loan['amount_paid'], 2) ?></td>
                                        <td class="fw-bold">P<?= number_format($loan['remaining_balance'], 2) ?></td>
                                        <td><?= date('M d, Y', strtotime($loan['due_date'])) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#recordPaymentModal"
                                                    data-loan-id="<?= $loan['id'] ?>"
                                                    data-loan-balance="<?= $loan['remaining_balance'] ?>">
                                                <i class="fas fa-plus me-1"></i>Record Payment
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Record Payment Modal -->
    <div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title">Record New Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="record_payment" value="1">
                        <div class="mb-3">
                            <label for="loan_id" class="form-label">Loan Account</label>
                            <select class="form-select bg-dark text-white" id="loan_id" name="loan_id" required>
                                <option value="">Select a loan...</option>
                                <?php foreach ($loansNeedingPayment as $loan): ?>
                                    <option value="<?= $loan['id'] ?>">
                                        #<?= $loan['id'] ?> - <?= htmlspecialchars($loan['full_name']) ?> (Balance: P<?= number_format($loan['remaining_balance'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="amount" class="form-label">Payment Amount</label>
                            <input type="number" class="form-control bg-dark text-white" id="amount" name="amount" 
                                   step="0.01" min="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="method" class="form-label">Payment Method</label>
                            <select class="form-select bg-dark text-white" id="method" name="method" required>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="auto_debit" selected>Auto Debit</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="payment_date" class="form-label">Payment Date</label>
                            <input type="date" class="form-control bg-dark text-white" id="payment_date" name="payment_date" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Payment Trends Chart - only initialize if we have data
        <?php if (!empty($chartLabels)): ?>
        const ctx = document.getElementById('paymentTrendsChart').getContext('2d');
        const paymentTrendsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [
                    {
                        label: 'Payment Amount (P)',
                        data: <?= json_encode($chartAmounts) ?>,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Payment Count',
                        data: <?= json_encode($chartCounts) ?>,
                        borderColor: 'rgba(153, 102, 255, 1)',
                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                        tension: 0.1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: 'white'
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label.includes('Amount')) {
                                    return label + ': P' + context.raw.toFixed(2);
                                }
                                return label + ': ' + context.raw;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: 'white'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Amount (P)',
                            color: 'white'
                        },
                        ticks: {
                            color: 'white',
                            callback: function(value) {
                                return 'P' + value;
                            }
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Payment Count',
                            color: 'white'
                        },
                        ticks: {
                            color: 'white',
                            precision: 0
                        },
                        grid: {
                            drawOnChartArea: false,
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        // Timeframe selector
        document.getElementById('timeframeSelect').addEventListener('change', function() {
            const timeframe = this.value;
            window.location.href = `repayments.php?timeframe=${timeframe}`;
        });
        
        // Handle loan selection from "Loans Needing Payment" table
        document.querySelectorAll('[data-bs-target="#recordPaymentModal"]').forEach(btn => {
            btn.addEventListener('click', function() {
                const loanId = this.getAttribute('data-loan-id');
                const balance = this.getAttribute('data-loan-balance');
                
                if (loanId && balance) {
                    document.getElementById('loan_id').value = loanId;
                    document.getElementById('amount').max = balance;
                    document.getElementById('amount').placeholder = `Max: P${parseFloat(balance).toFixed(2)}`;
                }
            });
        });
        
        // Reset modal when closed
        document.getElementById('recordPaymentModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('loan_id').value = '';
            document.getElementById('amount').value = '';
            document.getElementById('amount').removeAttribute('max');
            document.getElementById('amount').placeholder = '';
        });
    </script>
</body>
</html>