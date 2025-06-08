<?php
// admin/dashboard.php
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Start session and check authentication
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}
$_SESSION['admin_logged_in'] = true;

// Get database connection
$pdo = DatabaseConnection::getInstance()->getConnection();

// Get statistics for dashboard
try {
    // Total students
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM students WHERE is_verified = 1")->fetchColumn();
    
    // Total loans
    $totalLoans = $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn();
    
    // Active loans (approved but not repaid)
    $activeLoans = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'approved'")->fetchColumn();
    
    // Defaulted loans
    $defaultedLoans = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'defaulted'")->fetchColumn();
    
    // Total loan amount disbursed
    $totalDisbursed = $pdo->query("SELECT SUM(amount) FROM loans WHERE status IN ('approved', 'repaid')")->fetchColumn();
    
    // Total repaid amount
    $totalRepaid = $pdo->query("SELECT SUM(amount_paid) FROM loans WHERE status = 'repaid'")->fetchColumn();
    
    // Recent loan applications (last 7 days)
    $recentLoans = $pdo->query("SELECT COUNT(*) FROM loans WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    
    // Recent repayments (last 7 days)
    $recentRepayments = $pdo->query("SELECT COUNT(*) FROM repayments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    
    // Get recent loan applications
    $stmt = $pdo->prepare("
        SELECT l.*, s.full_name, s.student_id AS student_number, u.name AS university_name 
        FROM loans l
        JOIN students s ON l.student_id = s.id
        JOIN universities u ON l.university_id = u.id
        ORDER BY l.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentLoanApplications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent repayments
    $stmt = $pdo->prepare("
        SELECT r.*, s.full_name, s.student_id AS student_number, u.name AS university_name, l.amount AS loan_amount
        FROM repayments r
        JOIN loans l ON r.loan_id = l.id
        JOIN students s ON l.student_id = s.id
        JOIN universities u ON l.university_id = u.id
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentRepaymentRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get universities with most loans
    $stmt = $pdo->prepare("
        SELECT u.name AS university_name, COUNT(l.id) AS loan_count, 
               SUM(l.amount) AS total_amount, 
               AVG(l.amount) AS avg_amount
        FROM loans l
        JOIN universities u ON l.university_id = u.id
        GROUP BY l.university_id
        ORDER BY loan_count DESC
        LIMIT 5
    ");
    $stmt->execute();
    $topUniversities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get loan status distribution
    $loanStatusData = $pdo->query("
        SELECT status, COUNT(*) AS count 
        FROM loans 
        GROUP BY status
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Format numbers
    $totalDisbursed = $totalDisbursed ? number_format($totalDisbursed, 2) : '0.00';
    $totalRepaid = $totalRepaid ? number_format($totalRepaid, 2) : '0.00';
    
} catch (PDOException $e) {
    error_log("Dashboard statistics error: " . $e->getMessage());
    $error = "Error loading dashboard statistics. Please try again.";
}

// Log admin activity
try {
    $logStmt = $pdo->prepare("
        INSERT INTO admin_logs (action_type, target_id, details) 
        VALUES ('system_change', ?, 'Accessed admin dashboard')
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
    <title>Admin Dashboard - UniFlow Student Loans</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
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
        }

        /* Status badges */
        .badge {
            font-weight: 600;
            padding: 0.5em 0.8em;
            border-radius: 0.35rem;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #FFC107, #FF9800);
        }
        
        .status-approved {
            background: linear-gradient(135deg, #4CAF50, #8BC34A);
        }
        
        .status-rejected {
            background: linear-gradient(135deg, #F44336, #E91E63);
        }
        
        .status-repaid {
            background: linear-gradient(135deg, #2196F3, #03A9F4);
        }
        
        .status-defaulted {
            background: linear-gradient(135deg, #607D8B, #9E9E9E);
        }
        
        /* Table styles */
        .table {
            color: white;
        }
        
        .table th {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05rem;
        }
        
        .table td {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            vertical-align: middle;
        }
        
        /* Animation delays for cards */
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        .delay-5 { animation-delay: 0.5s; }

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
    <!-- Animated background -->
    <div class="bg-animated"></div>
    
    <!-- Include navbar -->
    <?php include 'navbar.php'; ?>
    
    <!-- Main Content -->
    <div class="container py-4">
        <!-- Welcome Section -->
        <div class="welcome-section fade-in">
            <h1 class="welcome-title">Welcome back,<?= htmlspecialchars($_SESSION['admin_username'] ?? 'Administrator') ?>!</h1>
            <p class="welcome-subtitle">Here's what's happening with your platform today.</p>
            <div class="row">
                <div class="col-md-3">
                    <a href="loans.php" class="btn btn-primary btn-lg w-100 mb-2">
                        <i class="fas fa-clock me-2"></i> Review Loans
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="/Uniflow/admin/students.php" class="btn btn-success btn-lg w-100 mb-2">
                        <i class="fas fa-users me-2"></i> Manage Students
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="repayments.php" class="btn btn-info btn-lg w-100 mb-2">
                        <i class="fas fa-money-bill-wave me-2"></i> Process Payments
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="reports.php" class="btn btn-warning btn-lg w-100 mb-2">
                        <i class="fas fa-chart-bar me-2"></i> Generate Reports
                    </a>
                </div>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error!</strong> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="row">
            <!-- Total Students -->
            <div class="col-xl-3 col-md-6 mb-4 fade-in delay-1">
                <div class="dashboard-card glass">
                    <div class="card-header">
                        <h5 class="mb-0">Total Students</h5>
                        <i class="fas fa-users stat-icon"></i>
                    </div>
                    <div class="card-body">
                        <div class="stat-number"><?= number_format($totalStudents) ?></div>
                        <p class="mb-0 text-muted">Verified student accounts</p>
                    </div>
                </div>
            </div>
            
            <!-- Total Loans -->
            <div class="col-xl-3 col-md-6 mb-4 fade-in delay-2">
                <div class="dashboard-card glass">
                    <div class="card-header">
                        <h5 class="mb-0">Total Loans</h5>
                        <i class="fas fa-hand-holding-usd stat-icon"></i>
                    </div>
                    <div class="card-body">
                        <div class="stat-number"><?= number_format($totalLoans) ?></div>
                        <p class="mb-0 text-muted">All-time loan applications</p>
                    </div>
                </div>
            </div>
            
            <!-- Active Loans -->
            <div class="col-xl-3 col-md-6 mb-4 fade-in delay-3">
                <div class="dashboard-card glass">
                    <div class="card-header">
                        <h5 class="mb-0">Active Loans</h5>
                        <i class="fas fa-clock stat-icon"></i>
                    </div>
                    <div class="card-body">
                        <div class="stat-number"><?= number_format($activeLoans) ?></div>
                        <p class="mb-0 text-muted">Currently outstanding</p>
                    </div>
                </div>
            </div>
            
            <!-- Defaulted Loans -->
            <div class="col-xl-3 col-md-6 mb-4 fade-in delay-4">
                <div class="dashboard-card glass">
                    <div class="card-header">
                        <h5 class="mb-0">Defaulted Loans</h5>
                        <i class="fas fa-exclamation-triangle stat-icon"></i>
                    </div>
                    <div class="card-body">
                        <div class="stat-number"><?= number_format($defaultedLoans) ?></div>
                        <p class="mb-0 text-muted">Requiring follow-up</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Second Row of Stats -->
        <div class="row">
            <!-- Total Disbursed -->
            <div class="col-xl-3 col-md-6 mb-4 fade-in delay-1">
                <div class="dashboard-card glass">
                    <div class="card-header">
                        <h5 class="mb-0">Total Disbursed</h5>
                        <i class="fas fa-money-bill-wave stat-icon"></i>
                    </div>
                    <div class="card-body">
                        <div class="stat-number">P<?= $totalDisbursed ?></div>
                        <p class="mb-0 text-muted">All-time loan amount</p>
                    </div>
                </div>
            </div>
            
            <!-- Total Repaid -->
            <div class="col-xl-3 col-md-6 mb-4 fade-in delay-2">
                <div class="dashboard-card glass">
                    <div class="card-header">
                        <h5 class="mb-0">Total Repaid</h5>
                        <i class="fas fa-coins stat-icon"></i>
                    </div>
                    <div class="card-body">
                        <div class="stat-number">P<?= $totalRepaid ?></div>
                        <p class="mb-0 text-muted">Amount recovered</p>
                    </div>
                </div>
            </div>
            
            <!-- Recent Loans -->
            <div class="col-xl-3 col-md-6 mb-4 fade-in delay-3">
                <div class="dashboard-card glass">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Loans</h5>
                        <i class="fas fa-calendar-day stat-icon"></i>
                    </div>
                    <div class="card-body">
                        <div class="stat-number"><?= number_format($recentLoans) ?></div>
                        <p class="mb-0 text-muted">Last 7 days</p>
                    </div>
                </div>
            </div>
            
            <!-- Recent Repayments -->
            <div class="col-xl-3 col-md-6 mb-4 fade-in delay-4">
                <div class="dashboard-card glass">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Repayments</h5>
                        <i class="fas fa-calendar-check stat-icon"></i>
                    </div>
                    <div class="card-body">
                        <div class="stat-number"><?= number_format($recentRepayments) ?></div>
                        <p class="mb-0 text-muted">Last 7 days</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity Section -->
        <div class="row">
            <!-- Recent Loan Applications -->
            <div class="col-lg-6 mb-4 fade-in">
                <div class="dashboard-card glass h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Loan Applications</h5>
                        <a href="loans.php" class="btn btn-sm btn-outline-light">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentLoanApplications as $loan): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar bg-primary me-2">
                                                        <?= strtoupper(substr($loan['full_name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?= htmlspecialchars($loan['full_name']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($loan['student_number']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>P<?= number_format($loan['amount'], 2) ?></td>
                                            <td>
                                                <span class="badge status-<?= $loan['status'] ?>">
                                                    <?= ucfirst($loan['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($loan['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Repayments -->
            <div class="col-lg-6 mb-4 fade-in">
                <div class="dashboard-card glass h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Repayments</h5>
                        <a href="repayments.php" class="btn btn-sm btn-outline-light">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Amount</th>
                                        <th>Loan</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentRepaymentRecords as $repayment): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar bg-success me-2">
                                                        <?= strtoupper(substr($repayment['full_name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?= htmlspecialchars($repayment['full_name']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($repayment['student_number']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>P<?= number_format($repayment['amount'], 2) ?></td>
                                            <td>P<?= number_format($repayment['loan_amount'], 2) ?></td>
                                            <td><?= date('M d, Y', strtotime($repayment['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Third Row - Top Universities and Loan Status -->
        <div class="row">
            <!-- Top Universities -->
            <div class="col-lg-6 mb-4 fade-in">
                <div class="dashboard-card glass h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Top Universities by Loans</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>University</th>
                                        <th>Loans</th>
                                        <th>Total Amount</th>
                                        <th>Average</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topUniversities as $university): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($university['university_name']) ?></td>
                                            <td><?= $university['loan_count'] ?></td>
                                            <td>P<?= number_format($university['total_amount'], 2) ?></td>
                                            <td>P<?= number_format($university['avg_amount'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Loan Status Distribution -->
            <div class="col-lg-6 mb-4 fade-in">
                <div class="dashboard-card glass h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Loan Status Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="loanStatusChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <?php foreach ($loanStatusData as $status): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <div class="badge status-<?= $status['status'] ?> me-2" style="width: 20px; height: 20px;"></div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between">
                                            <span><?= ucfirst($status['status']) ?></span>
                                            <span><?= $status['count'] ?> loans</span>
                                        </div>
                                        <div class="progress mt-1" style="height: 5px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?= ($status['count'] / $totalLoans) * 100 ?>%" 
                                                 aria-valuenow="<?= $status['count'] ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="<?= $totalLoans ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Animate cards on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.dashboard-card');
            cards.forEach(card => {
                setTimeout(() => {
                    card.classList.add('visible');
                }, 100);
            });
            
            // Initialize loan status chart
            const ctx = document.getElementById('loanStatusChart').getContext('2d');
            const loanStatusChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_map(function($item) { return ucfirst($item['status']); }, $loanStatusData)) ?>,
                    datasets: [{
                        data: <?= json_encode(array_map(function($item) { return $item['count']; }, $loanStatusData)) ?>,
                        backgroundColor: [
                            '#FFC107', // pending
                            '#4CAF50', // approved
                            '#F44336', // rejected
                            '#2196F3', // repaid
                            '#607D8B'  // defaulted
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>