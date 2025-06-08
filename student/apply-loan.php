<?php
// Enhanced session configuration
session_start([
    'use_strict_mode' => true,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict'
]);

// Define root path properly
define('ROOT_PATH', realpath(dirname(__FILE__) . '/..'));

// Load configuration and database connection
require_once(ROOT_PATH . '/config.php');
require_once(ROOT_PATH . '/dbconnection.php');

// Check if user is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$errors = [];
$success_message = '';
$student_id = $_SESSION['student_db_id']; // Using the database ID instead of student number
$student_data = null;
$recent_loans = [];

try {
    // Get database connection
    $pdo = DatabaseConnection::getInstance()->getConnection();
    
    // Get comprehensive student information with financial profile
    $stmt = $pdo->prepare("
        SELECT 
            s.id, s.student_id, s.full_name, s.created_at, s.fnb_account_number, s.university_id,
            sfp.ai_risk_score, sfp.dynamic_loan_limit, sfp.allowance_day, 
            sfp.emergency_topup_unlocked, sfp.last_loan_date,
            u.name as university_name, u.allowance_schedule
        FROM students s
        LEFT JOIN student_financial_profiles sfp ON s.id = sfp.student_id
        JOIN universities u ON s.university_id = u.id
        WHERE s.id = ? AND s.is_verified = 1
    ");
    $stmt->execute([$student_id]);
    $student_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student_data) {
        throw new Exception("Student not found or account not verified");
    }
    
    // Create financial profile if missing
    if (is_null($student_data['ai_risk_score'])) {
        $stmt = $pdo->prepare("
            INSERT INTO student_financial_profiles 
            (student_id, university_id, ai_risk_score, dynamic_loan_limit, allowance_day, emergency_topup_unlocked)
            VALUES (?, ?, 50.00, 500.00, 25, 0)
        ");
        $stmt->execute([$student_id, $student_data['university_id']]);
        
        // Refresh student data with new financial profile
        $stmt = $pdo->prepare("
            SELECT 
                s.id, s.student_id, s.full_name, s.created_at, s.fnb_account_number, s.university_id,
                sfp.ai_risk_score, sfp.dynamic_loan_limit, sfp.allowance_day, 
                sfp.emergency_topup_unlocked, sfp.last_loan_date,
                u.name as university_name, u.allowance_schedule
            FROM students s
            LEFT JOIN student_financial_profiles sfp ON s.id = sfp.student_id
            JOIN universities u ON s.university_id = u.id
            WHERE s.id = ? AND s.is_verified = 1
        ");
        $stmt->execute([$student_id]);
        $student_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Check for pending loans
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_count 
        FROM loans 
        WHERE student_id = ? AND status = 'pending'
    ");
    $stmt->execute([$student_id]);
    $pending_loans = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pending_loans['pending_count'] > 0) {
        $errors[] = "You have a pending loan application. Please wait for approval before applying for another loan.";
    }
    
    // Check daily loan limit
    if ($student_data['last_loan_date'] === date('Y-m-d')) {
        $errors[] = "You can only apply for one loan per day.";
    }
    
    // Get recent loan history (remove references to non-existent columns)
    $stmt = $pdo->prepare("
        SELECT 
            id, amount, status, created_at, disbursement_date, due_date
        FROM loans 
        WHERE student_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$student_id]);
    $recent_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $errors[] = "Database connection error. Please try again later.";
} catch (Exception $e) {
    error_log("Application error: " . $e->getMessage());
    $errors[] = $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $repayment_method = filter_input(INPUT_POST, 'repayment_method', FILTER_SANITIZE_STRING);
    $is_emergency = isset($_POST['is_emergency']) ? 1 : 0;
    $loan_purpose = filter_input(INPUT_POST, 'loan_purpose', FILTER_SANITIZE_STRING);
    
    // Validation
    if (!$amount || $amount <= 0) {
        $errors[] = "Please enter a valid loan amount.";
    } elseif ($amount > $student_data['dynamic_loan_limit']) {
        $errors[] = "Loan amount exceeds your limit of P" . number_format($student_data['dynamic_loan_limit'], 2);
    }
    
    if (!in_array($repayment_method, ['auto', 'manual'])) {
        $errors[] = "Please select a valid repayment method.";
    }
    
    if ($is_emergency && !$student_data['emergency_topup_unlocked']) {
        $errors[] = "Emergency loan feature is not available for your account.";
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Calculate AI approval confidence
            $ai_confidence = calculateAIApprovalConfidence($student_data, $amount);
            
            // Determine auto-approval threshold
            $auto_approval_threshold = 75.0;
            $status = ($ai_confidence >= $auto_approval_threshold) ? 'approved' : 'pending';
            
            // Calculate due date (30 days from disbursement for approved loans)
            $due_date = null;
            $disbursement_date = null;
            
            if ($status === 'approved') {
                $disbursement_date = date('Y-m-d H:i:s');
                $due_date = date('Y-m-d', strtotime('+30 days'));
            }
            
            // Insert loan application (remove non-existent loan_purpose column)
            $stmt = $pdo->prepare("
                INSERT INTO loans (
                    student_id, university_id, amount, interest_rate, status,
                    disbursement_date, due_date, repayment_method, is_emergency_topup,
                    ai_approval_confidence, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $student_id,
                $student_data['university_id'],
                $amount,
                5.00, // Default interest rate
                $status,
                $disbursement_date,
                $due_date,
                $repayment_method,
                $is_emergency,
                $ai_confidence
            ]);
            
            // Update student's last loan date and risk score
            $new_risk_score = updateRiskScore($student_data['ai_risk_score'], $amount, $ai_confidence);
            
            $stmt = $pdo->prepare("
                UPDATE student_financial_profiles 
                SET last_loan_date = CURDATE(), ai_risk_score = ?
                WHERE student_id = ?
            ");
            $stmt->execute([$new_risk_score, $student_id]);
            
            $pdo->commit();
            
            if ($status === 'approved') {
                $success_message = "Congratulations! Your loan of P" . number_format($amount, 2) . " has been approved and will be disbursed shortly.";
            } else {
                $success_message = "Your loan application has been submitted successfully. You will receive a notification once it's processed.";
            }
            
            // Refresh recent loans after successful application
            $stmt = $pdo->prepare("
                SELECT 
                    id, amount, status, created_at, disbursement_date, due_date
                FROM loans 
                WHERE student_id = ? 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute([$student_id]);
            $recent_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error processing loan application: " . $e->getMessage();
            error_log("Loan application error: " . $e->getMessage());
        }
    }
}

/**
 * Calculates AI approval confidence based on student profile and loan amount
 */
function calculateAIApprovalConfidence($student_data, $amount) {
    $base_score = 50;
    
    // Risk score factor (lower risk = higher confidence)
    $risk_factor = (100 - $student_data['ai_risk_score']) * 0.3;
    
    // Amount factor (lower amount relative to limit = higher confidence)
    $amount_ratio = $student_data['dynamic_loan_limit'] > 0 ? 
        $amount / $student_data['dynamic_loan_limit'] : 1;
    $amount_factor = (1 - $amount_ratio) * 30;
    
    // Account age factor (assuming newer accounts are riskier)
    $account_age_days = (strtotime('now') - strtotime($student_data['created_at'])) / (60 * 60 * 24);
    $age_factor = min($account_age_days / 30, 1) * 20; // Max 20% boost for accounts older than 30 days
    
    $confidence = $base_score + $risk_factor + $amount_factor + $age_factor;
    
    return min(max($confidence, 0), 100); // Clamp between 0 and 100
}

/**
 * Updates risk score based on current loan application
 */
function updateRiskScore($current_score, $amount, $ai_confidence) {
    $current_score = floatval($current_score);
    
    // Increase risk slightly with each loan application
    $risk_increase = ($amount / 1000) * 2; // 2% increase per P1000
    
    // Adjust based on AI confidence
    if ($ai_confidence > 80) {
        $risk_increase *= 0.5; // Reduce risk increase for high-confidence applications
    } elseif ($ai_confidence < 50) {
        $risk_increase *= 1.5; // Increase risk more for low-confidence applications
    }
    
    return min($current_score + $risk_increase, 100);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Loan - UniFlow Student Loans</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0a1628 0%, #1e3a5f 100%);
            color: white;
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-color: #FF6B6B;
            box-shadow: 0 0 0 0.25rem rgba(255, 107, 107, 0.25);
        }

        .form-select option {
            background: #1e3a5f;
            color: white;
        }

        .risk-meter {
            height: 10px;
            background: linear-gradient(90deg, #28a745 0%, #ffc107 50%, #dc3545 100%);
            border-radius: 5px;
            position: relative;
        }

        .risk-indicator {
            position: absolute;
            top: -3px;
            width: 16px;
            height: 16px;
            background: white;
            border-radius: 50%;
            border: 2px solid #333;
            transform: translateX(-50%);
        }

        .btn-primary {
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
            border: none;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #FF8E53, #FF6B6B);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 107, 107, 0.3);
        }

        .text-gradient {
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .alert {
            border: none;
        }

        .badge {
            font-size: 0.75em;
        }

        .form-text {
            color: rgba(255, 255, 255, 0.7);
        }

        .input-group-text {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
        }

        .progress {
            background: rgba(255, 255, 255, 0.1);
        }

        .progress-bar {
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include(ROOT_PATH . '/student/navbar.php'); ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Success/Error Messages -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger glass-card mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success glass-card mb-4">
                        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Only show the form if we have valid student data -->
                <?php if ($student_data): ?>
                <!-- Loan Application Form -->
                <div class="glass-card p-4 mb-4">
                    <h2 class="mb-4 text-center text-gradient">
                        <i class="fas fa-hand-holding-usd me-2"></i> Apply for Loan
                    </h2>
                    
                    <form method="POST" id="loanForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="amount" class="form-label">Loan Amount (Pula)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">P</span>
                                        <input type="number" class="form-control" id="amount" name="amount" 
                                               min="50" max="<?php echo $student_data['dynamic_loan_limit']; ?>" 
                                               step="10" required>
                                    </div>
                                    <div class="form-text">
                                        Maximum: P<?php echo number_format($student_data['dynamic_loan_limit'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="repayment_method" class="form-label">Repayment Method</label>
                                    <select class="form-select" id="repayment_method" name="repayment_method" required>
                                        <option value="">Select method</option>
                                        <option value="auto">Auto-debit from allowance</option>
                                        <option value="manual">Manual repayment</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="loan_purpose" class="form-label">Loan Purpose</label>
                            <select class="form-select" id="loan_purpose" name="loan_purpose" required>
                                <option value="">Select purpose</option>
                                <option value="textbooks">Textbooks & Study Materials</option>
                                <option value="accommodation">Accommodation</option>
                                <option value="food">Food & Groceries</option>
                                <option value="transport">Transportation</option>
                                <option value="medical">Medical Emergency</option>
                                <option value="technology">Technology & Equipment</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <?php if ($student_data['emergency_topup_unlocked']): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_emergency" name="is_emergency">
                                <label class="form-check-label" for="is_emergency">
                                    <i class="fas fa-bolt me-1 text-warning"></i> Emergency Top-up
                                </label>
                                <small class="d-block text-muted">Faster processing for urgent needs</small>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="alert alert-info glass-card mt-4">
                            <h6><i class="fas fa-info-circle me-2"></i>Loan Terms:</h6>
                            <ul class="mb-0 small">
                                <li>Interest Rate: 5% per annum</li>
                                <li>Repayment Period: 30 days</li>
                                <li>Late Payment Fee: P50</li>
                                <li>Processing Time: 24-48 hours (or instant for qualified applications)</li>
                            </ul>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary py-3">
                                <i class="fas fa-paper-plane me-2"></i> Submit Application
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($student_data): ?>
            <div class="col-lg-4">
                <!-- Financial Profile Card -->
                <div class="glass-card p-4 mb-4">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-line me-2"></i> Financial Profile
                    </h5>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small">Available Loan Limit</span>
                            <span class="fw-bold">P<?php echo number_format($student_data['dynamic_loan_limit'], 2); ?></span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar" 
                                 style="width: <?php echo min(($student_data['dynamic_loan_limit']/1000)*100, 100); ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small">Risk Score</span>
                            <span class="fw-bold"><?php echo number_format($student_data['ai_risk_score'], 1); ?>%</span>
                        </div>
                        <div class="risk-meter">
                            <div class="risk-indicator" style="left: <?php echo $student_data['ai_risk_score']; ?>%;"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-success">Low Risk</small>
                            <small class="text-warning">Medium</small>
                            <small class="text-danger">High Risk</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span class="small">Next Allowance</span>
                            <span>Day <?php echo $student_data['allowance_day']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="small">Schedule</span>
                            <span><?php echo ucfirst($student_data['allowance_schedule']); ?></span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span class="small">Bank Account</span>
                        <span><?php echo $student_data['fnb_account_number'] ?: 'Not set'; ?></span>
                    </div>

                    <?php if ($student_data['emergency_topup_unlocked']): ?>
                    <div class="alert alert-success glass-card py-2 mt-3">
                        <small><i class="fas fa-check me-1"></i> Emergency loans available</small>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Loans -->
                <?php if (!empty($recent_loans)): ?>
                <div class="glass-card p-4">
                    <h5 class="mb-3">
                        <i class="fas fa-history me-2"></i> Recent Loans
                    </h5>
                    
                    <?php foreach ($recent_loans as $loan): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-secondary">
                        <div>
                            <div class="fw-bold">P<?php echo number_format($loan['amount'], 2); ?></div>
                            <small class="text-muted"><?php echo date('M j, Y', strtotime($loan['created_at'])); ?></small>
                        </div>
                        <span class="badge rounded-pill bg-<?php 
                            echo $loan['status'] === 'approved' ? 'success' : 
                                 ($loan['status'] === 'pending' ? 'warning' : 'secondary'); 
                        ?>">
                            <?php echo ucfirst($loan['status']); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-3">
                        <a href="loans.php" class="btn btn-sm btn-outline-light">
                            View All Loans <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Include Footer -->
    <?php include(ROOT_PATH . '/student/footer.php'); ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time loan amount validation
        document.getElementById('amount')?.addEventListener('input', function() {
            const amount = parseFloat(this.value);
            const maxAmount = <?php echo $student_data['dynamic_loan_limit'] ?? 0; ?>;
            
            if (amount > maxAmount) {
                this.setCustomValidity(`Amount exceeds your limit of P${maxAmount.toFixed(2)}`);
                this.classList.add('is-invalid');
            } else if (amount < 50) {
                this.setCustomValidity('Minimum loan amount is P50');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
            }
        });

        // Form submission confirmation
        document.getElementById('loanForm')?.addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('amount').value);
            const purpose = document.getElementById('loan_purpose').options[document.getElementById('loan_purpose').selectedIndex].text;
            
            if (!confirm(`Confirm loan application:\n\nAmount: P${amount.toFixed(2)}\nPurpose: ${purpose}\n\nProceed?`)) {
                e.preventDefault();
            }
        });

        // Enhanced form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loanForm');
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
    </script>
</body>
</html>