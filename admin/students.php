<?php
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

// Handle student actions (verify/unverify, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['student_id'])) {
        $studentId = (int)$_POST['student_id'];
        $action = $_POST['action'];
        $adminId = $_SESSION['admin_id'];
        
        try {
            $pdo->beginTransaction();
            
            if ($action === 'verify') {
                $stmt = $pdo->prepare("UPDATE students SET is_verified = 1 WHERE id = ?");
                
                // Log admin action
                $logStmt = $pdo->prepare("
                    INSERT INTO admin_logs (action_type, target_id, details) 
                    VALUES ('student_verify', ?, 'Verified student account')
                ");
            } 
            elseif ($action === 'unverify') {
                $stmt = $pdo->prepare("UPDATE students SET is_verified = 0 WHERE id = ?");
                
                // Log admin action
                $logStmt = $pdo->prepare("
                    INSERT INTO admin_logs (action_type, target_id, details) 
                    VALUES ('student_unverify', ?, 'Unverified student account')
                ");
            }
            elseif ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
                
                // Log admin action
                $logStmt = $pdo->prepare("
                    INSERT INTO admin_logs (action_type, target_id, details) 
                    VALUES ('student_delete', ?, 'Deleted student account')
                ");
            }
            
            // Execute the student update
            $stmt->execute([$studentId]);
            $logStmt->execute([$studentId]);
            
            $pdo->commit();
            $_SESSION['success_message'] = "Student account successfully " . 
                ($action === 'verify' ? 'verified' : 
                 ($action === 'unverify' ? 'unverified' : 'deleted'));
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Error processing student: " . $e->getMessage();
        }
        
        header("Location: students.php");
        exit();
    }
}

// Handle student edit form submission
if (isset($_POST['edit_student'])) {
    $studentId = (int)$_POST['id'];
    $fullName = trim($_POST['full_name']);
    $studentIdNumber = trim($_POST['student_id']);
    $fnbAccount = trim($_POST['fnb_account_number']);
    $universityId = (int)$_POST['university_id'];
    $isVerified = isset($_POST['is_verified']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE students 
            SET full_name = ?, 
                student_id = ?,
                fnb_account_number = ?,
                university_id = ?,
                is_verified = ?
            WHERE id = ?
        ");
        
        $stmt->execute([$fullName, $studentIdNumber, $fnbAccount, $universityId, $isVerified, $studentId]);
        
        // Log admin action
        $logStmt = $pdo->prepare("
            INSERT INTO admin_logs (action_type, target_id, details) 
            VALUES ('student_edit', ?, 'Updated student profile')
        ");
        $logStmt->execute([$studentId]);
        
        $_SESSION['success_message'] = "Student information updated successfully";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error updating student: " . $e->getMessage();
    }
    
    header("Location: students.php");
    exit();
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$universityFilter = $_GET['university'] ?? '';

// Build base query
$query = "
    SELECT s.*, 
           u.name AS university_name,
           COUNT(l.id) AS loan_count,
           SUM(CASE WHEN l.status = 'repaid' THEN l.amount ELSE 0 END) AS total_repaid,
           SUM(CASE WHEN l.status = 'approved' AND l.amount_paid < l.amount THEN l.amount - l.amount_paid ELSE 0 END) AS outstanding_balance
    FROM students s
    LEFT JOIN universities u ON s.university_id = u.id
    LEFT JOIN loans l ON s.id = l.student_id
";

// Add filters
$whereClauses = [];
$params = [];

if ($statusFilter === 'verified') {
    $whereClauses[] = "s.is_verified = 1";
} elseif ($statusFilter === 'unverified') {
    $whereClauses[] = "s.is_verified = 0";
}

if (!empty($searchQuery)) {
    $whereClauses[] = "(s.full_name LIKE ? OR s.student_id LIKE ? OR s.fnb_account_number LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

if (!empty($universityFilter)) {
    $whereClauses[] = "u.id = ?";
    $params[] = $universityFilter;
}

if (!empty($whereClauses)) {
    $query .= " WHERE " . implode(" AND ", $whereClauses);
}

// Add grouping
$query .= " GROUP BY s.id";

// Add sorting
$query .= " ORDER BY s.created_at DESC";

// Prepare and execute
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get universities for filter dropdown
$universities = $pdo->query("SELECT id, name FROM universities ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - UniFlow Admin</title>
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
    <!-- Include navbar -->
    <?php include 'navbar.php'; ?>
    
    <div class="container py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Student Management</h1>
            <a href="dashboard.php" class="btn btn-outline-light">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
        
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <!-- Filters Card -->
        <div class="card glass mb-4">
            <div class="card-header">
                <h5 class="mb-0">Filters</h5>
            </div>
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Verification Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Students</option>
                            <option value="verified" <?= $statusFilter === 'verified' ? 'selected' : '' ?>>Verified Only</option>
                            <option value="unverified" <?= $statusFilter === 'unverified' ? 'selected' : '' ?>>Unverified Only</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="university" class="form-label">University</label>
                        <select id="university" name="university" class="form-select">
                            <option value="">All Universities</option>
                            <?php foreach ($universities as $university): ?>
                                <option value="<?= $university['id'] ?>" <?= $universityFilter == $university['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($university['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Search by name, student ID or account number" value="<?= htmlspecialchars($searchQuery) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Students Table -->
        <div class="card glass">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Student Accounts</h5>
                <span class="badge bg-primary">
                    <?= count($students) ?> <?= count($students) === 1 ? 'student' : 'students' ?>
                </span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>University</th>
                                <th>Account</th>
                                <th>Loans</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr onclick="window.location='student_details.php?id=<?php echo $student['id']; ?>'" style="cursor: pointer;">
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
                                    <td><?= htmlspecialchars($student['university_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($student['fnb_account_number'] ?? 'Not set') ?></td>
                                    <td><?= $student['loan_count'] ?></td>
                                    <td>P<?= number_format($student['outstanding_balance'] ?? 0, 2) ?></td>
                                    <td>
                                        <span class="verified-badge verified-<?= $student['is_verified'] ? 'true' : 'false' ?>">
                                            <?= $student['is_verified'] ? 'Verified' : 'Unverified' ?>
                                        </span>
                                    </td>
                                    <td class="action-btns">
                                        <!-- Verify/Unverify Buttons -->
                                        <?php if ($student['is_verified']): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                                <input type="hidden" name="action" value="unverify">
                                                <button type="submit" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-times-circle me-1"></i>Unverify
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                                <input type="hidden" name="action" value="verify">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check-circle me-1"></i>Verify
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <!-- Edit Button -->
                                        <button type="button" class="btn btn-info btn-sm" 
                                                data-bs-toggle="modal" data-bs-target="#editModal<?= $student['id'] ?>">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </button>
                                        
                                        <!-- Delete Button -->
                                        <button type="button" class="btn btn-danger btn-sm" 
                                                data-bs-toggle="modal" data-bs-target="#deleteModal<?= $student['id'] ?>">
                                            <i class="fas fa-trash-alt me-1"></i>Delete
                                        </button>
                                        
                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editModal<?= $student['id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content bg-dark text-white">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Student: <?= htmlspecialchars($student['full_name']) ?></h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <form method="post">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="edit_student" value="1">
                                                            <input type="hidden" name="id" value="<?= $student['id'] ?>">
                                                            
                                                            <div class="row g-3">
                                                                <div class="col-md-6">
                                                                    <label for="full_name<?= $student['id'] ?>" class="form-label">Full Name</label>
                                                                    <input type="text" class="form-control bg-dark text-white" id="full_name<?= $student['id'] ?>" 
                                                                           name="full_name" value="<?= htmlspecialchars($student['full_name']) ?>" required>
                                                                </div>
                                                                
                                                                <div class="col-md-6">
                                                                    <label for="student_id<?= $student['id'] ?>" class="form-label">Student ID</label>
                                                                    <input type="text" class="form-control bg-dark text-white" id="student_id<?= $student['id'] ?>" 
                                                                           name="student_id" value="<?= htmlspecialchars($student['student_id']) ?>" required>
                                                                </div>
                                                                
                                                                <div class="col-md-6">
                                                                    <label for="fnb_account_number<?= $student['id'] ?>" class="form-label">FNB Account</label>
                                                                    <input type="text" class="form-control bg-dark text-white" id="fnb_account_number<?= $student['id'] ?>" 
                                                                           name="fnb_account_number" value="<?= htmlspecialchars($student['fnb_account_number']) ?>">
                                                                </div>
                                                                
                                                                                                                               <div class="col-md-6">
                                                                    <label for="university_id<?= $student['id'] ?>" class="form-label">University</label>
                                                                    <select class="form-select bg-dark text-white" id="university_id<?= $student['id'] ?>" 
                                                                            name="university_id" required>
                                                                        <?php foreach ($universities as $university): ?>
                                                                            <option value="<?= $university['id'] ?>" 
                                                                                <?= $student['university_id'] == $university['id'] ? 'selected' : '' ?>>
                                                                                <?= htmlspecialchars($university['name']) ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                
                                                                <div class="col-md-12">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" 
                                                                               id="is_verified<?= $student['id'] ?>" 
                                                                               name="is_verified" 
                                                                               <?= $student['is_verified'] ? 'checked' : '' ?>>
                                                                        <label class="form-check-label" for="is_verified<?= $student['id'] ?>">
                                                                            Verified Account
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Delete Confirmation Modal -->
                                        <div class="modal fade" id="deleteModal<?= $student['id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content bg-dark text-white">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Confirm Deletion</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to delete this student account?</p>
                                                        <p><strong><?= htmlspecialchars($student['full_name']) ?></strong> (<?= htmlspecialchars($student['student_id']) ?>)</p>
                                                        <p class="text-danger">Warning: This action cannot be undone and will also delete all associated loan records.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form method="post">
                                                            <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button type="submit" class="btn btn-danger">Delete Permanently</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-user-graduate fa-3x mb-3 text-muted"></i>
                                        <h5>No students found</h5>
                                        <p class="text-muted">Try adjusting your filters or add new students</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.table').DataTable({
                responsive: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search students...",
                },
                dom: '<"top"f>rt<"bottom"lip><"clear">',
                pageLength: 25
            });
        });
    </script>
</body>
</html>