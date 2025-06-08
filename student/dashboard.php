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

// Initialize variables with default values
$error = null;
$student = [];
$loans = [];
$totalLoans = 0;
$totalPaid = 0;
$totalPending = 0;
$loanStatusCounts = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'repaid' => 0,
    'defaulted' => 0
];

try {
    // Get database connection
    $pdo = DatabaseConnection::getInstance()->getConnection();
    
    // Get student info with proper error handling
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_id, s.full_name, s.fnb_account_number, 
               sfp.ai_risk_score, sfp.dynamic_loan_limit, sfp.allowance_day,
               u.name as university_name
        FROM students s
        LEFT JOIN student_financial_profiles sfp ON s.id = sfp.student_id
        LEFT JOIN universities u ON s.university_id = u.id
        WHERE s.id = ? AND s.is_verified = 1
    ");
    $stmt->execute([$_SESSION['student_db_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception("Student not found or account not verified");
    }
    
    // Get loan data with proper aggregation
    $stmt = $pdo->prepare("
    SELECT 
        l.id, 
        l.amount, 
        COALESCE(SUM(r.amount), 0) as amount_paid,
        l.status,
        l.disbursement_date,
        l.due_date,
        DATEDIFF(l.due_date, CURDATE()) as days_remaining,
        (l.amount - COALESCE(SUM(r.amount), 0)) as outstanding_balance,
        l.status_updated_at as updated_at 
    FROM loans l
    LEFT JOIN repayments r ON l.id = r.loan_id
    WHERE l.student_id = ? 
    GROUP BY l.id, l.amount, l.status, l.disbursement_date, l.due_date, l.status_updated_at
    ORDER BY l.status_updated_at DESC
");
$stmt->execute([$_SESSION['student_db_id']]);
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals and status counts
  foreach ($loans as $loan) {
    $totalLoans += (float)$loan['amount'];
    $totalPaid += (float)$loan['amount_paid'];
    $totalPending += ((float)$loan['amount'] - (float)$loan['amount_paid']);
    
    // Count loan statuses for chart
    if (array_key_exists($loan['status'], $loanStatusCounts)) {
        $loanStatusCounts[$loan['status']]++;
    }
}
    // Get recent activity (last 5 transactions)
    $stmt = $pdo->prepare("
        (SELECT 
            'loan' as type,
            id,
            amount,
            status,
            created_at as date
        FROM loans 
        WHERE student_id = ?)
        
        UNION ALL
        
        (SELECT 
            'repayment' as type,
            id,
            amount,
            'completed' as status,
            created_at as date
        FROM repayments 
        WHERE loan_id IN (SELECT id FROM loans WHERE student_id = ?))
        
        ORDER BY date DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['student_db_id'], $_SESSION['student_db_id']]);
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "A database error occurred. Please try again later.";
} catch (Exception $e) {
    error_log("Application error: " . $e->getMessage());
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - UniFlow Student Loans</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css" rel="stylesheet">
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

        /* Quick Action Buttons */
        .btn-lg {
            border-radius: 15px;
            transition: all 0.3s ease;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: white;
        }

        .btn-lg:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
        }

        .btn-success {
            background: linear-gradient(135deg, #4ECDC4, #6A8DFF);
        }

        .btn-info {
            background: linear-gradient(135deg, #6A8DFF, #A18CD1);
        }

        .btn-warning {
            background: linear-gradient(135deg, #FF8E53, #FFC3A0);
        }

        /* Loading Animation */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 1s ease forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .welcome-title {
                font-size: 1.5rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }

            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.2rem;
            }
            
            .phone-mockup {
                width: 250px;
                height: 500px;
            }
            
            .cta-title {
                font-size: 2rem;
            }
        }

        /* Enhanced Footer Styles */
        .footer {
            background: linear-gradient(135deg, #0a1628 0%, #1e3a5f 100%);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 80px 0 30px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 30%, rgba(255, 107, 107, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(78, 205, 196, 0.08) 0%, transparent 50%);
            z-index: 0;
        }

        .footer-content {
            position: relative;
            z-index: 1;
        }

        .footer-logo {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
            display: inline-block;
        }

        .footer-description {
            opacity: 0.8;
            line-height: 1.7;
            margin-bottom: 30px;
        }

        .footer-links h5 {
            color: #FF6B6B;
            font-size: 1.2rem;
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 10px;
        }

        .footer-links h5::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 2px;
            background: linear-gradient(90deg, #FF6B6B, #4ECDC4);
        }

        .footer-links ul {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .footer-links a:hover {
            color: #4ECDC4;
            transform: translateX(5px);
        }

        .footer-links a::before {
            content: '→';
            margin-right: 8px;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .footer-links a:hover::before {
            opacity: 1;
        }

        .footer-contact-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .footer-contact-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .footer-social {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .footer-social a {
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .footer-social a:hover {
            background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
            transform: translateY(-3px);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 30px;
            margin-top: 60px;
            text-align: center;
        }

        .footer-partners {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .partner-logo {
            height: 40px;
            opacity: 0.7;
            transition: all 0.3s ease;
            filter: grayscale(100%) brightness(2);
        }

        .partner-logo:hover {
            opacity: 1;
            filter: grayscale(0) brightness(1);
        }

        .footer-legal {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .footer-legal a {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer-legal a:hover {
            color: #4ECDC4;
        }

        .footer-copyright {
            opacity: 0.6;
            font-size: 0.9rem;
        }

        /* Back to Top Button */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            text-decoration: none;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 999;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
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
<body>
    <!-- Include Navbar -->
    <?php include(ROOT_PATH . '/student/navbar.php'); ?>
    
    <div class="container py-5">
        <!-- Welcome Section -->
        <div class="welcome-section glass">
            <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?>!</h1>
            <p class="welcome-subtitle">
                <?php if (!empty($student['university_name'])): ?>
                    Studying at <?php echo htmlspecialchars($student['university_name']); ?> | 
                <?php endif; ?>
                Account Status: <span class="text-success">Verified</span>
            </p>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="row mt-3">
                <div class="col-md-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-shield-alt me-2 text-info"></i>
                        <div>
                            <small class="text-muted">AI Risk Score</small>
                            <h5 class="mb-0"><?php echo htmlspecialchars($student['ai_risk_score'] ?? 'N/A'); ?>%</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-credit-card me-2 text-primary"></i>
                        <div>
                            <small class="text-muted">Loan Limit</small>
                            <h5 class="mb-0">P<?php echo number_format($student['dynamic_loan_limit'] ?? 0, 2); ?></h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-calendar-day me-2 text-warning"></i>
                        <div>
                            <small class="text-muted">Allowance Day</small>
                            <h5 class="mb-0"><?php echo htmlspecialchars($student['allowance_day'] ?? 'N/A'); ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="row">
            <div class="col-md-4">
                <div class="dashboard-card card glass">
                    <div class="card-header">
                        <i class="fas fa-dollar-sign me-2"></i> Total Loans
                    </div>
                    <div class="card-body position-relative">
                        <i class="fas fa-hand-holding-usd stat-icon"></i>
                        <div class="stat-number">P<?php echo number_format($totalLoans, 2); ?></div>
                        <p class="text-muted">Total amount of all your loans</p>
                        <small class="text-info">
                            <i class="fas fa-info-circle"></i> <?php echo count($loans); ?> loan(s) taken
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="dashboard-card card glass">
                    <div class="card-header">
                        <i class="fas fa-check-circle me-2"></i> Amount Paid
                    </div>
                    <div class="card-body position-relative">
                        <i class="fas fa-piggy-bank stat-icon"></i>
                        <div class="stat-number">P<?php echo number_format($totalPaid, 2); ?></div>
                        <p class="text-muted">Total amount you've paid back</p>
                        <?php if ($totalLoans > 0): ?>
                            <small class="text-success">
                                <?php echo number_format(($totalPaid/$totalLoans)*100, 1); ?>% repaid
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="dashboard-card card glass">
                    <div class="card-header">
                        <i class="fas fa-clock me-2"></i> Pending Balance
                    </div>
                    <div class="card-body position-relative">
                        <i class="fas fa-hourglass-half stat-icon"></i>
                        <div class="stat-number">P<?php echo number_format($totalPending, 2); ?></div>
                        <p class="text-muted">Remaining amount to be paid</p>
                        <?php if ($totalPending > 0): ?>
                            <small class="text-warning">
                                <i class="fas fa-exclamation-triangle"></i> Active balance
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <!-- Loan Status Chart -->
            <div class="col-lg-8">
                <div class="dashboard-card card glass">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-chart-pie me-2"></i> Loan Status Overview</span>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-light active" data-chart-type="doughnut">Doughnut</button>
                            <button class="btn btn-outline-light" data-chart-type="bar">Bar</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height: 300px;">
                            <canvas id="loanChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Active Loans Table -->
                <div class="dashboard-card card glass mt-4">
                    <div class="card-header">
                        <i class="fas fa-list me-2"></i> Your Active Loans
                    </div>
                    <div class="card-body">
                        <?php if (!empty($loans)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Loan ID</th>
                                            <th>Amount</th>
                                            <th>Paid</th>
                                            <th>Outstanding</th>
                                            <th>Status</th>
                                            <th>Due Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($loans as $loan): ?>
                                            <?php if ($loan['status'] === 'approved' || $loan['status'] === 'defaulted'): ?>
                                                <tr>
                                                    <td>#<?php echo $loan['id']; ?></td>
                                                    <td>P<?php echo number_format($loan['amount'], 2); ?></td>
                                                    <td>P<?php echo number_format($loan['amount_paid'], 2); ?></td>
                                                    <td>P<?php echo number_format($loan['outstanding_balance'], 2); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $loan['status'] === 'approved' ? 'primary' : 'danger';
                                                        ?>">
                                                            <?php echo ucfirst($loan['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M j, Y', strtotime($loan['due_date'])); ?>
                                                        <br>
                                                        <small class="text-<?php 
                                                            echo $loan['days_remaining'] > 7 ? 'muted' : 'warning';
                                                        ?>">
                                                            <?php echo $loan['days_remaining'] > 0 ? 
                                                                $loan['days_remaining'] . ' days remaining' : 
                                                                abs($loan['days_remaining']) . ' days overdue'; ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <a href="loan-details.php?id=<?php echo $loan['id']; ?>" 
                                                           class="btn btn-sm btn-outline-light">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-info-circle text-muted fa-3x mb-3"></i>
                                <h5 class="text-muted">No active loans found</h5>
                                <p>You currently don't have any active loans</p>
                                <a href="apply-loan.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle"></i> Apply for a Loan
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="col-lg-4">
                <div class="dashboard-card card glass">
                    <div class="card-header">
                        <i class="fas fa-bell me-2"></i> Recent Activity
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recentActivity)): ?>
                            <?php foreach ($recentActivity as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <?php if ($activity['type'] === 'loan'): ?>
                                            <i class="fas fa-file-invoice-dollar"></i>
                                        <?php else: ?>
                                            <i class="fas fa-money-bill-wave"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">
                                            <?php echo ucfirst($activity['type']); ?> #<?php echo $activity['id']; ?>
                                        </h6>
                                        <p class="mb-0 text-muted small">
                                            Amount: P<?php echo number_format($activity['amount'], 2); ?>
                                            <br>
                                            Status: 
                                            <span class="
                                                <?php 
                                                    if ($activity['status'] === 'completed' || $activity['status'] === 'repaid') {
                                                        echo 'text-success';
                                                    } elseif ($activity['status'] === 'defaulted') {
                                                        echo 'text-danger';
                                                    } else {
                                                        echo 'text-warning';
                                                    }
                                                ?>
                                            ">
                                                <?php echo ucfirst($activity['status']); ?>
                                            </span>
                                            <br>
                                            <?php echo date('M j, Y g:i a', strtotime($activity['date'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="activity.php" class="btn btn-sm btn-outline-light">
                                    View All Activity <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-info-circle text-muted fa-2x mb-2"></i>
                                <p class="text-muted">No recent activity found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <br><br>
                <br>               <br>
                <br>                <br>
                <br>                <br>
                <br>                <br>
                <br>                <br>
                
              
                <!-- Financial Tips Card -->
                <div class="dashboard-card card glass mt-4">
                    <div class="card-header">
                        <i class="fas fa-lightbulb me-2"></i> Financial Tips
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info bg-transparent border-info">
                            <h6><i class="fas fa-piggy-bank me-2"></i> Save on Interest</h6>
                            <p class="small mb-2">Paying early reduces your total interest by up to 30%.</p>
                        </div>
                        
                        <div class="alert alert-success bg-transparent border-success mt-3">
                            <h6><i class="fas fa-chart-line me-2"></i> Improve Your Score</h6>
                            <p class="small mb-2">Timely repayments increase your loan limit by 5% each month.</p>
                        </div>
                        
                        <div class="alert alert-warning bg-transparent border-warning mt-3">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i> Avoid Defaults</h6>
                            <p class="small mb-2">Late payments affect your ability to borrow in future.</p>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="financial-tips.php" class="btn btn-sm btn-outline-light">
                                More Tips <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="dashboard-card card glass">
                    <div class="card-header">
                        <i class="fas fa-bolt me-2"></i> Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3 col-6 mb-3">
                                <a href="apply-loan.php" class="btn btn-primary btn-lg w-100 py-3">
                                    <i class="fas fa-plus-circle fa-2x mb-2"></i><br>
                                    Apply for Loan
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="make-payment.php" class="btn btn-success btn-lg w-100 py-3">
                                    <i class="fas fa-money-bill-wave fa-2x mb-2"></i><br>
                                    Make Payment
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="loans.php" class="btn btn-info btn-lg w-100 py-3">
                                    <i class="fas fa-file-invoice-dollar fa-2x mb-2"></i><br>
                                    View Loans
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="profile.php" class="btn btn-warning btn-lg w-100 py-3">
                                    <i class="fas fa-user-cog fa-2x mb-2"></i><br>
                                    Profile Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script>
        // Loan Status Chart Data from PHP
        const loanStatusData = {
            labels: ['Pending', 'Approved', 'Rejected', 'Repaid', 'Defaulted'],
            datasets: [{
                data: [
                    <?php echo $loanStatusCounts['pending']; ?>,
                    <?php echo $loanStatusCounts['approved']; ?>,
                    <?php echo $loanStatusCounts['rejected']; ?>,
                    <?php echo $loanStatusCounts['repaid']; ?>,
                    <?php echo $loanStatusCounts['defaulted']; ?>
                ],
                backgroundColor: [
                    '#FFC107',
                    '#4CAF50',
                    '#F44336',
                    '#2196F3',
                    '#9C27B0'
                ],
                borderWidth: 0
            }]
        };
        
        // Financial Overview Chart Data from PHP
        const financialData = {
            labels: ['Total Loans', 'Amount Paid', 'Pending Balance'],
            datasets: [{
                data: [
                    <?php echo $totalLoans; ?>,
                    <?php echo $totalPaid; ?>,
                    <?php echo $totalPending; ?>
                ],
                backgroundColor: [
                    '#3F51B5',
                    '#4CAF50',
                    '#FF9800'
                ],
                borderWidth: 0
            }]
        };
        
        // Initialize Charts
        let loanChart;
        let currentChartType = 'doughnut';
        
        function initChart() {
            const ctx = document.getElementById('loanChart').getContext('2d');
            
            if (loanChart) {
                loanChart.destroy();
            }
            
            if (currentChartType === 'doughnut') {
                loanChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: loanStatusData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    color: 'white'
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((value / total) * 100);
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        cutout: '65%'
                    }
                });
            } else {
                loanChart = new Chart(ctx, {
                    type: 'bar',
                    data: loanStatusData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return `${context.dataset.label || ''}: ${context.raw}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: 'white',
                                    stepSize: 1
                                },
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.1)'
                                }
                            },
                            x: {
                                ticks: {
                                    color: 'white'
                                },
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }
        }
        
        // Toggle chart type
        document.querySelectorAll('[data-chart-type]').forEach(btn => {
            btn.addEventListener('click', function() {
                currentChartType = this.dataset.chartType;
                document.querySelectorAll('[data-chart-type]').forEach(b => {
                    b.classList.toggle('active', b === this);
                });
                initChart();
            });
        });
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initChart();
            
            // Add animation to cards
            const cards = document.querySelectorAll('.dashboard-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('visible');
                }, index * 100);
            });
            
            // Add animated background
            const bgAnimated = document.createElement('div');
            bgAnimated.className = 'bg-animated';
            document.body.insertBefore(bgAnimated, document.body.firstChild);
        });
    </script>
</body>
</html>