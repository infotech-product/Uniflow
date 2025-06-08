<?php
// Enhanced session configuration with error handling
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

$student_id = $_SESSION['student_id'];

// Get database connection
$pdo = DatabaseConnection::getInstance()->getConnection();

// Get student's allowance information
$allowance = [];
try {
    $stmt = $pdo->prepare("SELECT amount, disbursement_date 
                          FROM allowance_verification_logs 
                          WHERE student_id = ? 
                          ORDER BY disbursement_date DESC 
                          LIMIT 1");
    $stmt->execute([$student_id]);
    $allowance = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching allowance data: " . $e->getMessage();
}

// Get active loans and repayments
$loans = [];
try {
    $stmt = $pdo->prepare("SELECT l.id, l.amount, l.amount_paid, l.due_date, l.interest_rate,
                          (SELECT SUM(amount) FROM repayments WHERE loan_id = l.id) AS total_repaid
                          FROM loans l
                          WHERE l.student_id = ? AND l.status = 'approved'
                          ORDER BY l.due_date ASC");
    $stmt->execute([$student_id]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching loan data: " . $e->getMessage();
}

// Calculate monthly financials
$monthly_allowance = $allowance['amount'] ?? 0;
$total_loan_balance = 0;
$monthly_loan_payments = 0;
$current_date = new DateTime();
$current_month = $current_date->format('Y-m');

foreach ($loans as &$loan) {
    $loan_balance = $loan['amount'] - ($loan['amount_paid'] ?? 0);
    $total_loan_balance += $loan_balance;
    
    // Calculate monthly payment (simple equal distribution)
    $due_date = new DateTime($loan['due_date']);
    $months_remaining = $current_date->diff($due_date)->m + ($current_date->diff($due_date)->y * 12);
    $months_remaining = max(1, $months_remaining); // At least 1 month
        
    $loan['monthly_payment'] = $loan_balance / $months_remaining;
    $monthly_loan_payments += $loan['monthly_payment'];
}

// Budget recommendation percentages (can be adjusted)
$budget_categories = [
    'loan_repayments' => 30,  // Max 30% of allowance to loans
    'accommodation' => 35,
    'food' => 20,
    'transport' => 10,
    'books_supplies' => 15,
    'personal' => 10,
    'savings' => 10
];

// Calculate recommended amounts
$recommendations = [];
foreach ($budget_categories as $category => $percentage) {
    if ($category == 'loan_repayments') {
        // Don't exceed the actual loan payments needed
        $recommendations[$category] = min(
            ($monthly_allowance * $percentage / 100),
            $monthly_loan_payments
        );
    } else {
        $recommendations[$category] = $monthly_allowance * $percentage / 100;
    }
}

// Adjust other categories if loan payments are less than 30%
if ($monthly_loan_payments < ($monthly_allowance * 0.3)) {
    $extra_amount = ($monthly_allowance * 0.3) - $monthly_loan_payments;
    $recommendations['savings'] += $extra_amount * 0.5;
    $recommendations['personal'] += $extra_amount * 0.3;
    $recommendations['books_supplies'] += $extra_amount * 0.2;
}

// Get current month's spending from transactions (simplified example)
$current_spending = [
    'accommodation' => 0,
    'food' => 0,
    'transport' => 0,
    'personal' => 0
];

// Add some sample transactions (in a real app, this would come from a transactions table)
$sample_transactions = [
    ['category' => 'food', 'amount' => 250, 'date' => date('Y-m-05')],
    ['category' => 'transport', 'amount' => 100, 'date' => date('Y-m-10')],
    ['category' => 'food', 'amount' => 300, 'date' => date('Y-m-15')],
    ['category' => 'personal', 'amount' => 150, 'date' => date('Y-m-20')]
];

foreach ($sample_transactions as $transaction) {
    if (array_key_exists($transaction['category'], $current_spending)) {
        $current_spending[$transaction['category']] += $transaction['amount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Budget Planner | UniFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #ffffff, #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.2;
            position: absolute;
            right: 20px;
            top: 20px;
            color: white;
        }
        
        /* Welcome Section */
        .welcome-section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .welcome-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #ffffff, #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .welcome-subtitle {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 20px;
        }
        
        /* Recent Activity */
        .activity-item {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
            color: white;
        }
        
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }
        
        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
            border: none;
            padding: 15px 40px;
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
            padding: 15px 40px;
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

        .budget-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .budget-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .progress {
            height: 10px;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .progress-bar {
            background-color: #4ECDC4;
        }
        .category-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
        }
        .icon-accommodation { background-color: #6A8DFF; }
        .icon-food { background-color: #FF8E53; }
        .icon-transport { background-color: #A18CD1; }
        .icon-books { background-color: #4ECDC4; }
        .icon-personal { background-color: #FF6B6B; }
        .icon-savings { background-color: #58C9B9; }
        .icon-loans { background-color: #6C757D; }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .alert-recommendation {
            background: rgba(78, 205, 196, 0.1);
            border-left: 4px solid #4ECDC4;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/Uniflow/student/navbar.php'; ?>

    <div class="container py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">My Monthly Budget Planner</h2>
            <div class="btn-group">
                <button class="btn btn-outline-light" id="printBudget">
                    <i class="bi bi-printer me-2"></i>Print
                </button>
                <button class="btn btn-outline-light" id="exportBudget">
                    <i class="bi bi-download me-2"></i>Export
                </button>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger glass" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Financial Overview -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="budget-card glass">
                    <h5 class="text-muted mb-3">Monthly Allowance</h5>
                    <h3 class="fw-bold">P<?php echo number_format($monthly_allowance, 2); ?></h3>
                    <?php if ($allowance): ?>
                        <p class="text-muted mb-0">Next disbursement: <?php echo date('M d, Y', strtotime($allowance['disbursement_date'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="budget-card glass">
                    <h5 class="text-muted mb-3">Loan Payments Due</h5>
                    <h3 class="fw-bold">P<?php echo number_format($monthly_loan_payments, 2); ?></h3>
                    <p class="text-muted mb-0"><?php echo count($loans); ?> active loan(s)</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="budget-card glass">
                    <h5 class="text-muted mb-3">Remaining Balance</h5>
                    <h3 class="fw-bold">P<?php echo number_format($monthly_allowance - $monthly_loan_payments, 2); ?></h3>
                    <p class="text-muted mb-0">For other expenses</p>
                </div>
            </div>
        </div>

        <!-- Budget Recommendations -->
        <div class="budget-card glass mb-4">
            <h4 class="fw-bold mb-4"><i class="bi bi-lightbulb me-2"></i>Recommended Budget</h4>
            
            <div class="alert alert-recommendation mb-4">
                <i class="bi bi-info-circle-fill me-2"></i>
                Based on your allowance and loan obligations, here's how we recommend allocating your funds this month.
            </div>
            
            <div class="row">
                <!-- Recommended Budget Chart -->
                <div class="col-lg-6">
                    <div class="chart-container">
                        <canvas id="budgetChart"></canvas>
                    </div>
                </div>
                
                <!-- Budget Breakdown -->
                <div class="col-lg-6">
                    <?php foreach ($recommendations as $category => $amount): 
                        if ($amount <= 0) continue;
                        
                        $icon_class = '';
                        $category_name = '';
                        switch($category) {
                            case 'loan_repayments': 
                                $icon_class = 'icon-loans';
                                $category_name = 'Loan Repayments';
                                break;
                            case 'accommodation': 
                                $icon_class = 'icon-accommodation';
                                $category_name = 'Accommodation';
                                break;
                            case 'food': 
                                $icon_class = 'icon-food';
                                $category_name = 'Food';
                                break;
                            case 'transport': 
                                $icon_class = 'icon-transport';
                                $category_name = 'Transport';
                                break;
                            case 'books_supplies': 
                                $icon_class = 'icon-books';
                                $category_name = 'Books & Supplies';
                                break;
                            case 'personal': 
                                $icon_class = 'icon-personal';
                                $category_name = 'Personal';
                                break;
                            case 'savings': 
                                $icon_class = 'icon-savings';
                                $category_name = 'Savings';
                                break;
                        }
                        
                        $spent = $current_spending[$category] ?? 0;
                        $percentage = ($monthly_allowance > 0) ? min(100, ($spent / $amount) * 100) : 0;
                    ?>
                    <div class="mb-4">
                        <div class="d-flex align-items-center mb-2">
                            <div class="category-icon <?php echo $icon_class; ?>">
                                <i class="bi 
                                    <?php 
                                    switch($category) {
                                        case 'loan_repayments': echo 'bi-cash-stack'; break;
                                        case 'accommodation': echo 'bi-house-door'; break;
                                        case 'food': echo 'bi-egg-fried'; break;
                                        case 'transport': echo 'bi-bus-front'; break;
                                        case 'books_supplies': echo 'bi-book'; break;
                                        case 'personal': echo 'bi-person'; break;
                                        case 'savings': echo 'bi-piggy-bank'; break;
                                    }
                                    ?>
                                "></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0"><?php echo $category_name; ?></h6>
                                <small class="text-muted">Recommended: P<?php echo number_format($amount, 2); ?></small>
                            </div>
                            <div class="text-end">
                                <strong>P<?php echo number_format($spent, 2); ?></strong>
                            </div>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?php echo $percentage; ?>%" 
                                 aria-valuenow="<?php echo $percentage; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Active Loans Section -->
        <div class="budget-card glass mb-4">
            <h4 class="fw-bold mb-4"><i class="bi bi-cash-stack me-2"></i>Your Active Loans</h4>
            
            <?php if (!empty($loans)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Loan ID</th>
                                <th>Original Amount</th>
                                <th>Balance</th>
                                <th>Monthly Payment</th>
                                <th>Due Date</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loans as $loan): 
                                $balance = $loan['amount'] - ($loan['amount_paid'] ?? 0);
                                $progress = ($loan['amount_paid'] / $loan['amount']) * 100;
                            ?>
                            <tr>
                                <td>#<?php echo $loan['id']; ?></td>
                                <td>P<?php echo number_format($loan['amount'], 2); ?></td>
                                <td>P<?php echo number_format($balance, 2); ?></td>
                                <td>P<?php echo number_format($loan['monthly_payment'], 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime($loan['due_date'])); ?></td>
                                <td>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?php echo $progress; ?>%" 
                                             aria-valuenow="<?php echo $progress; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                        </div>
                                    </div>
                                    <small><?php echo number_format($progress, 1); ?>% repaid</small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-check-circle display-4 text-muted mb-3"></i>
                    <h5>No Active Loans</h5>
                    <p class="text-muted">You don't have any active loans at this time</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Money Saving Tips -->
        <div class="budget-card glass">
            <h4 class="fw-bold mb-4"><i class="bi bi-piggy-bank me-2"></i>Money Saving Tips</h4>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3 bg-transparent border-0">
                        <div class="card-body">
                            <h5><i class="bi bi-shop text-success me-2"></i> Accommodation</h5>
                            <ul>
                                <li>Consider sharing accommodation with roommates</li>
                                <li>Look for student housing further from campus</li>
                                <li>Negotiate longer leases for better rates</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="card mb-3 bg-transparent border-0">
                        <div class="card-body">
                            <h5><i class="bi bi-bus-front text-warning me-2"></i> Transport</h5>
                            <ul>
                                <li>Use student discounts on public transport</li>
                                <li>Car pool with classmates</li>
                                <li>Consider walking or cycling for short distances</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-3 bg-transparent border-0">
                        <div class="card-body">
                            <h5><i class="bi bi-egg-fried text-danger me-2"></i> Food</h5>
                            <ul>
                                <li>Cook meals in bulk with friends</li>
                                <li>Buy generic brands at supermarkets</li>
                                <li>Take advantage of student meal deals</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="card mb-3 bg-transparent border-0">
                        <div class="card-body">
                            <h5><i class="bi bi-book text-primary me-2"></i> Books & Supplies</h5>
                            <ul>
                                <li>Buy used textbooks or share with classmates</li>
                                <li>Use library resources whenever possible</li>
                                <li>Look for free online learning materials</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Budget Chart
        const budgetCtx = document.getElementById('budgetChart').getContext('2d');
        const budgetChart = new Chart(budgetCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    'Loan Repayments', 
                    'Accommodation', 
                    'Food', 
                    'Transport', 
                    'Books & Supplies', 
                    'Personal', 
                    'Savings'
                ],
                datasets: [{
                    data: [
                        <?php echo $recommendations['loan_repayments']; ?>,
                        <?php echo $recommendations['accommodation']; ?>,
                        <?php echo $recommendations['food']; ?>,
                        <?php echo $recommendations['transport']; ?>,
                        <?php echo $recommendations['books_supplies']; ?>,
                        <?php echo $recommendations['personal']; ?>,
                        <?php echo $recommendations['savings']; ?>
                    ],
                    backgroundColor: [
                        '#6C757D',
                        '#6A8DFF',
                        '#FF8E53',
                        '#A18CD1',
                        '#4ECDC4',
                        '#FF6B6B',
                        '#58C9B9'
                    ],
                    borderColor: 'rgba(0, 0, 0, 0.1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: P${value.toFixed(2)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Print budget
        document.getElementById('printBudget').addEventListener('click', function() {
            window.print();
        });

        // Export budget (simplified example)
        document.getElementById('exportBudget').addEventListener('click', function() {
            // In a real implementation, this would generate a PDF or CSV
            alert('Budget exported successfully!');
        });
    </script>
</body>
</html>