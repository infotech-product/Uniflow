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
$profile = [];
$universities = [];

try {
    $pdo = DatabaseConnection::getInstance()->getConnection();
    
    // Get student info
    $stmt = $pdo->prepare("
        SELECT s.*, u.name AS university_name, u.location, 
               sfp.ai_risk_score, sfp.dynamic_loan_limit, sfp.allowance_day,
               gp.points AS gamification_points
        FROM students s
        JOIN universities u ON s.university_id = u.id
        LEFT JOIN student_financial_profiles sfp ON s.id = sfp.student_id
        LEFT JOIN gamification_points gp ON s.id = gp.student_id
        WHERE s.id = ?
    ");
    $stmt->execute([$_SESSION['student_db_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception("Student not found");
    }
    
    // Get all universities for dropdown
    $stmt = $pdo->prepare("SELECT id, name FROM universities ORDER BY name");
    $stmt->execute();
    $universities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();
        
        try {
            if (isset($_POST['update_personal_info'])) {
                // Validate and update personal info
                $full_name = trim($_POST['full_name']);
                $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
                $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
                
                if (empty($full_name)) {
                    throw new Exception("Full name is required");
                }
                
                if (!$email) {
                    throw new Exception("Invalid email address");
                }
                
                $stmt = $pdo->prepare("
                    UPDATE students 
                    SET full_name = ?, email = ?, phone = ?
                    WHERE id = ?
                ");
                $stmt->execute([$full_name, $email, $phone, $_SESSION['student_db_id']]);
                
                $success = "Personal information updated successfully";
                
            } elseif (isset($_POST['update_password'])) {
                // Password change
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                // Verify current password
                $stmt = $pdo->prepare("SELECT password_hash FROM students WHERE id = ?");
                $stmt->execute([$_SESSION['student_db_id']]);
                $db_password = $stmt->fetchColumn();
                
                if (!password_verify($current_password, $db_password)) {
                    throw new Exception("Current password is incorrect");
                }
                
                if ($new_password !== $confirm_password) {
                    throw new Exception("New passwords don't match");
                }
                
                if (strlen($new_password) < 8) {
                    throw new Exception("Password must be at least 8 characters");
                }
                
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE students SET password_hash = ? WHERE id = ?");
                $stmt->execute([$new_hash, $_SESSION['student_db_id']]);
                
                $success = "Password updated successfully";
                
                // Log out all other sessions
                $_SESSION['password_changed'] = time();
                
            } elseif (isset($_POST['update_preferences'])) {
                // Update preferences
                $allowance_day = isset($_POST['allowance_day']) ? (int)$_POST['allowance_day'] : null;
                
                if ($allowance_day && ($allowance_day < 1 || $allowance_day > 28)) {
                    throw new Exception("Allowance day must be between 1 and 28");
                }
                
                $stmt = $pdo->prepare("
                    UPDATE student_financial_profiles 
                    SET allowance_day = ?
                    WHERE student_id = ?
                ");
                $stmt->execute([$allowance_day, $_SESSION['student_db_id']]);
                
                $success = "Preferences updated successfully";
            }
            
            $pdo->commit();
            
            // Refresh student data
            header("Location: profile.php");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error = $e->getMessage();
        }
    }
    
} catch (Exception $e) {
    error_log("Profile page error: " . $e->getMessage());
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - UniFlow Student Loans</title>
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
        
        .profile-section {
            margin-bottom: 40px;
            padding: 25px;
            border-radius: 15px;
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
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
        }
        
        .profile-stat {
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.7);
        }
        
        .stat-value {
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .nav-pills .nav-link {
            color: rgba(255,255,255,0.8);
            border-radius: 10px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
        }
        
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
            color: white;
        }
        
        .nav-pills .nav-link:hover:not(.active) {
            background: rgba(255,255,255,0.1);
        }
        
        .form-control, .form-select {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,107,107,0.5);
            color: white;
            box-shadow: 0 0 0 0.25rem rgba(255,107,107,0.25);
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: rgba(255,255,255,0.6);
        }
        
        @media (max-width: 768px) {
            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 2rem;
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
            <div class="col-lg-3 mb-4">
                <div class="glass p-4 rounded text-center mb-4">
                    <div class="profile-avatar mx-auto mb-3">
                        <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                    </div>
                    <h4><?php echo htmlspecialchars($student['full_name']); ?></h4>
                    <p class="text-muted"><?php echo htmlspecialchars($student['university_name']); ?></p>
                </div>
                
                <div class="glass p-3 rounded">
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#personal" data-bs-toggle="tab">
                                <i class="fas fa-user me-2"></i> Personal Info
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#security" data-bs-toggle="tab">
                                <i class="fas fa-lock me-2"></i> Security
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#preferences" data-bs-toggle="tab">
                                <i class="fas fa-cog me-2"></i> Preferences
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#financial" data-bs-toggle="tab">
                                <i class="fas fa-chart-line me-2"></i> Financial Profile
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="glass p-3 rounded mt-3">
                    <div class="profile-stat">
                        <div class="stat-label">Risk Score</div>
                        <div class="stat-value">
                            <?php echo number_format($student['ai_risk_score'], 1); ?>%
                        </div>
                    </div>
                    <div class="profile-stat">
                        <div class="stat-label">Loan Limit</div>
                        <div class="stat-value">
                            P <?php echo number_format($student['dynamic_loan_limit'], 2); ?>
                        </div>
                    </div>
                    <div class="profile-stat">
                        <div class="stat-label">Gamification Points</div>
                        <div class="stat-value">
                            <?php echo number_format($student['gamification_points'] ?? 0); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-9">
                <div class="glass p-4 rounded">
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
                    
                    <div class="tab-content">
                        <!-- Personal Info Tab -->
                        <div class="tab-pane fade show active" id="personal">
                            <div class="section-header">
                                <h3><i class="fas fa-user me-2"></i> Personal Information</h3>
                                <p class="text-muted">Update your personal details</p>
                            </div>
                            
                            <form method="POST">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="student_id" class="form-label">Student ID</label>
                                        <input type="text" class="form-control" id="student_id" 
                                               value="<?php echo htmlspecialchars($student['student_id']); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="university" class="form-label">University</label>
                                        <input type="text" class="form-control" id="university" 
                                               value="<?php echo htmlspecialchars($student['university_name']); ?>" readonly>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="full_name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="fnb_account" class="form-label">FNB Account Number</label>
                                        <input type="text" class="form-control" id="fnb_account" 
                                               value="<?php echo htmlspecialchars($student['fnb_account_number'] ?? ''); ?>" readonly>
                                    </div>
                                </div>
                                
                                <button type="submit" name="update_personal_info" class="btn btn-primary-custom">
                                    <i class="fas fa-save me-1"></i> Save Changes
                                </button>
                            </form>
                        </div>
                        
                        <!-- Security Tab -->
                        <div class="tab-pane fade" id="security">
                            <div class="section-header">
                                <h3><i class="fas fa-lock me-2"></i> Security Settings</h3>
                                <p class="text-muted">Change your password and manage security</p>
                            </div>
                            
                            <form method="POST">
                                <div class="row mb-3">
                                    <div class="col-md-12 position-relative">
                                        <label for="current_password" class="form-label">Current Password *</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <span class="password-toggle" onclick="togglePassword('current_password')">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6 position-relative">
                                        <label for="new_password" class="form-label">New Password *</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <span class="password-toggle" onclick="togglePassword('new_password')">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                        <div class="form-text">Minimum 8 characters</div>
                                    </div>
                                    <div class="col-md-6 position-relative">
                                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="logout_all" name="logout_all">
                                        <label class="form-check-label" for="logout_all">
                                            Log out of all other devices
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" name="update_password" class="btn btn-primary-custom">
                                    <i class="fas fa-key me-1"></i> Change Password
                                </button>
                            </form>
                        </div>
                        
                        <!-- Preferences Tab -->
                        <div class="tab-pane fade" id="preferences">
                            <div class="section-header">
                                <h3><i class="fas fa-cog me-2"></i> Preferences</h3>
                                <p class="text-muted">Configure your account preferences</p>
                            </div>
                            
                            <form method="POST">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="allowance_day" class="form-label">Preferred Allowance Day</label>
                                        <select class="form-select" id="allowance_day" name="allowance_day">
                                            <option value="">-- Select day --</option>
                                            <?php for ($i = 1; $i <= 28; $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo ($student['allowance_day'] == $i) ? 'selected' : ''; ?>>
                                                    <?php echo $i . date('S', strtotime("2023-01-$i")); ?> of the month
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                        <div class="form-text">When you'd prefer to receive your allowance</div>
                                    </div>
                                </div>
                                
                                <button type="submit" name="update_preferences" class="btn btn-primary-custom">
                                    <i class="fas fa-save me-1"></i> Save Preferences
                                </button>
                            </form>
                        </div>
                        
                        <!-- Financial Profile Tab -->
                        <div class="tab-pane fade" id="financial">
                            <div class="section-header">
                                <h3><i class="fas fa-chart-line me-2"></i> Financial Profile</h3>
                                <p class="text-muted">Your financial health and loan eligibility</p>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="profile-stat glass-dark mb-4">
                                        <div class="stat-label">AI Risk Score</div>
                                        <div class="stat-value">
                                            <?php echo number_format($student['ai_risk_score'], 1); ?>%
                                        </div>
                                        <div class="progress mt-2" style="height: 6px;">
                                            <div class="progress-bar bg-danger" role="progressbar" 
                                                 style="width: <?php echo $student['ai_risk_score']; ?>%" 
                                                 aria-valuenow="<?php echo $student['ai_risk_score']; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <div class="form-text mt-2">
                                            Lower scores indicate better financial health
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="profile-stat glass-dark mb-4">
                                        <div class="stat-label">Dynamic Loan Limit</div>
                                        <div class="stat-value">
                                            P <?php echo number_format($student['dynamic_loan_limit'], 2); ?>
                                        </div>
                                        <div class="progress mt-2" style="height: 6px;">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: <?php echo ($student['dynamic_loan_limit'] / 2000) * 100; ?>%" 
                                                 aria-valuenow="<?php echo $student['dynamic_loan_limit']; ?>" 
                                                 aria-valuemin="0" aria-valuemax="2000"></div>
                                        </div>
                                        <div class="form-text mt-2">
                                            Maximum: P 2,000.00
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="glass-dark p-4 rounded mb-4">
                                <h5 class="mb-3"><i class="fas fa-lightbulb me-2"></i> How to Improve Your Score</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item bg-transparent text-white border-secondary">
                                        <i class="fas fa-check-circle text-success me-2"></i> Complete financial literacy courses
                                    </li>
                                    <li class="list-group-item bg-transparent text-white border-secondary">
                                        <i class="fas fa-check-circle text-success me-2"></i> Make loan repayments on time
                                    </li>
                                    <li class="list-group-item bg-transparent text-white border-secondary">
                                        <i class="fas fa-check-circle text-success me-2"></i> Maintain a good repayment history
                                    </li>
                                    <li class="list-group-item bg-transparent text-white border-secondary">
                                        <i class="fas fa-check-circle text-success me-2"></i> Avoid multiple loan applications
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
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
        // Toggle password visibility
        function togglePassword(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
        
        // Initialize Bootstrap tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Show first tab if hash is empty
            if (!window.location.hash) {
                const firstTab = document.querySelector('.nav-pills .nav-link');
                if (firstTab) {
                    new bootstrap.Tab(firstTab).show();
                }
            }
        });
    </script>
</body>
</html>