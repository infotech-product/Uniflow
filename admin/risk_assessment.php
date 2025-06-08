<?php
// risk_assessment.php - AI-Powered Risk Assessment Dashboard
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

// Initialize variables
$riskData = [];
$studentDetails = [];
$loanHistory = [];
$paymentBehavior = [];
$error = null;
$studentId = $_GET['student_id'] ?? null;
$universityId = $_GET['university_id'] ?? null;

// Load risk assessment data
try {
    // Get all students with risk profiles
    $stmt = $pdo->prepare("
        SELECT 
            s.id, 
            s.student_id AS student_number,
            s.full_name,
            u.name AS university_name,
            sfp.ai_risk_score,
            sfp.dynamic_loan_limit,
            sfp.emergency_topup_unlocked,
            COUNT(l.id) AS total_loans,
            SUM(l.amount) AS total_borrowed,
            COALESCE(SUM(r.amount), 0) AS total_repaid,
            COUNT(CASE WHEN l.status = 'defaulted' THEN 1 END) AS defaulted_loans
        FROM students s
        JOIN universities u ON s.university_id = u.id
        JOIN student_financial_profiles sfp ON s.id = sfp.student_id
        LEFT JOIN loans l ON s.id = l.student_id
        LEFT JOIN repayments r ON l.id = r.loan_id
        GROUP BY s.id, s.student_id, s.full_name, u.name, sfp.ai_risk_score, sfp.dynamic_loan_limit, sfp.emergency_topup_unlocked
        ORDER BY sfp.ai_risk_score DESC
    ");
    $stmt->execute();
    $riskData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If specific student requested, load detailed profile
    if ($studentId) {
        // Student details
        $stmt = $pdo->prepare("
            SELECT 
                s.*,
                u.name AS university_name,
                sfp.*,
                (SELECT COUNT(*) FROM loans WHERE student_id = s.id) AS loan_count,
                (SELECT SUM(amount) FROM loans WHERE student_id = s.id) AS total_borrowed,
                (SELECT SUM(amount) FROM repayments r JOIN loans l ON r.loan_id = l.id WHERE l.student_id = s.id) AS total_repaid
            FROM students s
            JOIN universities u ON s.university_id = u.id
            JOIN student_financial_profiles sfp ON s.id = sfp.student_id
            WHERE s.id = ?
        ");
        $stmt->execute([$studentId]);
        $studentDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        // Loan history
        $stmt = $pdo->prepare("
            SELECT 
                l.*,
                (SELECT SUM(amount) FROM repayments WHERE loan_id = l.id) AS amount_repaid,
                DATEDIFF(NOW(), l.due_date) AS days_overdue
            FROM loans l
            WHERE l.student_id = ?
            ORDER BY l.created_at DESC
        ");
        $stmt->execute([$studentId]);
        $loanHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Payment behavior analysis
        if (!empty($loanHistory)) {
            $stmt = $pdo->prepare("
                SELECT 
                    AVG(DATEDIFF(r.created_at, l.due_date)) AS avg_days_late,
                    COUNT(CASE WHEN DATEDIFF(r.created_at, l.due_date) > 0 THEN 1 END) AS late_payments,
                    COUNT(CASE WHEN DATEDIFF(r.created_at, l.due_date) <= 0 THEN 1 END) AS on_time_payments,
                    MIN(DATEDIFF(r.created_at, l.due_date)) AS earliest_repayment,
                    MAX(DATEDIFF(r.created_at, l.due_date)) AS latest_repayment
                FROM repayments r
                JOIN loans l ON r.loan_id = l.id
                WHERE l.student_id = ?
            ");
            $stmt->execute([$studentId]);
            $paymentBehavior = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

} catch (PDOException $e) {
    error_log("Risk assessment error: " . $e->getMessage());
    $error = "Error loading risk assessment data. Please try again.";
}

// Log admin activity
try {
    $logStmt = $pdo->prepare("
        INSERT INTO admin_logs (action_type, target_id, details) 
        VALUES ('risk_assessment_view', ?, ?)
    ");
    $logStmt->execute([$_SESSION['admin_id'] ?? null, "Accessed risk assessment dashboard"]);
} catch (PDOException $e) {
    error_log("Failed to log admin activity: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Risk Assessment - UniFlow Admin</title>
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
        .risk-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .risk-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .risk-high {
            border-left-color: #F44336;
            background: rgba(244, 67, 54, 0.1);
        }
        .risk-medium {
            border-left-color: #FFC107;
            background: rgba(255, 193, 7, 0.1);
        }
        .risk-low {
            border-left-color: #4CAF50;
            background: rgba(76, 175, 80, 0.1);
        }
        .risk-score {
            font-size: 1.8rem;
            font-weight: 700;
        }
        .progress-thin {
            height: 8px;
        }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: rgba(255,255,255,0.2);
        }
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #4ECDC4;
        }
        .risk-indicator {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .risk-indicator.high {
            background-color: #F44336;
        }
        .risk-indicator.medium {
            background-color: #FFC107;
        }
        .risk-indicator.low {
            background-color: #4CAF50;
        }
    </style>
</head>
<body class="bg-dark text-white">
    <?php include 'navbar.php'; ?>
    
    <div class="container py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">AI Risk Assessment Dashboard</h1>
            <div>
                <button class="btn btn-outline-light" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print Report
                </button>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($studentId && $studentDetails): ?>
            <!-- Student Risk Profile Detail View -->
            <div class="card glass mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Student Risk Profile: <?= htmlspecialchars($studentDetails['full_name']) ?></h5>
                    <a href="risk_assessment.php" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-arrow-left me-1"></i>Back to All Students
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card risk-card <?= $studentDetails['ai_risk_score'] > 70 ? 'risk-high' : ($studentDetails['ai_risk_score'] > 40 ? 'risk-medium' : 'risk-low') ?> mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">AI Risk Score</h5>
                                    <div class="risk-score"><?= number_format($studentDetails['ai_risk_score'], 1) ?></div>
                                    <div class="progress progress-thin mt-2">
                                        <div class="progress-bar bg-<?= $studentDetails['ai_risk_score'] > 70 ? 'danger' : ($studentDetails['ai_risk_score'] > 40 ? 'warning' : 'success') ?>" 
                                             role="progressbar" 
                                             style="width: <?= $studentDetails['ai_risk_score'] ?>%"
                                             aria-valuenow="<?= $studentDetails['ai_risk_score'] ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100"></div>
                                    </div>
                                    <p class="mb-0 mt-2">
                                        <?php if ($studentDetails['ai_risk_score'] > 70): ?>
                                            <span class="text-danger">High Risk</span> - Consider additional verification
                                        <?php elseif ($studentDetails['ai_risk_score'] > 40): ?>
                                            <span class="text-warning">Medium Risk</span> - Standard monitoring recommended
                                        <?php else: ?>
                                            <span class="text-success">Low Risk</span> - Good repayment history
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="card glass mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Student Information</h5>
                                    <div class="mb-3">
                                        <small class="text-muted">Student ID</small>
                                        <div><?= htmlspecialchars($studentDetails['student_number']) ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted">University</small>
                                        <div><?= htmlspecialchars($studentDetails['university_name']) ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted">FNB Account</small>
                                        <div><?= htmlspecialchars($studentDetails['fnb_account_number'] ?? 'Not provided') ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted">Account Status</small>
                                        <div>
                                            <span class="badge <?= $studentDetails['is_verified'] ? 'bg-success' : 'bg-warning' ?>">
                                                <?= $studentDetails['is_verified'] ? 'Verified' : 'Unverified' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card glass">
                                <div class="card-body">
                                    <h5 class="card-title">Credit Limits</h5>
                                    <div class="mb-3">
                                        <small class="text-muted">Current Loan Limit</small>
                                        <div class="fw-bold">P<?= number_format($studentDetails['dynamic_loan_limit'], 2) ?></div>
                                        <small>Automatically adjusted based on behavior</small>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted">Emergency Top-Up</small>
                                        <div>
                                            <span class="badge <?= $studentDetails['emergency_topup_unlocked'] ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $studentDetails['emergency_topup_unlocked'] ? 'Available' : 'Not Available' ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted">Last Loan Date</small>
                                        <div><?= $studentDetails['last_loan_date'] ? date('M d, Y', strtotime($studentDetails['last_loan_date'])) : 'Never' ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-8">
                            <div class="card glass mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Financial Summary</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="card bg-dark">
                                                <div class="card-body">
                                                    <h6 class="card-title">Total Borrowed</h6>
                                                    <div class="fs-4 fw-bold">P<?= number_format($studentDetails['total_borrowed'] ?? 0, 2) ?></div>
                                                    <small class="text-muted"><?= $studentDetails['loan_count'] ?? 0 ?> loans</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="card bg-dark">
                                                <div class="card-body">
                                                    <h6 class="card-title">Total Repaid</h6>
                                                    <div class="fs-4 fw-bold">P<?= number_format($studentDetails['total_repaid'] ?? 0, 2) ?></div>
                                                    <small class="text-muted">
                                                        <?= $studentDetails['total_borrowed'] > 0 ? 
                                                            number_format(($studentDetails['total_repaid']/$studentDetails['total_borrowed'])*100, 2) : '0' ?>% repayment rate
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="card bg-dark">
                                                <div class="card-body">
                                                    <h6 class="card-title">Defaulted Loans</h6>
                                                    <div class="fs-4 fw-bold"><?= $studentDetails['defaulted_loans'] ?? 0 ?></div>
                                                    <small class="text-muted">Requires follow-up</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="card bg-dark">
                                                <div class="card-body">
                                                    <h6 class="card-title">Payment Behavior</h6>
                                                    <div class="fs-4 fw-bold">
                                                        <?= $paymentBehavior['on_time_payments'] ?? 0 ?> / <?= ($paymentBehavior['on_time_payments'] ?? 0) + ($paymentBehavior['late_payments'] ?? 0) ?>
                                                    </div>
                                                    <small class="text-muted">On-time payments</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($paymentBehavior)): ?>
                                    <div class="mt-4">
                                        <h6>Payment Timeliness Analysis</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <small class="text-muted">Average Days Late</small>
                                                <div class="fw-bold <?= $paymentBehavior['avg_days_late'] > 7 ? 'text-danger' : ($paymentBehavior['avg_days_late'] > 0 ? 'text-warning' : 'text-success') ?>">
                                                    <?= number_format($paymentBehavior['avg_days_late'], 1) ?> days
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">Earliest Repayment</small>
                                                <div class="fw-bold <?= $paymentBehavior['earliest_repayment'] > 0 ? 'text-danger' : 'text-success' ?>">
                                                    <?= $paymentBehavior['earliest_repayment'] ?> days
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">Latest Repayment</small>
                                                <div class="fw-bold <?= $paymentBehavior['latest_repayment'] > 30 ? 'text-danger' : ($paymentBehavior['latest_repayment'] > 7 ? 'text-warning' : 'text-success') ?>">
                                                    <?= $paymentBehavior['latest_repayment'] ?> days
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card glass">
                                <div class="card-header">
                                    <h5 class="mb-0">Loan History Timeline</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($loanHistory)): ?>
                                        <div class="timeline">
                                            <?php foreach ($loanHistory as $loan): ?>
                                                <div class="timeline-item mb-3">
                                                    <div class="card bg-dark">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between">
                                                                <h6>Loan #<?= $loan['id'] ?></h6>
                                                                <span class="badge bg-<?= $loan['status'] === 'approved' ? 'primary' : ($loan['status'] === 'repaid' ? 'success' : ($loan['status'] === 'defaulted' ? 'danger' : 'warning')) ?>">
                                                                    <?= ucfirst($loan['status']) ?>
                                                                </span>
                                                            </div>
                                                            <div class="row mt-2">
                                                                <div class="col-md-4">
                                                                    <small class="text-muted">Amount</small>
                                                                    <div>P<?= number_format($loan['amount'], 2) ?></div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <small class="text-muted">Repaid</small>
                                                                    <div>P<?= number_format($loan['amount_repaid'] ?? 0, 2) ?></div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <small class="text-muted">Due Date</small>
                                                                    <div><?= date('M d, Y', strtotime($loan['due_date'])) ?></div>
                                                                </div>
                                                            </div>
                                                            <?php if ($loan['status'] === 'approved' && $loan['due_date'] < date('Y-m-d')): ?>
                                                                <div class="mt-2 text-danger">
                                                                    <small>Overdue by <?= $loan['days_overdue'] ?> days</small>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-money-bill-wave fa-3x opacity-25 mb-3"></i>
                                            <p class="text-muted">No loan history found for this student</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Main Risk Assessment Dashboard -->
            <div class="card glass mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Student Risk Profiles</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="riskTable">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>University</th>
                                    <th>Risk Score</th>
                                    <th>Loan Limit</th>
                                    <th>Loans</th>
                                    <th>Borrowed</th>
                                    <th>Repaid</th>
                                    <th>Defaults</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($riskData as $student): ?>
                                    <?php 
                                        $riskClass = $student['ai_risk_score'] > 70 ? 'high' : 
                                                    ($student['ai_risk_score'] > 40 ? 'medium' : 'low');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="risk-indicator <?= $riskClass ?> me-2"></span>
                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($student['full_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($student['student_number']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($student['university_name']) ?></td>
                                        <td>
                                            <div class="progress progress-thin" style="width: 100px;">
                                                <div class="progress-bar bg-<?= $riskClass === 'high' ? 'danger' : ($riskClass === 'medium' ? 'warning' : 'success') ?>" 
                                                     role="progressbar" 
                                                     style="width: <?= $student['ai_risk_score'] ?>%"
                                                     aria-valuenow="<?= $student['ai_risk_score'] ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100"></div>
                                            </div>
                                            <small><?= number_format($student['ai_risk_score'], 1) ?></small>
                                        </td>
                                        <td>P<?= number_format($student['dynamic_loan_limit'], 2) ?></td>
                                        <td><?= $student['total_loans'] ?></td>
                                        <td>P<?= number_format($student['total_borrowed'] ?? 0, 2) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span>P<?= number_format($student['total_repaid'] ?? 0, 2) ?></span>
                                                <small class="text-muted ms-2">
                                                    <?= $student['total_borrowed'] > 0 ? 
                                                        number_format(($student['total_repaid']/$student['total_borrowed'])*100, 0) : '0' ?>%
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $student['defaulted_loans'] > 0 ? 'danger' : 'secondary' ?>">
                                                <?= $student['defaulted_loans'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="risk_assessment.php?student_id=<?= $student['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-search me-1"></i>Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card glass h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Risk Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 250px;">
                                <canvas id="riskDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card glass h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Top Risk Factors</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Late Payments</span>
                                    <span>42% impact</span>
                                </div>
                                <div class="progress progress-thin">
                                    <div class="progress-bar bg-danger" style="width: 42%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Loan Default History</span>
                                    <span>35% impact</span>
                                </div>
                                <div class="progress progress-thin">
                                    <div class="progress-bar bg-warning" style="width: 35%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Repayment Ratio</span>
                                    <span>15% impact</span>
                                </div>
                                <div class="progress progress-thin">
                                    <div class="progress-bar bg-info" style="width: 15%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>University Performance</span>
                                    <span>8% impact</span>
                                </div>
                                <div class="progress progress-thin">
                                    <div class="progress-bar bg-primary" style="width: 8%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#riskTable').DataTable({
                responsive: true,
                order: [[2, 'desc']], // Sort by risk score descending
                dom: '<"top"f>rt<"bottom"lip><"clear">',
                pageLength: 25,
                columnDefs: [
                    { targets: [2], type: 'num' } // Proper numeric sorting for risk score
                ]
            });
        });
        
        // Risk Distribution Chart (only on main dashboard)
        <?php if (!$studentId): ?>
        const riskCtx = document.getElementById('riskDistributionChart').getContext('2d');
        const riskChart = new Chart(riskCtx, {
            type: 'doughnut',
            data: {
                labels: ['High Risk (>70)', 'Medium Risk (40-70)', 'Low Risk (<40)'],
                datasets: [{
                    data: [
                        <?= count(array_filter($riskData, fn($s) => $s['ai_risk_score'] > 70)) ?>,
                        <?= count(array_filter($riskData, fn($s) => $s['ai_risk_score'] > 40 && $s['ai_risk_score'] <= 70)) ?>,
                        <?= count(array_filter($riskData, fn($s) => $s['ai_risk_score'] <= 40)) ?>
                    ],
                    backgroundColor: [
                        '#F44336',
                        '#FFC107',
                        '#4CAF50'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: 'white'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw + ' students';
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>