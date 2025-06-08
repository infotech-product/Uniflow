<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Start session and check authentication
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

// Handle loan actions (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['loan_id'])) {
        $loanId = (int)$_POST['loan_id'];
        $action = $_POST['action'];
        $adminId = $_SESSION['admin_id'];
        
        try {
            $pdo->beginTransaction();
            
            if ($action === 'approve') {
                $stmt = $pdo->prepare("
                    UPDATE loans 
                    SET status = 'approved', 
                        disbursement_date = NOW(),
                        due_date = DATE_ADD(NOW(), INTERVAL 30 DAY),
                        status_updated_at = NOW()
                    WHERE id = ? AND status = 'pending'
                ");
                
                // Log admin action
                $logStmt = $pdo->prepare("
                    INSERT INTO admin_logs (action_type, target_id, details) 
                    VALUES ('loan_approve', ?, 'Approved loan application')
                ");
            } 
            elseif ($action === 'reject') {
                $rejectionReason = $_POST['rejection_reason'] ?? 'Not specified';
                
                $stmt = $pdo->prepare("
                    UPDATE loans 
                    SET status = 'rejected', 
                        rejection_reason = ?,
                        status_updated_at = NOW()
                    WHERE id = ? AND status = 'pending'
                ");
                
                // Log admin action
                $logStmt = $pdo->prepare("
                    INSERT INTO admin_logs (action_type, target_id, details) 
                    VALUES ('loan_reject', ?, 'Rejected loan application: ' || ?)
                ");
            }
            
            // Execute the loan update
            if ($action === 'reject') {
                $stmt->execute([$rejectionReason, $loanId]);
                $logStmt->execute([$loanId, $rejectionReason]);
            } else {
                $stmt->execute([$loanId]);
                $logStmt->execute([$loanId]);
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = "Loan successfully " . ($action === 'approve' ? 'approved' : 'rejected');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Error processing loan: " . $e->getMessage();
        }
        
        header("Location: loans.php");
        exit();
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$universityFilter = $_GET['university'] ?? '';

// Build base query
$query = "
    SELECT l.*, 
           s.full_name, 
           s.student_id, 
           s.fnb_account_number,
           u.name AS university_name,
           u.id AS university_id
    FROM loans l
    JOIN students s ON l.student_id = s.id
    JOIN universities u ON l.university_id = u.id
";

// Add filters
$whereClauses = [];
$params = [];

if ($statusFilter !== 'all') {
    $whereClauses[] = "l.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $whereClauses[] = "(s.full_name LIKE ? OR s.student_id LIKE ? OR l.id = ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = $searchQuery;
}

if (!empty($universityFilter)) {
    $whereClauses[] = "u.id = ?";
    $params[] = $universityFilter;
}

if (!empty($whereClauses)) {
    $query .= " WHERE " . implode(" AND ", $whereClauses);
}

// Add sorting
$query .= " ORDER BY l.created_at DESC";

// Prepare and execute
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get universities for filter dropdown
$universities = $pdo->query("SELECT id, name FROM universities ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Management - UniFlow Admin</title>
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
            <h1 class="mb-0">Loan Management</h1>
            <a href="dashboard.php" class="btn btn-outline-light">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
        
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error_message'] ?>
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
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            <option value="repaid" <?= $statusFilter === 'repaid' ? 'selected' : '' ?>>Repaid</option>
                            <option value="defaulted" <?= $statusFilter === 'defaulted' ? 'selected' : '' ?>>Defaulted</option>
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
                               placeholder="Search by name, student ID or loan ID" value="<?= htmlspecialchars($searchQuery) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Loans Table -->
        <div class="card glass">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Loan Applications</h5>
                <span class="badge bg-primary">
                    <?= count($loans) ?> <?= count($loans) === 1 ? 'loan' : 'loans' ?>
                </span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Loan ID</th>
                                <th>Student</th>
                                <th>University</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Applied On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loans as $loan): ?>
                                <tr>
                                    <td>#<?= $loan['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar bg-primary me-2 text-white d-flex align-items-center justify-content-center" 
                                                 style="width: 36px; height: 36px; border-radius: 50%;">
                                                <?= strtoupper(substr($loan['full_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($loan['full_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($loan['student_id']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($loan['university_name']) ?></td>
                                    <td>P<?= number_format($loan['amount'], 2) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $loan['status'] ?>">
                                            <?= ucfirst($loan['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($loan['created_at'])) ?></td>
                                    <td class="action-btns">
                                        <?php if ($loan['status'] === 'pending'): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check me-1"></i>Approve
                                                </button>
                                            </form>
                                            
                                            <button type="button" class="btn btn-danger btn-sm" 
                                                    data-bs-toggle="modal" data-bs-target="#rejectModal<?= $loan['id'] ?>">
                                                <i class="fas fa-times me-1"></i>Reject
                                            </button>
                                            
                                            <!-- Reject Modal -->
                                            <div class="modal fade" id="rejectModal<?= $loan['id'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content bg-dark text-white">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Reject Loan #<?= $loan['id'] ?></h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form method="post">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                                
                                                                <div class="mb-3">
                                                                    <label for="rejectionReason<?= $loan['id'] ?>" class="form-label">Reason for Rejection</label>
                                                                    <textarea class="form-control bg-dark text-white" id="rejectionReason<?= $loan['id'] ?>" 
                                                                              name="rejection_reason" rows="3" required></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <a href="loan_details.php?id=<?= $loan['id'] ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (empty($loans)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-file-invoice-dollar fa-3x mb-3 text-muted"></i>
                            <h5>No loans found</h5>
                            <p class="text-muted">Try adjusting your filters</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('table').DataTable({
                responsive: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search...",
                    zeroRecords: "No matching loans found",
                    info: "Showing _START_ to _END_ of _TOTAL_ loans",
                    infoEmpty: "No loans available",
                    infoFiltered: "(filtered from _MAX_ total loans)"
                },
                dom: '<"top"f>rt<"bottom"lip><"clear">',
                pageLength: 10
            });
        });
    </script>
</body>
</html>