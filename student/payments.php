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
$student = [];
$activeLoans = [];
$paymentMethods = [];
$selectedLoan = null;

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
    
    // Get active loans with remaining balance
    $stmt = $pdo->prepare("
        SELECT l.id, l.amount, 
               (SELECT COALESCE(SUM(amount), 0) FROM repayments WHERE loan_id = l.id) AS amount_paid,
               (l.amount - (SELECT COALESCE(SUM(amount), 0) FROM repayments WHERE loan_id = l.id)) AS amount_remaining,
               l.due_date
        FROM loans l
        WHERE l.student_id = ? AND l.university_id = ? 
        AND l.status = 'approved'
        AND (l.amount - (SELECT COALESCE(SUM(amount), 0) FROM repayments WHERE loan_id = l.id)) > 0
        ORDER BY l.due_date ASC
    ");
    $stmt->execute([$_SESSION['student_db_id'], $student['university_id']]);
    $activeLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment methods (simplified for demo)
    $paymentMethods = [
        ['id' => 'mm_mobile', 'name' => 'Mobile Money', 'icon' => 'fa-mobile-screen'],
        ['id' => 'bank_transfer', 'name' => 'Bank Transfer', 'icon' => 'fa-building-columns'],
        ['id' => 'fnb_direct', 'name' => 'FNB Account', 'icon' => 'fa-landmark']
    ];
    
    // Process payment if form submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $loanId = filter_input(INPUT_POST, 'loan_id', FILTER_VALIDATE_INT);
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
        $reference = filter_input(INPUT_POST, 'reference', FILTER_SANITIZE_STRING);
        
        // Validate input
        if (!$loanId) {
            $error = "Please select a valid loan";
        } elseif (!$amount || $amount <= 0) {
            $error = "Please enter a valid payment amount";
        } elseif (!$method || !in_array($method, array_column($paymentMethods, 'id'))) {
            $error = "Please select a valid payment method";
        } else {
            // Get loan details for validation
            $stmt = $pdo->prepare("
                SELECT amount, 
                       (SELECT COALESCE(SUM(amount), 0) FROM repayments WHERE loan_id = ?) AS amount_paid,
                       (amount - (SELECT COALESCE(SUM(amount), 0) FROM repayments WHERE loan_id = ?)) AS amount_remaining
                FROM loans 
                WHERE id = ? AND student_id = ?
            ");
            $stmt->execute([$loanId, $loanId, $loanId, $_SESSION['student_db_id']]);
            $selectedLoan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$selectedLoan) {
                $error = "Selected loan not found";
            } elseif ($amount > $selectedLoan['amount_remaining']) {
                $error = "Payment amount cannot exceed remaining balance of P" . number_format($selectedLoan['amount_remaining'], 2);
            } else {
                // Process payment (simplified - in real app this would connect to payment gateway)
                $pdo->beginTransaction();
                
                try {
                    // Insert repayment record
                    $stmt = $pdo->prepare("
                        INSERT INTO repayments (
                            loan_id, university_id, amount, method, 
                            transaction_reference, is_partial, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $loanId,
                        $student['university_id'],
                        $amount,
                        str_replace('_', ' ', $method),
                        $reference,
                        ($amount < $selectedLoan['amount_remaining']) ? 1 : 0
                    ]);
                    
                    // Check if loan is now fully paid
                    if ($amount == $selectedLoan['amount_remaining']) {
                        $stmt = $pdo->prepare("
                            UPDATE loans SET status = 'repaid', status_updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$loanId]);
                    }
                    
                    $pdo->commit();
                    
                    // Generate a fake transaction reference if none provided
                    if (empty($reference)) {
                        $reference = 'UNI-' . strtoupper(substr($method, 0, 3)) . '-' . time();
                    }
                    
                    $success = "Payment of P" . number_format($amount, 2) . " processed successfully!";
                    $success .= "<br>Transaction Reference: <strong>$reference</strong>";
                    
                    // Refresh active loans list
                    $stmt = $pdo->prepare("
                        SELECT l.id, l.amount, 
                               (SELECT COALESCE(SUM(amount), 0) FROM repayments WHERE loan_id = l.id) AS amount_paid,
                               (l.amount - (SELECT COALESCE(SUM(amount), 0) FROM repayments WHERE loan_id = l.id)) AS amount_remaining,
                               l.due_date
                        FROM loans l
                        WHERE l.student_id = ? AND l.university_id = ? 
                        AND l.status = 'approved'
                        AND (l.amount - (SELECT COALESCE(SUM(amount), 0) FROM repayments WHERE loan_id = l.id)) > 0
                        ORDER BY l.due_date ASC
                    ");
                    $stmt->execute([$_SESSION['student_db_id'], $student['university_id']]);
                    $activeLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Payment processing failed: " . $e->getMessage();
                }
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Payment page error: " . $e->getMessage());
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

        /* Navigation */
        .navbar-custom {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem 0;
        }

        /* Form Styles */
        .form-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
        }

        .form-label {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
        }

        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 12px 15px;
            border-radius: 10px;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            color: white;
            box-shadow: 0 0 0 0.25rem rgba(255, 107, 107, 0.25);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        /* Loan Cards */
        .loan-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .loan-card:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-3px);
        }

        .loan-amount {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Payment Method Cards */
        .method-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .method-card:hover, .method-card.active {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .method-card.active {
            border: 2px solid #4ECDC4;
        }

        .method-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 15px;
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

        /* Progress Bar */
        .progress {
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        .progress-bar {
            background: linear-gradient(90deg, #FF6B6B, #4ECDC4);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .form-container {
                padding: 20px;
            }
            
            .loan-amount {
                font-size: 1.2rem;
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
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Success/Error Messages -->
                <?php if ($error): ?>
                    <div class="alert alert-danger glass mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success glass mb-4">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-container glass">
                    <h2 class="mb-4 text-center" style="background: linear-gradient(135deg, #ffffff, #e0e7ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                        <i class="fas fa-money-bill-wave me-2"></i> Make Payment
                    </h2>
                    
                    <?php if (empty($activeLoans)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-4x mb-3" style="color: #4ECDC4;"></i>
                            <h4 class="mb-3">No Active Loans</h4>
                            <p class="mb-4">You don't have any loans requiring payment at this time.</p>
                            <a href="loans.php" class="btn btn-primary-custom">
                                <i class="fas fa-file-invoice-dollar me-2"></i> View Loan History
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="POST" id="paymentForm">
                            <!-- Loan Selection -->
                            <div class="mb-4">
                                <label class="form-label">Select Loan to Pay</label>
                                <?php foreach ($activeLoans as $loan): ?>
                                    <div class="loan-card">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="loan_id" 
                                                   id="loan_<?php echo $loan['id']; ?>" 
                                                   value="<?php echo $loan['id']; ?>"
                                                   required>
                                            <label class="form-check-label w-100" for="loan_<?php echo $loan['id']; ?>">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h5 class="mb-1">Loan #<?php echo $loan['id']; ?></h5>
                                                        <small class="text-muted">Due: <?php echo date('M j, Y', strtotime($loan['due_date'])); ?></small>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="loan-amount">P<?php echo number_format($loan['amount_remaining'], 2); ?></div>
                                                        <small class="text-muted">Remaining</small>
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <div class="d-flex justify-content-between small text-muted mb-1">
                                                        <span>Paid: P<?php echo number_format($loan['amount_paid'], 2); ?></span>
                                                        <span>Total: P<?php echo number_format($loan['amount'], 2); ?></span>
                                                    </div>
                                                    <div class="progress">
                                                        <div class="progress-bar" role="progressbar" 
                                                             style="width: <?php echo ($loan['amount_paid'] / $loan['amount']) * 100; ?>%" 
                                                             aria-valuenow="<?php echo ($loan['amount_paid'] / $loan['amount']) * 100; ?>" 
                                                             aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Amount -->
                            <div class="mb-4">
                                <label for="amount" class="form-label">Payment Amount (Pula)</label>
                                <div class="input-group">
                                    <span class="input-group-text">P</span>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           min="50" step="10" required>
                                </div>
                                <div class="form-text">Minimum payment: P50</div>
                            </div>
                            
                            <!-- Payment Method -->
                            <div class="mb-4">
                                <label class="form-label">Payment Method</label>
                                <div class="row">
                                    <?php foreach ($paymentMethods as $method): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="method-card" onclick="selectMethod('<?php echo $method['id']; ?>')">
                                                <div class="d-flex align-items-center">
                                                    <div class="method-icon">
                                                        <i class="fas <?php echo $method['icon']; ?>"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo $method['name']; ?></h6>
                                                        <small class="text-muted">
                                                            <?php if ($method['id'] === 'fnb_direct'): ?>
                                                                Instant transfer
                                                            <?php elseif ($method['id'] === 'mm_mobile'): ?>
                                                                1-3 business days
                                                            <?php else: ?>
                                                                3-5 business days
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <input type="radio" name="payment_method" 
                                                       id="method_<?php echo $method['id']; ?>" 
                                                       value="<?php echo $method['id']; ?>" 
                                                       class="d-none" required>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Reference Number -->
                            <div class="mb-4">
                                <label for="reference" class="form-label">Transaction Reference (Optional)</label>
                                <input type="text" class="form-control" id="reference" name="reference" 
                                       placeholder="Enter your payment reference number">
                            </div>
                            
                            <!-- Terms -->
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                                    <label class="form-check-label" for="agreeTerms">
                                        I confirm this payment information is correct
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Submit -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary-custom">
                                    <i class="fas fa-paper-plane me-2"></i> Submit Payment
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include Footer -->
    <?php include($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/student/footer.php'); ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Select payment method visually
        function selectMethod(methodId) {
            document.querySelectorAll('.method-card').forEach(card => {
                card.classList.remove('active');
            });
            document.getElementById('method_' + methodId).checked = true;
            document.querySelector(`.method-card[onclick="selectMethod('${methodId}')"]`).classList.add('active');
        }
        
        // Set max amount when loan is selected
        document.querySelectorAll('input[name="loan_id"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    const loanCard = this.closest('.loan-card');
                    const remainingAmount = loanCard.querySelector('.loan-amount').textContent.replace('P', '').replace(',', '');
                    document.getElementById('amount').max = remainingAmount;
                }
            });
        });
        
        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('amount').value);
            const method = document.querySelector('input[name="payment_method"]:checked');
            
            if (!method) {
                alert('Please select a payment method');
                e.preventDefault();
                return;
            }
            
            if (amount < 50) {
                alert('Minimum payment amount is P50');
                e.preventDefault();
                return;
            }
            
            if (!confirm(`Confirm payment of P${amount.toFixed(2)} via ${method.value.replace('_', ' ')}?`)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>