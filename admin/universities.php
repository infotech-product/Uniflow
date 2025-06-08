<?php
// universities.php - Universities Dashboard
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Start session and check admin authentication
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

// Get database connection
$pdo = DatabaseConnection::getInstance()->getConnection();

// Get all universities with student and loan statistics
$universities = [];
try {
    $query = "SELECT 
                u.id, 
                u.name, 
                u.location, 
                u.allowance_schedule,
                u.is_dtef_partner,
                COUNT(DISTINCT s.id) AS student_count,
                COUNT(DISTINCT l.id) AS loan_count,
                SUM(CASE WHEN l.status = 'approved' THEN l.amount ELSE 0 END) AS total_loans_approved,
                SUM(CASE WHEN l.status = 'repaid' THEN l.amount_paid ELSE 0 END) AS total_loans_repaid,
                SUM(CASE WHEN l.status = 'defaulted' THEN l.amount - l.amount_paid ELSE 0 END) AS total_loans_defaulted
              FROM universities u
              LEFT JOIN students s ON u.id = s.university_id
              LEFT JOIN loans l ON u.id = l.university_id
              GROUP BY u.id
              ORDER BY u.name ASC";
    
    $stmt = $pdo->query($query);
    $universities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching university data: " . $e->getMessage();
}

// Get loan statistics by university for charts
$loan_stats = [];
try {
    $query = "SELECT 
                u.name AS university,
                COUNT(l.id) AS total_loans,
                SUM(CASE WHEN l.status = 'approved' THEN 1 ELSE 0 END) AS approved_loans,
                SUM(CASE WHEN l.status = 'repaid' THEN 1 ELSE 0 END) AS repaid_loans,
                SUM(CASE WHEN l.status = 'defaulted' THEN 1 ELSE 0 END) AS defaulted_loans
              FROM universities u
              LEFT JOIN loans l ON u.id = l.university_id
              GROUP BY u.id
              ORDER BY total_loans DESC";
    
    $stmt = $pdo->query($query);
    $loan_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching loan statistics: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Universities | Uniflow</title>
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

        .university-card {
            transition: all 0.3s ease;
            border-left: 5px solid transparent;
        }
        .university-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        .badge-dtef {
            background-color: rgba(78, 205, 196, 0.2);
            color: #4ECDC4;
        }
        .badge-monthly {
            background-color: rgba(106, 141, 255, 0.2);
            color: #6A8DFF;
        }
        .badge-semester {
            background-color: rgba(255, 107, 107, 0.2);
            color: #FF6B6B;
        }
        .badge-quarter {
            background-color: rgba(255, 142, 83, 0.2);
            color: #FF8E53;
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
                    <h1 class="welcome-title">Universities Dashboard</h1>
                    <p class="welcome-subtitle">View all partner universities and their financial activity</p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-outline-custom" data-bs-toggle="modal" data-bs-target="#statsModal">
                        <i class="bi bi-graph-up me-2"></i>View Statistics
                    </button>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger glass" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Summary Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card glass">
                    <div class="stat-number"><?php echo count($universities); ?></div>
                    <div class="stat-label">Universities</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card glass">
                    <div class="stat-number"><?php echo array_sum(array_column($universities, 'student_count')); ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card glass">
                    <div class="stat-number">P<?php echo number_format(array_sum(array_column($universities, 'total_loans_approved')), 2); ?></div>
                    <div class="stat-label">Loans Approved</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card glass">
                    <div class="stat-number">P<?php echo number_format(array_sum(array_column($universities, 'total_loans_defaulted')), 2); ?></div>
                    <div class="stat-label">Loans Defaulted</div>
                </div>
            </div>
        </div>

        <!-- Universities List -->
        <div class="dashboard-card glass visible mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-building me-2"></i>Partner Universities</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($universities)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>University</th>
                                    <th>Location</th>
                                    <th>Students</th>
                                    <th>Loans</th>
                                    <th>Approved</th>
                                    <th>Repaid</th>
                                    <th>Defaulted</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($universities as $university): ?>
                                    <tr class="university-card" onclick="window.location='university_details.php?id=<?php echo $university['id']; ?>'" style="cursor: pointer;">
                                        <td>
                                            <strong><?php echo htmlspecialchars($university['name']); ?></strong>
                                            <?php if ($university['is_dtef_partner']): ?>
                                                <span class="badge badge-dtef ms-2">DTEF Partner</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($university['location']); ?></td>
                                        <td><?php echo $university['student_count']; ?></td>
                                        <td><?php echo $university['loan_count']; ?></td>
                                        <td>P<?php echo number_format($university['total_loans_approved'], 2); ?></td>
                                        <td>P<?php echo number_format($university['total_loans_repaid'], 2); ?></td>
                                        <td>P<?php echo number_format($university['total_loans_defaulted'], 2); ?></td>
                                        <td>
                                            <?php 
                                            $badge_class = '';
                                            switch($university['allowance_schedule']) {
                                                case 'monthly': $badge_class = 'badge-monthly'; break;
                                                case 'semester': $badge_class = 'badge-semester'; break;
                                                case 'quarter': $badge_class = 'badge-quarter'; break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo ucfirst($university['allowance_schedule']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-building display-4 text-muted mb-3"></i>
                        <h5>No universities found</h5>
                        <p class="text-muted">There are no universities registered in the system</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Loan Statistics Chart -->
        <div class="dashboard-card glass visible mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>Loan Statistics by University</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="loansChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Modal -->
    <div class="modal fade" id="statsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content glass-dark">
                <div class="modal-header border-0">
                    <h5 class="modal-title">University Statistics</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <canvas id="studentsChart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-container">
                                <canvas id="defaultRatesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Prepare data for charts
        const loanStats = <?php echo json_encode($loan_stats); ?>;
        const universities = <?php echo json_encode($universities); ?>;

        // Main Loans Chart
        const loansCtx = document.getElementById('loansChart').getContext('2d');
        const loansChart = new Chart(loansCtx, {
            type: 'bar',
            data: {
                labels: loanStats.map(u => u.university),
                datasets: [
                    {
                        label: 'Approved Loans',
                        data: loanStats.map(u => u.approved_loans),
                        backgroundColor: 'rgba(78, 205, 196, 0.7)',
                        borderColor: 'rgba(78, 205, 196, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Repaid Loans',
                        data: loanStats.map(u => u.repaid_loans),
                        backgroundColor: 'rgba(106, 141, 255, 0.7)',
                        borderColor: 'rgba(106, 141, 255, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Defaulted Loans',
                        data: loanStats.map(u => u.defaulted_loans),
                        backgroundColor: 'rgba(255, 107, 107, 0.7)',
                        borderColor: 'rgba(255, 107, 107, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)'
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                }
            }
        });

        // Students Chart for Modal
        const statsModal = document.getElementById('statsModal');
        statsModal.addEventListener('shown.bs.modal', function() {
            const studentsCtx = document.getElementById('studentsChart').getContext('2d');
            new Chart(studentsCtx, {
                type: 'doughnut',
                data: {
                    labels: universities.map(u => u.name),
                    datasets: [{
                        data: universities.map(u => u.student_count),
                        backgroundColor: [
                            'rgba(78, 205, 196, 0.7)',
                            'rgba(106, 141, 255, 0.7)',
                            'rgba(255, 107, 107, 0.7)',
                            'rgba(255, 142, 83, 0.7)',
                            'rgba(161, 140, 209, 0.7)',
                            'rgba(108, 117, 125, 0.7)'
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
                        title: {
                            display: true,
                            text: 'Student Distribution',
                            color: 'rgba(255, 255, 255, 0.8)'
                        }
                    }
                }
            });

            // Default Rates Chart for Modal
            const defaultRatesCtx = document.getElementById('defaultRatesChart').getContext('2d');
            new Chart(defaultRatesCtx, {
                type: 'radar',
                data: {
                    labels: universities.map(u => u.name),
                    datasets: [{
                        label: 'Default Rate %',
                        data: universities.map(u => {
                            const total = u.total_loans_approved;
                            const defaulted = u.total_loans_defaulted;
                            return total > 0 ? ((defaulted / total) * 100).toFixed(1) : 0;
                        }),
                        backgroundColor: 'rgba(255, 107, 107, 0.2)',
                        borderColor: 'rgba(255, 107, 107, 1)',
                        pointBackgroundColor: 'rgba(255, 107, 107, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(255, 107, 107, 1)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            angleLines: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            pointLabels: {
                                color: 'rgba(255, 255, 255, 0.8)'
                            },
                            ticks: {
                                backdropColor: 'rgba(0, 0, 0, 0)',
                                color: 'rgba(255, 255, 255, 0.7)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: 'rgba(255, 255, 255, 0.8)'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Loan Default Rates',
                            color: 'rgba(255, 255, 255, 0.8)'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>