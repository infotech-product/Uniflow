<?php
// reports.php - Financial Reporting & Analytics Dashboard
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
$reportData = [];
$error = null;
$chartData = [];
$timeframe = $_GET['timeframe'] ?? 'month';
$reportType = $_GET['report'] ?? 'overview';
$universityFilter = $_GET['university'] ?? '';

// Date ranges
$now = new DateTime();
$dateFormat = 'Y-m-d';

switch ($timeframe) {
    case 'week':
        $startDate = $now->modify('-1 week')->format($dateFormat);
        break;
    case 'year':
        $startDate = $now->modify('-1 year')->format($dateFormat);
        break;
    case 'quarter':
        $startDate = $now->modify('-3 months')->format($dateFormat);
        break;
    case 'all':
        $startDate = '1970-01-01';
        break;
    case 'month':
    default:
        $startDate = $now->modify('-1 month')->format($dateFormat);
        break;
}

// Generate reports based on type
try {
    // Get universities for filter dropdown
    $universities = $pdo->query("SELECT id, name FROM universities ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    // Base where clause
    $whereClause = "WHERE l.created_at >= :startDate";
    $params = ['startDate' => $startDate];
    
    if (!empty($universityFilter)) {
        $whereClause .= " AND l.university_id = :universityId";
        $params['universityId'] = $universityFilter;
    }

    switch ($reportType) {
        case 'overview':
            // Financial Overview Report
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT l.student_id) AS total_students,
                    COUNT(l.id) AS total_loans,
                    SUM(l.amount) AS total_disbursed,
                    SUM(r.amount) AS total_repaid,
                    AVG(l.amount) AS avg_loan_size,
                    COUNT(DISTINCT CASE WHEN l.status = 'defaulted' THEN l.id END) AS defaulted_loans,
                    SUM(CASE WHEN l.status = 'defaulted' THEN l.amount - l.amount_paid ELSE 0 END) AS defaulted_amount
                FROM loans l
                LEFT JOIN repayments r ON l.id = r.loan_id
                $whereClause
            ");
            $stmt->execute($params);
            $reportData = $stmt->fetch(PDO::FETCH_ASSOC);

            // Loan status distribution
            $statusStmt = $pdo->prepare("
                SELECT 
                    l.status,
                    COUNT(*) AS loan_count,
                    SUM(l.amount) AS total_amount
                FROM loans l
                $whereClause
                GROUP BY l.status
            ");
            $statusStmt->execute($params);
            $statusData = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

            // Monthly trends data
            $trendStmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(l.created_at, '%Y-%m') AS month,
                    COUNT(l.id) AS loan_count,
                    SUM(l.amount) AS disbursed_amount,
                    COALESCE(SUM(r.amount), 0) AS repaid_amount
                FROM loans l
                LEFT JOIN repayments r ON l.id = r.loan_id
                WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY month
                ORDER BY month ASC
            ");
            $trendStmt->execute();
            $monthlyTrends = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

            // Prepare chart data
            $chartData['status'] = [
                'labels' => array_column($statusData, 'status'),
                'datasets' => [
                    [
                        'label' => 'Loan Count',
                        'data' => array_column($statusData, 'loan_count'),
                        'backgroundColor' => ['#4CAF50', '#2196F3', '#FFC107', '#F44336', '#9E9E9E']
                    ]
                ]
            ];

            $chartData['monthly'] = [
                'labels' => array_column($monthlyTrends, 'month'),
                'datasets' => [
                    [
                        'label' => 'Disbursed (P)',
                        'data' => array_column($monthlyTrends, 'disbursed_amount'),
                        'borderColor' => '#FF6B6B',
                        'backgroundColor' => 'rgba(255, 107, 107, 0.1)'
                    ],
                    [
                        'label' => 'Repaid (P)',
                        'data' => array_column($monthlyTrends, 'repaid_amount'),
                        'borderColor' => '#4ECDC4',
                        'backgroundColor' => 'rgba(78, 205, 196, 0.1)'
                    ]
                ]
            ];
            break;

        case 'university':
            // University Performance Report
            $stmt = $pdo->prepare("
                SELECT 
                    u.id,
                    u.name AS university_name,
                    COUNT(l.id) AS total_loans,
                    SUM(l.amount) AS total_disbursed,
                    SUM(r.amount) AS total_repaid,
                    COUNT(DISTINCT l.student_id) AS total_students,
                    COUNT(CASE WHEN l.status = 'defaulted' THEN 1 END) AS defaulted_loans,
                    SUM(CASE WHEN l.status = 'defaulted' THEN l.amount - l.amount_paid ELSE 0 END) AS defaulted_amount,
                    (SUM(r.amount) / SUM(l.amount)) * 100 AS repayment_rate
                FROM loans l
                JOIN universities u ON l.university_id = u.id
                LEFT JOIN repayments r ON l.id = r.loan_id
                $whereClause
                GROUP BY u.id, u.name
                ORDER BY total_disbursed DESC
            ");
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'delinquency':
            // Delinquency Report
            $stmt = $pdo->prepare("
                SELECT 
                    l.id AS loan_id,
                    s.full_name,
                    s.student_id,
                    u.name AS university_name,
                    l.amount,
                    l.amount_paid,
                    l.amount - l.amount_paid AS outstanding_balance,
                    l.due_date,
                    DATEDIFF(NOW(), l.due_date) AS days_overdue,
                    CASE 
                        WHEN DATEDIFF(NOW(), l.due_date) BETWEEN 1 AND 30 THEN '1-30 days'
                        WHEN DATEDIFF(NOW(), l.due_date) BETWEEN 31 AND 60 THEN '31-60 days'
                        WHEN DATEDIFF(NOW(), l.due_date) > 60 THEN '60+ days'
                        ELSE 'Current'
                    END AS delinquency_status
                FROM loans l
                JOIN students s ON l.student_id = s.id
                JOIN universities u ON l.university_id = u.id
                WHERE l.status = 'approved' 
                AND l.amount_paid < l.amount
                AND l.due_date < CURDATE()
                ORDER BY days_overdue DESC
            ");
            $stmt->execute();
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'student':
            // Student Performance Report
            $stmt = $pdo->prepare("
                SELECT 
                    s.id,
                    s.full_name,
                    s.student_id,
                    u.name AS university_name,
                    COUNT(l.id) AS total_loans,
                    SUM(l.amount) AS total_borrowed,
                    SUM(r.amount) AS total_repaid,
                    MAX(l.created_at) AS last_loan_date,
                    (SUM(r.amount) / SUM(l.amount)) * 100 AS repayment_rate,
                    sfp.ai_risk_score,
                    sfp.dynamic_loan_limit
                FROM students s
                JOIN universities u ON s.university_id = u.id
                LEFT JOIN loans l ON s.id = l.student_id
                LEFT JOIN repayments r ON l.id = r.loan_id
                LEFT JOIN student_financial_profiles sfp ON s.id = sfp.student_id
                $whereClause
                GROUP BY s.id, s.full_name, s.student_id, u.name, sfp.ai_risk_score, sfp.dynamic_loan_limit
                ORDER BY repayment_rate DESC
            ");
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }

} catch (PDOException $e) {
    error_log("Reports error: " . $e->getMessage());
    $error = "Error generating report. Please try again.";
}

// Log admin activity
try {
    $logStmt = $pdo->prepare("
        INSERT INTO admin_logs (action_type, target_id, details) 
        VALUES ('report_generated', ?, ?)
    ");
    $logStmt->execute([$_SESSION['admin_id'] ?? null, "Generated $reportType report for $timeframe timeframe"]);
} catch (PDOException $e) {
    error_log("Failed to log admin activity: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - UniFlow Admin</title>
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

        .glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
        }
        .chart-container {
            height: 300px;
            position: relative;
        }
        .stat-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stat-card.disbursed { border-left-color: #FF6B6B; }
        .stat-card.repaid { border-left-color: #4ECDC4; }
        .stat-card.students { border-left-color: #6A8DFF; }
        .stat-card.defaults { border-left-color: #F44336; }
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
        }
    </style>
</head>
<body class="bg-dark text-white">
    <?php include 'navbar.php'; ?>
    
    <div class="container py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Financial Reports & Analytics</h1>
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
        
        <!-- Report Controls -->
        <div class="card glass mb-4">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-3">
                        <label for="report" class="form-label">Report Type</label>
                        <select id="report" name="report" class="form-select" onchange="this.form.submit()">
                            <option value="overview" <?= $reportType === 'overview' ? 'selected' : '' ?>>Financial Overview</option>
                            <option value="university" <?= $reportType === 'university' ? 'selected' : '' ?>>University Performance</option>
                            <option value="delinquency" <?= $reportType === 'delinquency' ? 'selected' : '' ?>>Delinquency Report</option>
                            <option value="student" <?= $reportType === 'student' ? 'selected' : '' ?>>Student Performance</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="timeframe" class="form-label">Timeframe</label>
                        <select id="timeframe" name="timeframe" class="form-select" onchange="this.form.submit()">
                            <option value="week" <?= $timeframe === 'week' ? 'selected' : '' ?>>Last Week</option>
                            <option value="month" <?= $timeframe === 'month' ? 'selected' : '' ?>>Last Month</option>
                            <option value="quarter" <?= $timeframe === 'quarter' ? 'selected' : '' ?>>Last Quarter</option>
                            <option value="year" <?= $timeframe === 'year' ? 'selected' : '' ?>>Last Year</option>
                            <option value="all" <?= $timeframe === 'all' ? 'selected' : '' ?>>All Time</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="university" class="form-label">University</label>
                        <select id="university" name="university" class="form-select" onchange="this.form.submit()">
                            <option value="">All Universities</option>
                            <?php foreach ($universities as $uni): ?>
                                <option value="<?= $uni['id'] ?>" <?= $universityFilter == $uni['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($uni['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Report Content -->
        <?php if ($reportType === 'overview'): ?>
            <!-- Financial Overview Report -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card stat-card disbursed h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Disbursed</h5>
                            <div class="stat-number">P<?= number_format($reportData['total_disbursed'] ?? 0, 2) ?></div>
                            <p class="mb-0 text-muted"><?= $timeframe ?> period</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card repaid h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Repaid</h5>
                            <div class="stat-number">P<?= number_format($reportData['total_repaid'] ?? 0, 2) ?></div>
                            <p class="mb-0 text-muted"><?= number_format(($reportData['total_repaid']/$reportData['total_disbursed'])*100 ?? 0, 2) ?>% repayment rate</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card students h-100">
                        <div class="card-body">
                            <h5 class="card-title">Active Students</h5>
                            <div class="stat-number"><?= number_format($reportData['total_students'] ?? 0) ?></div>
                            <p class="mb-0 text-muted"><?= number_format($reportData['total_loans'] ?? 0) ?> loans</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card defaults h-100">
                        <div class="card-body">
                            <h5 class="card-title">Default Amount</h5>
                            <div class="stat-number">P<?= number_format($reportData['defaulted_amount'] ?? 0, 2) ?></div>
                            <p class="mb-0 text-muted"><?= number_format($reportData['defaulted_loans'] ?? 0) ?> loans</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card glass h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Loan Status Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="card glass h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Monthly Trends (12 Months)</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="trendsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card glass">
                <div class="card-header">
                    <h5 class="mb-0">Loan Portfolio Summary</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Metric</th>
                                    <th>Count</th>
                                    <th>Amount (P)</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Total Loans Disbursed</td>
                                    <td><?= number_format($reportData['total_loans'] ?? 0) ?></td>
                                    <td><?= number_format($reportData['total_disbursed'] ?? 0, 2) ?></td>
                                    <td>100%</td>
                                </tr>
                                <tr>
                                    <td>Amount Repaid</td>
                                    <td>-</td>
                                    <td><?= number_format($reportData['total_repaid'] ?? 0, 2) ?></td>
                                    <td><?= number_format(($reportData['total_repaid']/$reportData['total_disbursed'])*100 ?? 0, 2) ?>%</td>
                                </tr>
                                <tr>
                                    <td>Outstanding Balance</td>
                                    <td>-</td>
                                    <td><?= number_format(($reportData['total_disbursed'] - $reportData['total_repaid']) ?? 0, 2) ?></td>
                                    <td><?= number_format((($reportData['total_disbursed'] - $reportData['total_repaid'])/$reportData['total_disbursed'])*100 ?? 0, 2) ?>%</td>
                                </tr>
                                <tr>
                                    <td>Defaulted Loans</td>
                                    <td><?= number_format($reportData['defaulted_loans'] ?? 0) ?></td>
                                    <td><?= number_format($reportData['defaulted_amount'] ?? 0, 2) ?></td>
                                    <td><?= number_format(($reportData['defaulted_amount']/$reportData['total_disbursed'])*100 ?? 0, 2) ?>%</td>
                                </tr>
                                <tr>
                                    <td>Average Loan Size</td>
                                    <td>-</td>
                                    <td><?= number_format($reportData['avg_loan_size'] ?? 0, 2) ?></td>
                                    <td>-</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        <?php elseif ($reportType === 'university'): ?>
            <!-- University Performance Report -->
            <div class="card glass mb-4">
                <div class="card-header">
                    <h5 class="mb-0">University Performance Summary</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>University</th>
                                    <th>Students</th>
                                    <th>Loans</th>
                                    <th>Disbursed (P)</th>
                                    <th>Repaid (P)</th>
                                    <th>Repayment Rate</th>
                                    <th>Defaults</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $uni): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($uni['university_name']) ?></td>
                                        <td><?= number_format($uni['total_students']) ?></td>
                                        <td><?= number_format($uni['total_loans']) ?></td>
                                        <td><?= number_format($uni['total_disbursed'], 2) ?></td>
                                        <td><?= number_format($uni['total_repaid'], 2) ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?= $uni['repayment_rate'] ?>%;" 
                                                     aria-valuenow="<?= $uni['repayment_rate'] ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    <?= number_format($uni['repayment_rate'], 1) ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-danger"><?= number_format($uni['defaulted_amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        <?php elseif ($reportType === 'delinquency'): ?>
            <!-- Delinquency Report -->
            <div class="card glass mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Delinquency Report</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>University</th>
                                    <th>Loan Amount</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Due Date</th>
                                    <th>Days Overdue</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $loan): ?>
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
                                        <td><?= htmlspecialchars($loan['university_name']) ?></td>
                                        <td><?= number_format($loan['amount'], 2) ?></td>
                                        <td><?= number_format($loan['amount_paid'], 2) ?></td>
                                        <td class="fw-bold"><?= number_format($loan['outstanding_balance'], 2) ?></td>
                                        <td><?= date('M d, Y', strtotime($loan['due_date'])) ?></td>
                                        <td class="<?= $loan['days_overdue'] > 60 ? 'text-danger' : 'text-warning' ?>">
                                            <?= $loan['days_overdue'] ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $loan['delinquency_status'] === 'Current' ? 'bg-success' : 'bg-danger' ?>">
                                                <?= $loan['delinquency_status'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        <?php elseif ($reportType === 'student'): ?>
            <!-- Student Performance Report -->
            <div class="card glass mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Student Performance Report</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>University</th>
                                    <th>Loans</th>
                                    <th>Borrowed (P)</th>
                                    <th>Repaid (P)</th>
                                    <th>Repayment Rate</th>
                                    <th>Risk Score</th>
                                    <th>Loan Limit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $student): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar bg-primary me-2">
                                                    <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($student['full_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($student['student_id']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($student['university_name']) ?></td>
                                        <td><?= number_format($student['total_loans']) ?></td>
                                        <td><?= number_format($student['total_borrowed'], 2) ?></td>
                                        <td><?= number_format($student['total_repaid'], 2) ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?= $student['repayment_rate'] ?>%;" 
                                                     aria-valuenow="<?= $student['repayment_rate'] ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    <?= number_format($student['repayment_rate'], 1) ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-<?= $student['ai_risk_score'] < 40 ? 'success' : ($student['ai_risk_score'] < 70 ? 'warning' : 'danger') ?>" 
                                                     role="progressbar" 
                                                     style="width: <?= $student['ai_risk_score'] ?>%;" 
                                                     aria-valuenow="<?= $student['ai_risk_score'] ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    <?= number_format($student['ai_risk_score'], 1) ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= number_format($student['dynamic_loan_limit'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
        // Initialize DataTables
        $(document).ready(function() {
            $('table').DataTable({
                responsive: true,
                dom: '<"top"f>rt<"bottom"lip><"clear">',
                pageLength: 25
            });
        });
        
        // Charts for Overview Report
        <?php if ($reportType === 'overview' && isset($chartData['status'])): ?>
        // Loan Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: <?= json_encode($chartData['status']) ?>,
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
                                return context.label + ': ' + context.raw;
                            }
                        }
                    }
                }
            }
        });
        
        // Monthly Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        const trendsChart = new Chart(trendsCtx, {
            type: 'line',
            data: <?= json_encode($chartData['monthly']) ?>,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label.includes('(P)')) {
                                    return label + ': ' + context.raw.toFixed(2);
                                }
                                return label + ': ' + context.raw;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'white'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'white',
                            callback: function(value) {
                                return 'P' + value;
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        // Update report when filters change
        document.getElementById('timeframe').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('report').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('university').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>