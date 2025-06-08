<?php
// university_details.php - University Detailed View
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Start session and check admin authentication
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

// Get university ID from URL
$university_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($university_id <= 0) {
    header('Location: universities.php');
    exit();
}

// Get database connection
$pdo = DatabaseConnection::getInstance()->getConnection();

// Get university details
$university = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM universities WHERE id = ?");
    $stmt->execute([$university_id]);
    $university = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$university) {
        header('Location: universities.php');
        exit();
    }
} catch (PDOException $e) {
    $error_message = "Error fetching university details: " . $e->getMessage();
}

// Get university statistics
$stats = [];
try {
    $query = "SELECT 
                COUNT(DISTINCT s.id) AS student_count,
                COUNT(DISTINCT l.id) AS loan_count,
                SUM(CASE WHEN l.status = 'approved' THEN l.amount ELSE 0 END) AS total_loans_approved,
                SUM(CASE WHEN l.status = 'repaid' THEN l.amount_paid ELSE 0 END) AS total_loans_repaid,
                SUM(CASE WHEN l.status = 'defaulted' THEN l.amount - l.amount_paid ELSE 0 END) AS total_loans_defaulted,
                AVG(CASE WHEN l.status = 'approved' THEN l.amount ELSE NULL END) AS avg_loan_amount,
                COUNT(DISTINCT CASE WHEN l.status = 'approved' THEN s.id ELSE NULL END) AS students_with_loans
              FROM universities u
              LEFT JOIN students s ON u.id = s.university_id
              LEFT JOIN loans l ON u.id = l.university_id
              WHERE u.id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$university_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching university statistics: " . $e->getMessage();
}

// Get recent students
$recent_students = [];
try {
    $stmt = $pdo->prepare("SELECT id, student_id, full_name, created_at 
                          FROM students 
                          WHERE university_id = ?
                          ORDER BY created_at DESC
                          LIMIT 5");
    $stmt->execute([$university_id]);
    $recent_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching recent students: " . $e->getMessage();
}

// Get recent loans
$recent_loans = [];
try {
    $stmt = $pdo->prepare("SELECT l.id, s.full_name, l.amount, l.status, l.created_at
                          FROM loans l
                          JOIN students s ON l.student_id = s.id
                          WHERE l.university_id = ?
                          ORDER BY l.created_at DESC
                          LIMIT 5");
    $stmt->execute([$university_id]);
    $recent_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching recent loans: " . $e->getMessage();
}

// Get loan status distribution for chart
$loan_status_data = [];
try {
    $stmt = $pdo->prepare("SELECT 
                            status,
                            COUNT(*) AS count,
                            SUM(amount) AS total_amount
                          FROM loans
                          WHERE university_id = ?
                          GROUP BY status");
    $stmt->execute([$university_id]);
    $loan_status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching loan status data: " . $e->getMessage();
}

// Get monthly loan activity for chart
$monthly_activity = [];
try {
    $stmt = $pdo->prepare("SELECT 
                            DATE_FORMAT(created_at, '%Y-%m') AS month,
                            COUNT(*) AS loan_count,
                            SUM(amount) AS total_amount
                          FROM loans
                          WHERE university_id = ?
                          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                          ORDER BY month DESC
                          LIMIT 12");
    $stmt->execute([$university_id]);
    $monthly_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $monthly_activity = array_reverse($monthly_activity); // Oldest first
} catch (PDOException $e) {
    $error_message = "Error fetching monthly activity: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($university['name']); ?> | Uniflow</title>
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
           
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 15px;
            text-align: center;
        }
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .badge-dtef {
            background-color: rgba(78, 205, 196, 0.2);
            color: #4ECDC4;
        }
        .badge-schedule {
            background-color: rgba(106, 141, 255, 0.2);
            color: #6A8DFF;
        }
        .university-header {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .activity-card {
            transition: all 0.3s ease;
        }
        .activity-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
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
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-approved {
            background-color: rgba(78, 205, 196, 0.2);
            color: #4ECDC4;
        }
        .badge-pending {
            background-color: rgba(255, 193, 160, 0.2);
            color: #FFC3A0;
        }
        .badge-repaid {
            background-color: rgba(106, 141, 255, 0.2);
            color: #6A8DFF;
        }
        .badge-defaulted {
            background-color: rgba(255, 107, 107, 0.2);
            color: #FF6B6B;
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animated"></div>

    <!-- Navigation -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/Uniflow/admin/navbar.php'; ?>

    <div class="container py-5">
        <!-- University Header -->
        <div class="university-header glass">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title"><?php echo htmlspecialchars($university['name']); ?></h1>
                    <p class="welcome-subtitle mb-2">
                        <i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($university['location']); ?>
                        <?php if ($university['is_dtef_partner']): ?>
                            <span class="badge badge-dtef ms-2">DTEF Partner</span>
                        <?php endif; ?>
                        <span class="badge badge-schedule ms-2">
                            <?php echo ucfirst($university['allowance_schedule']); ?> Allowances
                        </span>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="universities.php" class="btn btn-outline-custom">
                        <i class="bi bi-arrow-left me-2"></i>Back to Universities
                    </a>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger glass" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card glass">
                    <div class="stat-number"><?php echo $stats['student_count'] ?? 0; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card glass">
                    <div class="stat-number"><?php echo $stats['loan_count'] ?? 0; ?></div>
                    <div class="stat-label">Total Loans</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card glass">
                    <div class="stat-number">P<?php echo number_format($stats['avg_loan_amount'] ?? 0, 2); ?></div>
                    <div class="stat-label">Avg Loan Amount</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card glass">
                    <div class="stat-number">
                        <?php 
                        $default_rate = ($stats['total_loans_approved'] > 0) 
                            ? ($stats['total_loans_defaulted'] / $stats['total_loans_approved']) * 100 
                            : 0;
                        echo number_format($default_rate, 1); 
                        ?>%
                    </div>
                    <div class="stat-label">Default Rate</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-6">
                <!-- Loan Status Chart -->
                <div class="dashboard-card glass visible mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-pie-chart-fill me-2"></i>Loan Status Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="loanStatusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Students -->
                <div class="dashboard-card glass visible mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Recent Students</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_students)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Student ID</th>
                                            <th>Name</th>
                                            <th>Registered</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_students as $student): ?>
                                            <tr class="activity-card" onclick="window.location='student_details.php?id=<?php echo $student['id']; ?>'" style="cursor: pointer;">
                                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-end mt-3">
                                <a href="students.php?university=<?php echo $university_id; ?>" class="btn btn-sm btn-outline-light">
                                    View All Students <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-people display-4 text-muted mb-3"></i>
                                <h5>No Students Found</h5>
                                <p class="text-muted">This university doesn't have any registered students yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-6">
                <!-- Monthly Activity Chart -->
                <div class="dashboard-card glass visible mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Monthly Loan Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyActivityChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Loans -->
                <div class="dashboard-card glass visible mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-cash-stack me-2"></i>Recent Loans</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_loans)): ?>
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
                                        <?php foreach ($recent_loans as $loan): ?>
                                            <tr class="activity-card" onclick="window.location='loan_details.php?id=<?php echo $loan['id']; ?>'" style="cursor: pointer;">
                                                <td><?php echo htmlspecialchars($loan['full_name']); ?></td>
                                                <td>P<?php echo number_format($loan['amount'], 2); ?></td>
                                                <td>
                                                    <?php 
                                                    $badge_class = '';
                                                    switch($loan['status']) {
                                                        case 'approved': $badge_class = 'badge-approved'; break;
                                                        case 'pending': $badge_class = 'badge-pending'; break;
                                                        case 'repaid': $badge_class = 'badge-repaid'; break;
                                                        case 'defaulted': $badge_class = 'badge-defaulted'; break;
                                                    }
                                                    ?>
                                                    <span class="status-badge <?php echo $badge_class; ?>">
                                                        <?php echo ucfirst($loan['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($loan['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-end mt-3">
                                <a href="loans.php?university=<?php echo $university_id; ?>" class="btn btn-sm btn-outline-light">
                                    View All Loans <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-cash display-4 text-muted mb-3"></i>
                                <h5>No Loans Found</h5>
                                <p class="text-muted">This university doesn't have any loan records yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Loan Status Chart
        const loanStatusCtx = document.getElementById('loanStatusChart').getContext('2d');
        const loanStatusChart = new Chart(loanStatusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($loan_status_data, 'status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($loan_status_data, 'count')); ?>,
                    backgroundColor: [
                        'rgba(78, 205, 196, 0.7)',
                        'rgba(255, 193, 160, 0.7)',
                        'rgba(106, 141, 255, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: 'rgba(0, 0, 0, 0.1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Monthly Activity Chart
        const monthlyActivityCtx = document.getElementById('monthlyActivityChart').getContext('2d');
        const monthlyActivityChart = new Chart(monthlyActivityCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_activity, 'month')); ?>,
                datasets: [
                    {
                        label: 'Number of Loans',
                        data: <?php echo json_encode(array_column($monthly_activity, 'loan_count')); ?>,
                        backgroundColor: 'rgba(78, 205, 196, 0.2)',
                        borderColor: 'rgba(78, 205, 196, 1)',
                        borderWidth: 2,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Total Amount (P)',
                        data: <?php echo json_encode(array_column($monthly_activity, 'total_amount')); ?>,
                        backgroundColor: 'rgba(106, 141, 255, 0.2)',
                        borderColor: 'rgba(106, 141, 255, 1)',
                        borderWidth: 2,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Number of Loans',
                            color: 'rgba(255, 255, 255, 0.7)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Total Amount (P)',
                            color: 'rgba(255, 255, 255, 0.7)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        },
                        grid: {
                            drawOnChartArea: false,
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)'
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            }
        });
    </script>
</body>
</html>