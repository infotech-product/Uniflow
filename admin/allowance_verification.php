<?php
// allowance_verification.php - Student Allowance Verification Dashboard
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Start session and check authentication
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

// Get database connection
$pdo = DatabaseConnection::getInstance()->getConnection();
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_allowance'])) {
        $student_id = $_POST['student_id'];
        $amount = $_POST['amount'];
        $disbursement_date = $_POST['disbursement_date'];
        
        try {
            // First get the university_id for the student
            $stmt = $pdo->prepare("SELECT university_id FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student && isset($student['university_id'])) {
                // Now insert the verification record
                $stmt = $pdo->prepare("INSERT INTO allowance_verification_logs 
                                      (student_id, university_id, amount, disbursement_date, verified_by) 
                                      VALUES (?, ?, ?, ?, 'admin')");
                $stmt->execute([
                    $student_id,
                    $student['university_id'],
                    $amount,
                    $disbursement_date
                ]);
                
                $_SESSION['success_message'] = "Allowance successfully verified for student ID: $student_id";
            } else {
                $_SESSION['error_message'] = "Error: Could not determine university for student ID: $student_id";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error verifying allowance: " . $e->getMessage();
        }
    }
}

// Get pending verifications
$pending_verifications = [];
try {
    $stmt = $pdo->query("SELECT v.*, s.full_name, s.student_id AS student_number, u.name AS university_name 
                         FROM allowance_verification_logs v
                         JOIN students s ON v.student_id = s.id
                         JOIN universities u ON v.university_id = u.id
                         WHERE v.verified_by = 'system'");
    $pending_verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching pending verifications: " . $e->getMessage();
}

// Get verification history
$verification_history = [];
try {
    $stmt = $pdo->query("SELECT v.*, s.full_name, s.student_id AS student_number, u.name AS university_name 
                         FROM allowance_verification_logs v
                         JOIN students s ON v.student_id = s.id
                         JOIN universities u ON v.university_id = u.id
                         ORDER BY v.created_at DESC
                         LIMIT 50");
    $verification_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching verification history: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Allowance Verification | Uniflow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
        .verification-card {
            transition: all 0.3s ease;
            border-left: 5px solid transparent;
        }
        .verification-card.system {
            border-left-color: #4ECDC4;
        }
        .verification-card.admin {
            border-left-color: #FF6B6B;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-system {
            background-color: rgba(78, 205, 196, 0.2);
            color: #4ECDC4;
        }
        .badge-admin {
            background-color: rgba(255, 107, 107, 0.2);
            color: #FF6B6B;
        }
        .search-box {
            position: relative;
        }
        .search-box .bi {
            position: absolute;
            top: 12px;
            left: 15px;
            color: rgba(255, 255, 255, 0.7);
        }
        .search-box input {
            padding-left: 40px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        .search-box input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        .table-responsive {
            border-radius: 15px;
            overflow: hidden;
        }
        .table {
            background: rgba(255, 255, 255, 0.05);
            margin-bottom: 0;
        }
        .table th {
            background: rgba(0, 0, 0, 0.2);
            color: rgba(255, 255, 255, 0.8);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .table td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animated"></div>

    <!-- Navigation -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/Uniflow/admin/navbar.php'; ?>

    <div class="container py-5">
        <!-- Page Header -->
        <div class="welcome-section glass mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">Allowance Verification</h1>
                    <p class="welcome-subtitle">Verify student allowance disbursements and view verification history</p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#verifyModal">
                        <i class="bi bi-check-circle-fill me-2"></i>Verify Allowance
                    </button>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success glass" role="alert">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger glass" role="alert">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Pending Verifications -->
            <div class="col-lg-6">
                <div class="dashboard-card glass visible mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-hourglass-split me-2"></i>Pending Verifications</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($pending_verifications)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>University</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_verifications as $verification): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($verification['full_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($verification['student_number']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($verification['university_name']); ?></td>
                                                <td>P<?php echo number_format($verification['amount'], 2); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($verification['disbursement_date'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-success" 
                                                            onclick="prepareVerification(
                                                                '<?php echo $verification['student_id']; ?>',
                                                                '<?php echo $verification['amount']; ?>',
                                                                '<?php echo $verification['disbursement_date']; ?>'
                                                            )">
                                                        <i class="bi bi-check"></i> Verify
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-check-circle-fill display-4 text-success mb-3"></i>
                                <h5>No pending verifications</h5>
                                <p class="text-muted">All system-generated verifications have been processed</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Manual Verification Form -->
            <div class="col-lg-6">
                <div class="dashboard-card glass visible mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Manual Verification</h5>
                    </div>
                    <div class="card-body">
                        <form id="manualVerificationForm" method="POST">
                            <div class="mb-3">
                                <label for="studentSearch" class="form-label">Student ID or Name</label>
                                <div class="search-box">
                                    <i class="bi bi-search"></i>
                                    <input type="text" class="form-control" id="studentSearch" placeholder="Search students...">
                                </div>
                                <div id="studentResults" class="mt-2 d-none">
                                    <div class="list-group glass" id="studentResultsList"></div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="amount" class="form-label">Amount (Pula)</label>
                                    <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="disbursement_date" class="form-label">Disbursement Date</label>
                                    <input type="date" class="form-control" id="disbursement_date" name="disbursement_date" required>
                                </div>
                            </div>
                            
                            <input type="hidden" id="student_id" name="student_id">
                            <button type="submit" name="verify_allowance" class="btn btn-primary-custom w-100">
                                <i class="bi bi-check-circle-fill me-2"></i>Verify Allowance
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Verification History -->
        <div class="dashboard-card glass visible mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Verification History</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>University</th>
                                <th>Amount</th>
                                <th>Disbursement Date</th>
                                <th>Verified By</th>
                                <th>Date Verified</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($verification_history as $record): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($record['full_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($record['student_number']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['university_name']); ?></td>
                                    <td>P<?php echo number_format($record['amount'], 2); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($record['disbursement_date'])); ?></td>
                                    <td>
                                        <span class="status-badge badge-<?php echo strtolower($record['verified_by']); ?>">
                                            <?php echo ucfirst($record['verified_by']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($record['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Verification Modal -->
    <div class="modal fade" id="verifyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-dark">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Confirm Allowance Verification</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="verificationForm" method="POST">
                        <input type="hidden" id="modal_student_id" name="student_id">
                        <input type="hidden" id="modal_amount" name="amount">
                        <input type="hidden" id="modal_disbursement_date" name="disbursement_date">
                        
                        <div class="mb-3">
                            <p>You are about to verify the following allowance:</p>
                            <ul class="list-group list-group-flush glass mb-3">
                                <li class="list-group-item d-flex justify-content-between glass">
                                    <span>Student ID:</span>
                                    <span id="modal_student_info"></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between glass">
                                    <span>Amount:</span>
                                    <span id="modal_amount_display"></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between glass">
                                    <span>Disbursement Date:</span>
                                    <span id="modal_date_display"></span>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="verify_allowance" class="btn btn-success">
                                <i class="bi bi-check-circle-fill me-2"></i>Confirm Verification
                            </button>
                            <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle-fill me-2"></i>Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Set today's date as default for disbursement date
        document.getElementById('disbursement_date').valueAsDate = new Date();
        
        // Student search functionality
        $('#studentSearch').on('input', function() {
            const query = $(this).val().trim();
            if (query.length > 2) {
                $.ajax({
                    url: '/Uniflow/admin/api/search_students.php',
                    method: 'GET',
                    data: { query: query },
                    dataType: 'json',
                    success: function(data) {
                        const resultsList = $('#studentResultsList');
                        resultsList.empty();
                        
                        if (data.length > 0) {
                            data.forEach(student => {
                                resultsList.append(`
                                    <a href="#" class="list-group-item list-group-item-action" 
                                       onclick="selectStudent(${student.id}, '${student.student_id}', '${student.full_name.replace(/'/g, "\\'")}')">
                                        <div class="d-flex justify-content-between">
                                            <span>${student.full_name}</span>
                                            <small class="text-muted">${student.student_id}</small>
                                        </div>
                                    </a>
                                `);
                            });
                            $('#studentResults').removeClass('d-none');
                        } else {
                            resultsList.append(`
                                <div class="list-group-item">
                                    No students found matching "${query}"
                                </div>
                            `);
                            $('#studentResults').removeClass('d-none');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error searching students:", error);
                    }
                });
            } else {
                $('#studentResults').addClass('d-none');
            }
        });
        
        function selectStudent(id, studentId, fullName) {
            $('#student_id').val(id);
            $('#studentSearch').val(`${fullName} (${studentId})`);
            $('#studentResults').addClass('d-none');
        }
        
        function prepareVerification(studentId, amount, disbursementDate) {
            $('#modal_student_id').val(studentId);
            $('#modal_amount').val(amount);
            $('#modal_disbursement_date').val(disbursementDate);
            
            // Get student info via AJAX
            $.ajax({
                url: '/Uniflow/admin/api/get_student_info.php',
                method: 'GET',
                data: { id: studentId },
                dataType: 'json',
                success: function(data) {
                    $('#modal_student_info').text(`${data.full_name} (${data.student_id})`);
                    $('#modal_amount_display').text(`P${parseFloat(amount).toFixed(2)}`);
                    $('#modal_date_display').text(new Date(disbursementDate).toLocaleDateString());
                    
                    // Show the modal
                    var modal = new bootstrap.Modal(document.getElementById('verifyModal'));
                    modal.show();
                },
                error: function(xhr, status, error) {
                    console.error("Error fetching student info:", error);
                    alert("Error loading student information. Please try again.");
                }
            });
        }
        
        // Submit the modal form
        $('#verificationForm').on('submit', function(e) {
            // Copy values to the main form
            $('#student_id').val($('#modal_student_id').val());
            $('#amount').val($('#modal_amount').val());
            $('#disbursement_date').val($('#modal_disbursement_date').val());
            
            // Submit the manual verification form
            $('#manualVerificationForm').submit();
        });
    </script>
</body>
</html>