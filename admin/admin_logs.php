<?php
// admin_logs.php - Admin Activity Logs Dashboard
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

// Handle log filtering
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base query
$query = "SELECT * FROM admin_logs";
$params = [];

// Apply filters
if ($filter !== 'all') {
    $query .= " WHERE action_type = ?";
    $params[] = $filter;
}

if ($search !== '') {
    $query .= ($filter !== 'all' ? " AND" : " WHERE") . " (details LIKE ? OR target_id = ?)";
    $params[] = "%$search%";
    
    // Only add target_id as parameter if it's numeric
    if (is_numeric($search)) {
        $params[] = $search;
    } else {
        // If search term isn't numeric, we need to adjust the query
        $query = str_replace("OR target_id = ?", "", $query);
    }
}

// Add sorting
$query .= " ORDER BY performed_at DESC";

// Pagination
$logs_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $logs_per_page;

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM ($query) AS total";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_logs = $stmt->fetchColumn();

$total_pages = ceil($total_logs / $logs_per_page);

// Add pagination to main query
$query .= " LIMIT $logs_per_page OFFSET $offset";

// Get logs
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Action type descriptions
$action_descriptions = [
    'student_edit' => 'Student Profile Edit',
    'loan_approve' => 'Loan Approval',
    'loan_reject' => 'Loan Rejection',
    'payment_view' => 'Payment Viewed',
    'defaulters_view' => 'Defaulters List Viewed',
    'system_change' => 'System Configuration Change'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Logs | Uniflow</title>
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

        .log-card {
            transition: all 0.3s ease;
            border-left: 5px solid transparent;
        }
        .log-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        .action-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
        }
        .action-student_edit { background-color: #4ECDC4; }
        .action-loan_approve { background-color: #6A8DFF; }
        .action-loan_reject { background-color: #FF6B6B; }
        .action-payment_view { background-color: #FF8E53; }
        .action-defaulters_view { background-color: #A18CD1; }
        .action-system_change { background-color: #6C757D; }
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
        .badge-filter {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .badge-filter:hover {
            transform: translateY(-2px);
            opacity: 0.9;
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
                    <h1 class="welcome-title">Admin Activity Logs</h1>
                    <p class="welcome-subtitle">Track all administrator actions and system changes</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="dropdown">
                        <button class="btn btn-outline-custom dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-download me-2"></i>Export
                        </button>
                        <ul class="dropdown-menu glass-dark" aria-labelledby="exportDropdown">
                            <li><a class="dropdown-item" href="#" onclick="exportLogs('csv')">CSV Format</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportLogs('pdf')">PDF Format</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="dashboard-card glass visible mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <input type="text" class="form-control" name="search" placeholder="Search logs..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <span class="me-3">Filter:</span>
                            <div class="btn-group" role="group">
                                <a href="?filter=all" class="btn btn-sm <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline-light'; ?>">All</a>
                                <?php foreach ($action_descriptions as $type => $desc): ?>
                                    <a href="?filter=<?php echo $type; ?>" class="btn btn-sm <?php echo $filter === $type ? 'btn-primary' : 'btn-outline-light'; ?>">
                                        <?php echo $desc; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="dashboard-card glass visible mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Activity Logs</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($logs)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Target ID</th>
                                    <th>Details</th>
                                    <th>Performed At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="action-icon action-<?php echo $log['action_type']; ?>">
                                                    <?php 
                                                    $icon = '';
                                                    switch($log['action_type']) {
                                                        case 'student_edit': $icon = 'bi-person'; break;
                                                        case 'loan_approve': $icon = 'bi-check-circle'; break;
                                                        case 'loan_reject': $icon = 'bi-x-circle'; break;
                                                        case 'payment_view': $icon = 'bi-cash-stack'; break;
                                                        case 'defaulters_view': $icon = 'bi-exclamation-triangle'; break;
                                                        case 'system_change': $icon = 'bi-gear'; break;
                                                        default: $icon = 'bi-activity';
                                                    }
                                                    ?>
                                                    <i class="bi <?php echo $icon; ?>"></i>
                                                </div>
                                                <div>
                                                    <strong><?php echo $action_descriptions[$log['action_type']] ?? ucfirst(str_replace('_', ' ', $log['action_type'])); ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo $log['target_id'] ?: 'N/A'; ?></td>
                                        <td><?php echo $log['details'] ?: 'No details provided'; ?></td>
                                        <td><?php echo date('M d, Y H:i:s', strtotime($log['performed_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav aria-label="Logs pagination">
                        <ul class="pagination justify-content-center mt-4">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-journal-x display-4 text-muted mb-3"></i>
                        <h5>No activity logs found</h5>
                        <p class="text-muted">There are no logs matching your criteria</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function exportLogs(format) {
            // Get current filter and search parameters
            const filter = '<?php echo $filter; ?>';
            const search = '<?php echo urlencode($search); ?>';
            
            // Show loading state
            const exportBtn = document.querySelector('#exportDropdown');
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="bi bi-arrow-repeat spinner"></i> Preparing...';
            
            // Make AJAX request to export endpoint
            $.ajax({
                url: '/Uniflow/admin/api/export_logs.php',
                method: 'POST',
                data: {
                    format: format,
                    filter: filter,
                    search: search
                },
                xhrFields: {
                    responseType: 'blob'
                },
                success: function(data) {
                    // Create download link
                    const a = document.createElement('a');
                    const url = window.URL.createObjectURL(data);
                    a.href = url;
                    
                    // Set filename based on format and current date
                    const date = new Date().toISOString().split('T')[0];
                    a.download = `admin_logs_${date}.${format}`;
                    
                    // Trigger download
                    document.body.appendChild(a);
                    a.click();
                    
                    // Clean up
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    // Restore button text
                    exportBtn.innerHTML = originalText;
                },
                error: function(xhr, status, error) {
                    console.error("Export error:", error);
                    alert("Error generating export file. Please try again.");
                    exportBtn.innerHTML = originalText;
                }
            });
        }
        
        // Initialize tooltips
        $(function () {
            $('[data-bs-toggle="tooltip"]').tooltip();
        });
    </script>
</body>
</html>