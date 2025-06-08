<?php
// student_details.php - Student Detailed View
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Start session and check admin authentication
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

// Get student ID from URL
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($student_id <= 0) {
    header('Location: students.php');
    exit();
}

// Get database connection
$pdo = DatabaseConnection::getInstance()->getConnection();

// Get student details
$student = [];
try {
    $stmt = $pdo->prepare("SELECT 
                            s.*,
                            u.name AS university_name,
                            u.location AS university_location
                          FROM students s
                          JOIN universities u ON s.university_id = u.id
                          WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        header('Location: students.php');
        exit();
    }
} catch (PDOException $e) {
    $error_message = "Error fetching student details: " . $e->getMessage();
}

// Get student's loan history
$loans = [];
try {
    $stmt = $pdo->prepare("SELECT 
                            l.*,
                            SUM(r.amount) AS amount_paid
                          FROM loans l
                          LEFT JOIN repayments r ON l.id = r.loan_id
                          WHERE l.student_id = ?
                          GROUP BY l.id
                          ORDER BY l.created_at DESC");
    $stmt->execute([$student_id]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching loan history: " . $e->getMessage();
}

// Get student's course completions
$courses = [];
try {
    $stmt = $pdo->prepare("SELECT 
                            c.title,
                            c.points_reward,
                            cc.completed_at
                          FROM student_course_completion cc
                          JOIN academy_courses c ON cc.course_id = c.id
                          WHERE cc.student_id = ?
                          ORDER BY cc.completed_at DESC");
    $stmt->execute([$student_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching course completions: " . $e->getMessage();
}

// Get gamification points
$points = 0;
try {
    $stmt = $pdo->prepare("SELECT points FROM gamification_points WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $points = $result ? $result['points'] : 0;
} catch (PDOException $e) {
    $error_message = "Error fetching gamification points: " . $e->getMessage();
}

// Get financial profile
$financial_profile = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM student_financial_profiles WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $financial_profile = $stmt->fetch(PDO::FETCH_ASSOC) ?? [];
} catch (PDOException $e) {
    $error_message = "Error fetching financial profile: " . $e->getMessage();
}

// Handle student verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_student'])) {
    try {
        $stmt = $pdo->prepare("UPDATE students SET is_verified = 1 WHERE id = ?");
        $stmt->execute([$student_id]);
        
        $_SESSION['success_message'] = "Student verified successfully";
        header("Location: student_details.php?id=$student_id");
        exit();
    } catch (PDOException $e) {
        $error_message = "Error verifying student: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student['full_name']); ?> | Uniflow</title>
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
   
        .student-header {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .detail-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .badge-verified {
            background-color: rgba(78, 205, 196, 0.2);
            color: #4ECDC4;
        }
        .badge-unverified {
            background-color: rgba(255, 107, 107, 0.2);
            color: #FF6B6B;
        }
        .badge-loan {
            background-color: rgba(106, 141, 255, 0.2);
            color: #6A8DFF;
        }
        .badge-points {
            background-color: rgba(255, 193, 160, 0.2);
            color: #FFC3A0;
        }
        .loan-card {
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        .loan-card.pending {
            border-left-color: #FFC3A0;
        }
        .loan-card.approved {
            border-left-color: #4ECDC4;
        }
        .loan-card.repaid {
            border-left-color: #6A8DFF;
        }
        .loan-card.defaulted {
            border-left-color: #FF6B6B;
        }
        .loan-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .progress-container {
            height: 10px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .progress-bar {
            background: linear-gradient(90deg, #FF6B6B, #FF8E53);
            border-radius: 5px;
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
        .risk-meter {
            height: 10px;
            background: linear-gradient(to right, #4ECDC4, #FFC3A0, #FF6B6B);
            border-radius: 5px;
            margin-top: 5px;
        }
        .risk-indicator {
            width: 15px;
            height: 15px;
            background-color: white;
            border-radius: 50%;
            position: absolute;
            top: -2px;
            transform: translateX(-50%);
            box-shadow: 0 0 5px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animated"></div>

    <!-- Navigation -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/Uniflow/admin/navbar.php'; ?>

    <div class="container py-5">
        <!-- Alerts -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success glass" role="alert">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger glass" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Student Header -->
        <div class="student-header glass">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title"><?php echo htmlspecialchars($student['full_name']); ?></h1>
                    <p class="welcome-subtitle mb-2">
                        <span class="badge <?php echo $student['is_verified'] ? 'badge-verified' : 'badge-unverified'; ?> me-2">
                            <?php echo $student['is_verified'] ? 'Verified' : 'Unverified'; ?>
                        </span>
                        <span class="badge badge-loan me-2">
                            <?php echo count($loans); ?> Loans
                        </span>
                        <span class="badge badge-points">
                            <?php echo $points; ?> Points
                        </span>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="students.php" class="btn btn-outline-custom">
                        <i class="bi bi-arrow-left me-2"></i>Back to Students
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-6">
                <!-- Student Details Card -->
                <div class="detail-card glass">
                    <h5 class="mb-4"><i class="bi bi-person-lines-fill me-2"></i>Student Information</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">Student ID</p>
                            <h6><?php echo htmlspecialchars($student['student_id']); ?></h6>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">FNB Account</p>
                            <h6><?php echo htmlspecialchars($student['fnb_account_number']); ?></h6>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">University</p>
                            <h6><?php echo htmlspecialchars($student['university_name']); ?></h6>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">Location</p>
                            <h6><?php echo htmlspecialchars($student['university_location']); ?></h6>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">Registered</p>
                            <h6><?php echo date('M d, Y', strtotime($student['created_at'])); ?></h6>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">Last Login</p>
                            <h6><?php echo $student['last_login_device'] ? date('M d, Y', strtotime($student['last_login_device'])) : 'Never'; ?></h6>
                        </div>
                    </div>
                    <?php if (!$student['is_verified']): ?>
                        <form method="POST">
                            <button type="submit" name="verify_student" class="btn btn-success w-100 mt-3">
                                <i class="bi bi-check-circle-fill me-2"></i>Verify Student
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Financial Profile Card -->
                <div class="detail-card glass">
                    <h5 class="mb-4"><i class="bi bi-graph-up me-2"></i>Financial Profile</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">AI Risk Score</p>
                            <h6><?php echo $financial_profile['ai_risk_score'] ?? 'N/A'; ?></h6>
                            <?php if (isset($financial_profile['ai_risk_score'])): ?>
                                <div class="risk-meter">
                                    <div class="risk-indicator" style="left: <?php echo $financial_profile['ai_risk_score']; ?>%;"></div>
                                </div>
                                <small class="text-muted">0 (Low Risk) — 100 (High Risk)</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">Loan Limit</p>
                            <h6>P<?php echo number_format($financial_profile['dynamic_loan_limit'] ?? 0, 2); ?></h6>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">Allowance Day</p>
                            <h6><?php echo $financial_profile['allowance_day'] ?? 'Not set'; ?></h6>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">Emergency Topup</p>
                            <h6><?php echo isset($financial_profile['emergency_topup_unlocked']) ? ($financial_profile['emergency_topup_unlocked'] ? 'Enabled' : 'Disabled') : 'N/A'; ?></h6>
                        </div>
                    </div>
                    <div class="mb-3">
                        <p class="mb-1 text-muted">Last Loan Date</p>
                        <h6><?php echo $financial_profile['last_loan_date'] ? date('M d, Y', strtotime($financial_profile['last_loan_date'])) : 'Never'; ?></h6>
                    </div>
                </div>

                <!-- Course Completions -->
                <div class="detail-card glass">
                    <h5 class="mb-4"><i class="bi bi-award me-2"></i>Completed Courses</h5>
                    <?php if (!empty($courses)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th>Points</th>
                                        <th>Completed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $course): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($course['title']); ?></td>
                                            <td><?php echo $course['points_reward']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($course['completed_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-journal-x display-4 text-muted mb-3"></i>
                            <h5>No Courses Completed</h5>
                            <p class="text-muted">This student hasn't completed any financial courses yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-6">
                <!-- Loan History -->
                <div class="detail-card glass">
                    <h5 class="mb-4"><i class="bi bi-cash-stack me-2"></i>Loan History</h5>
                    <?php if (!empty($loans)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Loan ID</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($loans as $loan): ?>
                                        <tr class="loan-card <?php echo $loan['status']; ?>" onclick="window.location='loan_details.php?id=<?php echo $loan['id']; ?>'" style="cursor: pointer;">
                                            <td>#<?php echo $loan['id']; ?></td>
                                            <td>P<?php echo number_format($loan['amount'], 2); ?></td>
                                            <td>
                                                <?php 
                                                $badge_class = '';
                                                switch($loan['status']) {
                                                    case 'pending': $badge_class = 'badge-pending'; break;
                                                    case 'approved': $badge_class = 'badge-approved'; break;
                                                    case 'repaid': $badge_class = 'badge-repaid'; break;
                                                    case 'defaulted': $badge_class = 'badge-defaulted'; break;
                                                    case 'rejected': $badge_class = 'badge-rejected'; break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($loan['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($loan['status'] === 'approved' || $loan['status'] === 'repaid'): ?>
                                                    <?php 
                                                    $progress = ($loan['amount_paid'] / $loan['amount']) * 100;
                                                    $progress = min(100, $progress);
                                                    ?>
                                                    <div class="progress-container">
                                                        <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
                                                    </div>
                                                    <small><?php echo number_format($progress, 1); ?>%</small>
                                                <?php else: ?>
                                                    <small class="text-muted">N/A</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-cash display-4 text-muted mb-3"></i>
                            <h5>No Loan History</h5>
                            <p class="text-muted">This student hasn't applied for any loans yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Gamification Progress -->
                <div class="detail-card glass">
                    <h5 class="mb-4"><i class="bi bi-trophy me-2"></i>Gamification Progress</h5>
                    <div class="text-center py-3">
                        <div class="display-4 mb-3"><?php echo $points; ?> Points</div>
                        <div class="progress-container">
                            <div class="progress-bar" style="width: <?php echo min(100, ($points / 1000) * 100); ?>%"></div>
                        </div>
                        <small class="text-muted">Progress to next level (1000 points)</small>
                    </div>
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="h4"><?php echo count($loans); ?></div>
                            <small class="text-muted">Loans</small>
                        </div>
                        <div class="col-md-4">
                            <div class="h4"><?php echo count($courses); ?></div>
                            <small class="text-muted">Courses</small>
                        </div>
                        <div class="col-md-4">
                            <div class="h4"><?php echo $financial_profile['ai_risk_score'] ?? 'N/A'; ?></div>
                            <small class="text-muted">Risk Score</small>
                        </div>
                    </div>
                </div>

                <!-- Device Information -->
                <div class="detail-card glass">
                    <h5 class="mb-4"><i class="bi bi-phone me-2"></i>Device Information</h5>
                    <div class="mb-3">
                        <p class="mb-1 text-muted">Last Login Device</p>
                        <h6><?php echo $student['last_login_device'] ? htmlspecialchars($student['last_login_device']) : 'Unknown'; ?></h6>
                    </div>
                    <div class="mb-3">
                        <p class="mb-1 text-muted">Registered IP</p>
                        <h6><?php echo $student['registration_ip'] ?? 'Unknown'; ?></h6>
                    </div>
                    <div>
                        <p class="mb-1 text-muted">Last Activity</p>
                        <h6><?php echo $student['last_activity'] ? date('M d, Y H:i', strtotime($student['last_activity'])) : 'Never'; ?></h6>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>