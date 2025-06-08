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
$all_courses = [];
$completed_courses = [];
$pending_courses = [];

try {
    $pdo = DatabaseConnection::getInstance()->getConnection();
    
    // Get student info
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_id, s.full_name, s.university_id,
               u.name AS university_name, gp.points AS gamification_points
        FROM students s
        JOIN universities u ON s.university_id = u.id
        LEFT JOIN gamification_points gp ON s.id = gp.student_id
        WHERE s.id = ?
    ");
    $stmt->execute([$_SESSION['student_db_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception("Student not found");
    }
    
    // Get all available courses (university-specific + general)
    $stmt = $pdo->prepare("
        SELECT ac.*, 
               CASE WHEN scc.student_id IS NOT NULL THEN 1 ELSE 0 END AS is_completed
        FROM academy_courses ac
        LEFT JOIN student_course_completion scc ON ac.id = scc.course_id AND scc.student_id = ?
        WHERE ac.university_id IS NULL OR ac.university_id = ?
        ORDER BY ac.university_id DESC, ac.title ASC
    ");
    $stmt->execute([$_SESSION['student_db_id'], $student['university_id']]);
    $all_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separate completed and pending courses
    $completed_courses = array_filter($all_courses, function($course) {
        return $course['is_completed'] == 1;
    });
    
    $pending_courses = array_filter($all_courses, function($course) {
        return $course['is_completed'] == 0;
    });
    
    // Handle course completion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_course'])) {
        $course_id = (int)$_POST['course_id'];
        
        // Verify course exists and is available to student
        $stmt = $pdo->prepare("
            SELECT id, points_reward 
            FROM academy_courses 
            WHERE id = ? AND (university_id IS NULL OR university_id = ?)
        ");
        $stmt->execute([$course_id, $student['university_id']]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            throw new Exception("Course not found or not available");
        }
        
        // Check if already completed
        $stmt = $pdo->prepare("
            SELECT 1 FROM student_course_completion 
            WHERE student_id = ? AND course_id = ?
        ");
        $stmt->execute([$_SESSION['student_db_id'], $course_id]);
        
        if ($stmt->fetch()) {
            throw new Exception("You've already completed this course");
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Record completion
        $stmt = $pdo->prepare("
            INSERT INTO student_course_completion 
            (student_id, course_id, points_earned)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['student_db_id'],
            $course_id,
            $course['points_reward']
        ]);
        
        // Award gamification points
        $stmt = $pdo->prepare("
            INSERT INTO gamification_points (student_id, university_id, points, last_activity)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            points = points + VALUES(points),
            last_activity = NOW()
        ");
        $stmt->execute([
            $_SESSION['student_db_id'],
            $student['university_id'],
            $course['points_reward']
        ]);
        
        // Improve financial profile score
        $stmt = $pdo->prepare("
            UPDATE student_financial_profiles 
            SET ai_risk_score = GREATEST(ai_risk_score - 1, 0),
                dynamic_loan_limit = LEAST(dynamic_loan_limit + 25, 2000)
            WHERE student_id = ?
        ");
        $stmt->execute([$_SESSION['student_db_id']]);
        
        $pdo->commit();
        
        $success = "Course completed! You earned " . $course['points_reward'] . " points.";
        
        // Refresh course data
        header("Location: academy.php");
        exit();
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    error_log("Academy page error: " . $e->getMessage());
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Academy - UniFlow Student Loans</title>
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

        /* Course Cards */
        .course-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            height: 100%;
            position: relative;
        }

        .course-card:hover {
            transform: translateY(-5px);
            border-color: rgba(255, 107, 107, 0.5);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .course-card.completed {
            border-color: rgba(40, 167, 69, 0.5);
            background: rgba(40, 167, 69, 0.1);
        }

        .course-card.completed::after {
            content: '\f058';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 15px;
            right: 15px;
            color: #28a745;
            font-size: 1.5rem;
        }

        .course-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 107, 107, 0.2);
            color: #FF6B6B;
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .course-points {
            display: inline-block;
            background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        /* Progress Bar */
        .progress-container {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .progress {
            height: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            margin: 15px 0;
        }

        .progress-bar {
            background: linear-gradient(90deg, #FF6B6B, #4ECDC4);
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
            background: rgb(255, 255, 255);
            border-color: rgba(40, 167, 69, 0.3);
            color: #28A745;
        }

        .alert-danger {
            background: rgb(255, 255, 255);
            border-color: rgba(220, 53, 69, 0.3);
            color:rgb(255, 0, 25);
        }

        /* Badges */
        .badge-custom {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 50px;
        }
        .course-section {
            margin-bottom: 40px;
        }
        
        .section-header {
            position: relative;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        
        .section-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, rgba(255,107,107,0.5), rgba(78,205,196,0.5));
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .course-card {
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
            <div class="col-lg-10 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <h1 class="text-center" style="background: linear-gradient(135deg, #ffffff, #e0e7ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                        <i class="fas fa-graduation-cap me-3"></i> Financial Academy
                    </h1>
                    <div class="text-end">
                        <span class="badge-custom">
                            <i class="fas fa-coins me-2"></i>
                            <?php echo isset($student['gamification_points']) ? (int)$student['gamification_points'] : 0; ?> Points
                        </span>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success mb-4">
                        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Progress Section -->
                <div class="progress-container glass mb-5">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h4 class="mb-3">Your Learning Progress</h4>
                            <p class="text-muted">
                                Complete courses to earn points and improve your financial profile.
                            </p>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between mb-2">
                                <span>
                                    <?php echo count($completed_courses); ?> of <?php echo count($all_courses); ?> courses completed
                                </span>
                                <span>
                                    <?php echo count($all_courses) > 0 ? round((count($completed_courses) / count($all_courses)) * 100) : 0; ?>%
                                </span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo count($all_courses) > 0 ? (count($completed_courses) / count($all_courses)) * 100 : 0; ?>%" 
                                     aria-valuenow="<?php echo count($all_courses) > 0 ? (count($completed_courses) / count($all_courses)) * 100 : 0; ?>" 
                                     aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Courses Section -->
                <div class="course-section">
                    <div class="section-header">
                        <h3><i class="fas fa-book-open me-2"></i> Available Courses</h3>
                        <p class="text-muted">Complete these courses to earn points and improve your financial profile</p>
                    </div>
                    
                    <?php if (!empty($pending_courses)): ?>
                        <div class="row">
                            <?php foreach ($pending_courses as $course): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="course-card">
                                        <?php if ($course['university_id'] == $student['university_id']): ?>
                                            <span class="course-badge"><?php echo htmlspecialchars($student['university_name']); ?></span>
                                        <?php endif; ?>
                                        
                                        <h4 class="mb-3"><?php echo htmlspecialchars($course['title']); ?></h4>
                                        <p class="text-muted mb-4"><?php echo htmlspecialchars($course['description']); ?></p>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="course-points">
                                                    <i class="fas fa-coins me-1"></i>
                                                    <?php echo (int)$course['points_reward']; ?> pts
                                                </span>
                                                <?php if ($course['duration_minutes']): ?>
                                                    <span class="text-muted ms-3">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo (int)$course['duration_minutes']; ?> min
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                <button type="submit" name="complete_course" class="btn btn-primary-custom btn-sm">
                                                    <i class="fas fa-check me-1"></i> Mark Complete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state glass">
                            <i class="fas fa-check-circle"></i>
                            <h4>No Pending Courses</h4>
                            <p class="text-muted">You've completed all available courses!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Completed Courses Section -->
                <div class="course-section">
                    <div class="section-header">
                        <h3><i class="fas fa-trophy me-2"></i> Completed Courses</h3>
                        <p class="text-muted">Courses you've successfully completed</p>
                    </div>
                    
                    <?php if (!empty($completed_courses)): ?>
                        <div class="row">
                            <?php foreach ($completed_courses as $course): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="course-card completed">
                                        <?php if ($course['university_id'] == $student['university_id']): ?>
                                            <span class="course-badge"><?php echo htmlspecialchars($student['university_name']); ?></span>
                                        <?php endif; ?>
                                        
                                        <h4 class="mb-3"><?php echo htmlspecialchars($course['title']); ?></h4>
                                        <p class="text-muted mb-4"><?php echo htmlspecialchars($course['description']); ?></p>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="course-points">
                                                    <i class="fas fa-coins me-1"></i>
                                                    <?php echo (int)$course['points_reward']; ?> pts
                                                </span>
                                            </div>
                                            <span class="text-success">
                                                <i class="fas fa-check-circle me-1"></i> Completed
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state glass">
                            <i class="fas fa-book-open"></i>
                            <h4>No Completed Courses Yet</h4>
                            <p class="text-muted">Start learning by completing available courses above</p>
                        </div>
                    <?php endif; ?>
                </div>
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
        // Add any interactive elements here if needed
        document.addEventListener('DOMContentLoaded', function() {
            // Example: Tooltips for course cards
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>