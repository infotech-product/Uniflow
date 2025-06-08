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
$success = null;
$loan = null;
$student = [];
$loan_id = isset($_GET['loan_id']) ? (int)$_GET['loan_id'] : null;

// Process payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = DatabaseConnection::getInstance()->getConnection();
        
        // Validate inputs
        $payment_amount = isset($_POST['payment_amount']) ? (float)$_POST['payment_amount'] : 0;
        $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
        $transaction_reference = isset($_POST['transaction_reference']) ? trim($_POST['transaction_reference']) : '';
        $selected_loan_id = isset($_POST['selected_loan']) ? (int)$_POST['selected_loan'] : $loan_id;
        
        if ($payment_amount <= 0) {
            throw new Exception("Payment amount must be greater than zero.");
        }
        
        if (empty($payment_method)) {
            throw new Exception("Please select a payment method.");
        }
        
        if (empty($transaction_reference)) {
            throw new Exception("Transaction reference is required.");
        }
        
        if (!$selected_loan_id) {
            throw new Exception("Please select a loan to make payment for.");
        }
        
        // Get loan details to verify ownership and calculate remaining amount
        $stmt = $pdo->prepare("
            SELECT l.*, 
                   (l.amount - (SELECT COALESCE(SUM(amount), 0) FROM repayments WHERE loan_id = l.id)) AS amount_remaining,
                   s.university_id
            FROM loans l
            JOIN students s ON l.student_id = s.id
            WHERE l.id = ? AND l.student_id = ? AND l.status = 'approved'
        ");
        $stmt->execute([$selected_loan_id, $_SESSION['student_db_id']]);
        $loan_for_payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$loan_for_payment) {
            throw new Exception("Loan not found or not eligible for payment.");
        }
        
        if ($payment_amount > $loan_for_payment['amount_remaining']) {
            throw new Exception("Payment amount cannot exceed remaining loan balance of P" . number_format($loan_for_payment['amount_remaining'], 2));
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Insert repayment record
        $stmt = $pdo->prepare("
            INSERT INTO repayments (loan_id, university_id, amount, method, transaction_reference, is_partial, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $is_partial = ($payment_amount < $loan_for_payment['amount_remaining']) ? 1 : 0;
        
        $stmt->execute([
            $selected_loan_id,
            $loan_for_payment['university_id'],
            $payment_amount,
            $payment_method,
            $transaction_reference,
            $is_partial
        ]);
        
        // Update loan status if fully paid
        if (!$is_partial) {
            $stmt = $pdo->prepare("
                UPDATE loans 
                SET status = 'repaid', status_updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$selected_loan_id]);
        }
        
        // Update student's AI risk score (improve score for successful payments)
        $stmt = $pdo->prepare("
            UPDATE student_financial_profiles 
            SET ai_risk_score = GREATEST(ai_risk_score - 2, 0),
                dynamic_loan_limit = LEAST(dynamic_loan_limit + 50, 2000)
            WHERE student_id = ?
        ");
        $stmt->execute([$_SESSION['student_db_id']]);
        
        // Award gamification points for payment
        $points_earned = min(floor($payment_amount / 10), 100); // 1 point per P10, max 100 points
        $stmt = $pdo->prepare("
            INSERT INTO gamification_points (student_id, university_id, points, last_activity)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            points = points + VALUES(points),
            last_activity = NOW()
        ");
        $stmt->execute([$_SESSION['student_db_id'], $loan_for_payment['university_id'], $points_earned]);
        
        $pdo->commit();
        
        $success = "Payment of P" . number_format($payment_amount, 2) . " has been successfully recorded! ";
        if ($points_earned > 0) {
            $success .= "You earned " . $points_earned . " points for this payment.";
        }
        
        // Clear form data on success
        $_POST = [];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        error_log("Payment processing error: " . $e->getMessage());
        $error = $e->getMessage();
    }
}

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
    
    // Get loan details if loan_id is provided
    if ($loan_id) {
        $stmt = $pdo->prepare("
            SELECT l.*, 
                   (SELECT COALESCE(SUM(amount), 0) FROM repayments WHERE loan_id = l.id) AS amount_paid,
                   (l.amount - (SELECT COALESCE(SUM(amount), 0) FROM repayments WHERE loan_id = l.id)) AS amount_remaining
            FROM loans l
            WHERE l.id = ? AND l.student_id = ? AND l.university_id = ?
        ");
        $stmt->execute([$loan_id, $_SESSION['student_db_id'], $student['university_id']]);
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$loan) {
            $error = "Loan not found or you don't have permission to access it.";
            $loan_id = null;
        } elseif ($loan['status'] !== 'approved') {
            $error = "This loan is not eligible for payment. Current status: " . ucfirst($loan['status']);
            $loan_id = null;
        } elseif ($loan['amount_remaining'] <= 0) {
            $error = "This loan has already been fully repaid.";
            $loan_id = null;
        }
    }
    
    // Get all eligible loans for dropdown if no specific loan selected
    $stmt = $pdo->prepare("
        SELECT l.id, l.amount, l.created_at,
               (l.amount - (SELECT COALESCE(SUM(amount), 0) FROM repayments WHERE loan_id = l.id)) AS amount_remaining
        FROM loans l
        WHERE l.student_id = ? AND l.university_id = ? AND l.status = 'approved'
        AND (l.amount - (SELECT COALESCE(SUM(amount), 0) FROM repayments WHERE loan_id = l.id)) > 0
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$_SESSION['student_db_id'], $student['university_id']]);
    $eligible_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Make payment page error: " . $e->getMessage());
    $error = "Error loading payment data. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - UniFlow Student Loans</title>
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

        /* Responsive */
        @media (max-width: 768px) {
            .loan-amount {
                font-size: 1.4rem;
            }
            
            .payment-method-card {
                padding: 15px;
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
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <h1 class="mb-4 text-center" style="background: linear-gradient(135deg, #ffffff, #e0e7ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                    <i class="fas fa-credit-card me-3"></i> Make Payment
                </h1>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success mb-4">
                        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                        <div class="mt-3">
                            <a href="loans.php" class="btn btn-outline-custom btn-sm me-2">
                                <i class="fas fa-list me-1"></i> View All Loans
                            </a>
                            <button type="button" class="btn btn-primary-custom btn-sm" onclick="location.reload()">
                                <i class="fas fa-plus me-1"></i> Make Another Payment
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($eligible_loans)): ?>
                    <div class="text-center py-5 glass">
                        <i class="fas fa-info-circle fa-4x mb-4" style="background: linear-gradient(135deg, #FF6B6B, #4ECDC4); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                        <h3 class="mb-3">No Eligible Loans</h3>
                        <p class="mb-4">You don't have any active loans that require payment at this time.</p>
                        <a href="loans.php" class="btn btn-primary-custom">
                            <i class="fas fa-list me-2"></i> View All Loans
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Loan Summary (if specific loan selected) -->
                    <?php if ($loan): ?>
                        <div class="loan-summary glass mb-4">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <h5 class="mb-2">
                                        <i class="fas fa-file-invoice-dollar me-2"></i>
                                        Loan #<?php echo htmlspecialchars($loan['id']); ?>
                                    </h5>
                                    <p class="text-muted mb-0">
                                        Applied: <?php echo date('M j, Y', strtotime($loan['created_at'])); ?>
                                    </p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="loan-amount">P<?php echo number_format($loan['amount'], 2); ?></div>
                                    <small class="text-muted">Original Amount</small>
                                </div>
                                <div class="col-md-4 text-end">
                                    <h6 class="mb-1">Remaining Balance</h6>
                                    <div class="loan-amount">P<?php echo number_format($loan['amount_remaining'], 2); ?></div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Paid: P<?php echo number_format($loan['amount_paid'], 2); ?></small>
                                    <small><?php echo number_format(($loan['amount_paid'] / $loan['amount']) * 100, 1); ?>% Complete</small>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?php echo ($loan['amount_paid'] / $loan['amount']) * 100; ?>%" 
                                         aria-valuenow="<?php echo ($loan['amount_paid'] / $loan['amount']) * 100; ?>" 
                                         aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Payment Form -->
                    <div class="glass p-4">
                        <form method="POST" id="paymentForm">
                            <!-- Loan Selection (if no specific loan) -->
                            <?php if (!$loan): ?>
                                <div class="mb-4">
                                    <label for="selected_loan" class="form-label">
                                        <i class="fas fa-file-invoice me-2"></i> Select Loan to Pay
                                    </label>
                                    <select class="form-select" id="selected_loan" name="selected_loan" required onchange="updateLoanDetails()">
                                        <option value="">Choose a loan...</option>
                                        <?php foreach ($eligible_loans as $eligible_loan): ?>
                                            <option value="<?php echo $eligible_loan['id']; ?>" 
                                                    data-remaining="<?php echo $eligible_loan['amount_remaining']; ?>"
                                                    data-total="<?php echo $eligible_loan['amount']; ?>">
                                                Loan #<?php echo $eligible_loan['id']; ?> - 
                                                P<?php echo number_format($eligible_loan['amount_remaining'], 2); ?> remaining
                                                (Applied: <?php echo date('M j, Y', strtotime($eligible_loan['created_at'])); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Selected Loan Details -->
                                <div id="selectedLoanDetails" class="loan-summary glass mb-4" style="display: none;">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h6 class="mb-1">Selected Loan</h6>
                                            <div id="selectedLoanId" class="loan-amount"></div>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <h6 class="mb-1">Remaining Balance</h6>
                                            <div id="selectedLoanRemaining" class="loan-amount"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="selected_loan" value="<?php echo $loan['id']; ?>">
                            <?php endif; ?>
                            
                            <!-- Payment Amount -->
                            <div class="mb-4">
                                <label for="payment_amount" class="form-label">
                                    <i class="fas fa-money-bill-wave me-2"></i> Payment Amount
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">P</span>
                                    <input type="number" class="form-control" id="payment_amount" name="payment_amount" 
                                           step="0.01" min="1" 
                                           <?php if ($loan): ?>max="<?php echo $loan['amount_remaining']; ?>"<?php endif; ?>
                                           placeholder="Enter payment amount" required 
                                           value="<?php echo isset($_POST['payment_amount']) ? htmlspecialchars($_POST['payment_amount']) : ''; ?>">
                                </div>
                                <div class="form-text text-muted mt-2">
                                    <?php if ($loan): ?>
                                        Maximum payment: P<?php echo number_format($loan['amount_remaining'], 2); ?>
                                    <?php else: ?>
                                        <span id="maxPaymentText" style="display: none;">Maximum payment: <span id="maxPaymentAmount"></span></span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Quick Amount Buttons -->
                                <div class="mt-3" id="quickAmountButtons" <?php if (!$loan): ?>style="display: none;"<?php endif; ?>>
                                    <small class="text-muted d-block mb-2">Quick amounts:</small>
                                    <div class="btn-group flex-wrap" role="group" id="quickAmountButtonsContainer">
                                        <?php if ($loan): ?>
                                            <?php
                                            $remaining = $loan['amount_remaining'];
                                            $quick_amounts = [
                                                round($remaining * 0.25, 2),
                                                round($remaining * 0.5, 2),
                                                round($remaining * 0.75, 2),
                                                $remaining
                                            ];
                                            ?>
                                            <?php foreach ($quick_amounts as $amount): ?>
                                                <button type="button" class="btn btn-outline-custom btn-sm me-2 mb-2" 
                                                        onclick="setPaymentAmount(<?php echo $amount; ?>)">
                                                    P<?php echo number_format($amount, 2); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Method -->
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-credit-card me-2"></i> Payment Method
                                </label>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="payment-method-card" onclick="selectPaymentMethod('mobile_money')">
                                            <input type="radio" name="payment_method" value="mobile_money" id="mobile_money" 
                                                   <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'mobile_money') ? 'checked' : ''; ?>>
                                            <div class="text-center">
                                                <i class="fas fa-mobile-alt payment-method-icon"></i>
                                                <h6 class="mb-1">Mobile Money</h6>
                                                <small class="text-muted">Orange Money, Mascom MyZaka</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="payment-method-card" onclick="selectPaymentMethod('bank_transfer')">
                                            <input type="radio" name="payment_method" value="bank_transfer" id="bank_transfer"
                                                   <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'bank_transfer') ? 'checked' : ''; ?>>
                                            <div class="text-center">
                                                <i class="fas fa-university payment-method-icon"></i>
                                                <h6 class="mb-1">Bank Transfer</h6>
                                                <small class="text-muted">Direct bank deposit</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="payment-method-card" onclick="selectPaymentMethod('auto_debit')">
                                            <input type="radio" name="payment_method" value="auto_debit" id="auto_debit"
                                                   <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'auto_debit') ? 'checked' : ''; ?>>
                                            <div class="text-center">
                                                <i class="fas fa-sync-alt payment-method-icon"></i>
                                                <h6 class="mb-1">Auto Debit</h6>
                                                <small class="text-muted">Automatic deduction</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Transaction Reference -->
                            <div class="mb-4">
                                <label for="transaction_reference" class="form-label">
                                    <i class="fas fa-receipt me-2"></i> Transaction Reference
                                </label>
                                                               <input type="text" class="form-control" id="transaction_reference" name="transaction_reference" 
                                       placeholder="Enter transaction reference number" required
                                       value="<?php echo isset($_POST['transaction_reference']) ? htmlspecialchars($_POST['transaction_reference']) : ''; ?>">
                                <div class="form-text text-muted mt-2">
                                    Please enter the reference number from your payment confirmation.
                                </div>
                            </div>
                            
                            <!-- Student Account Info -->
                            <div class="mb-4 glass-dark p-3 rounded">
                                <h6 class="mb-3"><i class="fas fa-user-circle me-2"></i> Your Account Information</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></p>
                                        <p class="mb-1"><strong>Student ID:</strong> <?php echo htmlspecialchars($student['student_id']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>University:</strong> <?php echo htmlspecialchars($student['university_name']); ?></p>
                                        <?php if (!empty($student['fnb_account_number'])): ?>
                                            <p class="mb-1"><strong>FNB Account:</strong> <?php echo htmlspecialchars($student['fnb_account_number']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Terms and Conditions -->
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="terms_agree" required>
                                <label class="form-check-label" for="terms_agree">
                                    I confirm that this payment information is accurate and I authorize UniFlow to record this payment.
                                </label>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary-custom btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i> Submit Payment
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-center py-4 mt-5">
        <div class="container">
            <p class="mb-0 text-muted">
                &copy; <?php echo date('Y'); ?> UniFlow Student Loans. All rights reserved.
            </p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Select payment method card when clicked
        function selectPaymentMethod(methodId) {
            document.getElementById(methodId).checked = true;
            
            // Update card styling
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            document.querySelector(`.payment-method-card[onclick="selectPaymentMethod('${methodId}')"]`)
                .classList.add('selected');
        }
        
        // Set payment amount from quick buttons
        function setPaymentAmount(amount) {
            document.getElementById('payment_amount').value = amount.toFixed(2);
        }
        
        // Update loan details when a loan is selected from dropdown
        function updateLoanDetails() {
            const select = document.getElementById('selected_loan');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.value) {
                const remaining = parseFloat(selectedOption.dataset.remaining);
                const total = parseFloat(selectedOption.dataset.total);
                const paid = total - remaining;
                const percentPaid = (paid / total) * 100;
                
                // Update displayed loan details
                document.getElementById('selectedLoanId').textContent = `Loan #${selectedOption.value}`;
                document.getElementById('selectedLoanRemaining').textContent = `P${remaining.toFixed(2)}`;
                
                // Update max payment amount
                document.getElementById('payment_amount').max = remaining;
                document.getElementById('maxPaymentAmount').textContent = `P${remaining.toFixed(2)}`;
                document.getElementById('maxPaymentText').style.display = 'block';
                
                // Update quick amount buttons
                const quickAmounts = [
                    Math.round(remaining * 0.25 * 100) / 100,
                    Math.round(remaining * 0.5 * 100) / 100,
                    Math.round(remaining * 0.75 * 100) / 100,
                    remaining
                ];
                
                const buttonsContainer = document.getElementById('quickAmountButtonsContainer');
                buttonsContainer.innerHTML = '';
                
                quickAmounts.forEach(amount => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-outline-custom btn-sm me-2 mb-2';
                    button.textContent = `P${amount.toFixed(2)}`;
                    button.onclick = () => setPaymentAmount(amount);
                    buttonsContainer.appendChild(button);
                });
                
                // Show the loan details and quick buttons
                document.getElementById('selectedLoanDetails').style.display = 'block';
                document.getElementById('quickAmountButtons').style.display = 'block';
            } else {
                // Hide if no loan selected
                document.getElementById('selectedLoanDetails').style.display = 'none';
                document.getElementById('quickAmountButtons').style.display = 'none';
                document.getElementById('maxPaymentText').style.display = 'none';
            }
        }
        
        // Initialize selected payment method styling
        document.addEventListener('DOMContentLoaded', function() {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            if (selectedMethod) {
                selectPaymentMethod(selectedMethod.id);
            }
        });
    </script>
</body>
</html>