<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in (matches super_admin table structure)
$isLoggedIn = isset($_SESSION['admin_id']) && isset($_SESSION['username']);
$username = $isLoggedIn ? $_SESSION['username'] : '';
$adminInitial = $isLoggedIn ? strtoupper(substr($username, 0, 1)) : '';
?>

<!-- Animated Background -->
<div class="bg-animated"></div>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-custom fixed-top">
    <div class="container">
        <!-- Brand with University Management Focus -->
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-university me-2"></i>UniFlow Admin
        </a>
        
        <!-- Mobile Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" 
                aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"><i class="fas fa-bars text-white"></i></span>
        </button>
        
        <!-- Navigation Links -->
        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                </li>
                
                <!-- Data Management Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dataManagementDropdown" role="button" 
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-database me-2"></i>Data Management
                    </a>
                    <ul class="dropdown-menu glass-dark" aria-labelledby="dataManagementDropdown">
                        <li><a class="dropdown-item" href="students.php">
                            <i class="fas fa-user-graduate me-2"></i>Students
                        </a></li>
                        <li><a class="dropdown-item" href="loans.php">
                            <i class="fas fa-hand-holding-usd me-2"></i>Loans
                        </a></li>
                        <li><a class="dropdown-item" href="repayments.php">
                            <i class="fas fa-money-bill-transfer me-2"></i>Repayments
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="universities.php">
                            <i class="fas fa-school me-2"></i>Universities
                        </a></li>
                        <li><a class="dropdown-item" href="courses.php">
                            <i class="fas fa-book me-2"></i>Courses
                        </a></li>
                    </ul>
                </li>
                
                <!-- Financial Tools -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="financialDropdown" role="button" 
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-chart-line me-2"></i>Financial Tools
                    </a>
                    <ul class="dropdown-menu glass-dark" aria-labelledby="financialDropdown">
                        <li><a class="dropdown-item" href="reports.php">
                            <i class="fas fa-file-invoice-dollar me-2"></i>Financial Reports
                        </a></li>
                        <li><a class="dropdown-item" href="risk_assessment.php">
                            <i class="fas fa-robot me-2"></i>AI Risk Assessment
                        </a></li>
                        <li><a class="dropdown-item" href="allowance_verification.php">
                            <i class="fas fa-check-circle me-2"></i>Allowance Verification
                        </a></li>
                    </ul>
                </li>
                
                <!-- System Administration -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="systemDropdown" role="button" 
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-server me-2"></i>System
                    </a>
                    <ul class="dropdown-menu glass-dark" aria-labelledby="systemDropdown">
                        <li><a class="dropdown-item" href="admin_logs.php">
                            <i class="fas fa-scroll me-2"></i>Activity Logs
                        </a></li>
                        <li><a class="dropdown-item" href="system_config.php">
                            <i class="fas fa-sliders-h me-2"></i>Configuration
                        </a></li>
                        <li><a class="dropdown-item" href="admin_management.php">
                            <i class="fas fa-users-cog me-2"></i>Admin Management
                        </a></li>
                    </ul>
                </li>
            </ul>
            
            <!-- Right Side Navigation -->
            <div class="d-flex align-items-center">
                <!-- Quick Search (for students/loans) -->
                <div class="input-group me-3 d-none d-lg-flex">
                    <input type="text" class="form-control glass" placeholder="Search students/loans..." 
                           style="background: rgba(255,255,255,0.1); color: white; border: none;">
                    <button class="btn btn-outline-light" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                
                <!-- Notifications -->
                <div class="dropdown me-3">
                    <a class="btn btn-link text-white position-relative" href="#" role="button" 
                       id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            3+
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end glass-dark" aria-labelledby="notificationsDropdown">
                        <li><h6 class="dropdown-header">Recent Activities</h6></li>
                        <li><a class="dropdown-item" href="#">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <div class="bg-primary rounded-circle p-2">
                                        <i class="fas fa-file-signature text-white"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="small text-muted"><?= date('M j, Y') ?></div>
                                    <span>New loan applications</span>
                                </div>
                            </div>
                        </a></li>
                        <li><a class="dropdown-item" href="#">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <div class="bg-warning rounded-circle p-2">
                                        <i class="fas fa-exclamation-triangle text-white"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="small text-muted"><?= date('M j, Y', strtotime('-1 day')) ?></div>
                                    <span>Pending verifications</span>
                                </div>
                            </div>
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="notifications.php">View all notifications</a></li>
                    </ul>
                </div>
                
                <!-- Admin Profile -->
                <?php if ($isLoggedIn): ?>
                <div class="dropdown">
                    <a class="btn btn-link text-white d-flex align-items-center" href="#" role="button" 
                       id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="me-2 d-none d-lg-block text-end">
                            <div class="fw-bold"><?= htmlspecialchars($username) ?></div>
                            <div class="small text-muted">System Administrator</div>
                        </div>
                        <div class="avatar bg-primary text-white" style="width: 36px; height: 36px; line-height: 36px;">
                            <?= $adminInitial ?>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end glass-dark" aria-labelledby="adminDropdown">
                        <li><a class="dropdown-item" href="admin_profile.php">
                            <i class="fas fa-user-shield me-2"></i>Admin Profile
                        </a></li>
                        <li><a class="dropdown-item" href="system_config.php">
                            <i class="fas fa-tools me-2"></i>System Settings
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a></li>
                    </ul>
                </div>
                <?php else: ?>
                <div class="d-flex">
                    <a href="logout.php" class="btn btn-outline-light me-2">Logout</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<style> .navbar-custom {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding: 1rem 0;
        transition: all 0.3s ease;
    }

    .navbar-custom.scrolled {
        background: rgba(10, 22, 40, 0.95);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
    }

    .navbar-brand {
        font-size: 1.8rem;
        font-weight: 800;
        background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .nav-link {
        color: rgba(255, 255, 255, 0.8);
        font-weight: 500;
        padding: 0.5rem 1rem;
        margin: 0 0.25rem;
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .nav-link:hover, .nav-link.active {
        color: white;
        background: rgba(255, 255, 255, 0.1);
    }

    .dropdown-menu {
        background: rgba(10, 22, 40, 0.95);
        border: 1px solid rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(20px);
    }

    .dropdown-item {
        color: rgba(255, 255, 255, 0.8);
        transition: all 0.2s ease;
    }

    .dropdown-item:hover {
        color: white;
        background: rgba(78, 205, 196, 0.2);
    }

    .btn-primary-custom {
        background: linear-gradient(135deg, #FF6B6B, #FF8E53);
        border: none;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
    }

    .btn-primary-custom:hover {
        background: linear-gradient(135deg, #FF8E53, #FF6B6B);
        box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4);
        transform: translateY(-2px);
    }

    .navbar-toggler {
        border: none;
        padding: 0.5rem;
    }

    .navbar-toggler:focus {
        box-shadow: none;
    }

    @media (max-width: 992px) {
        .navbar-collapse {
            background: rgba(10, 22, 40, 0.95);
            backdrop-filter: blur(20px);
            padding: 1rem;
            border-radius: 10px;
            margin-top: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .nav-link {
            margin: 0.25rem 0;
        }
    }</style>

<!-- Back to Top Button -->
<a href="#" class="back-to-top" id="backToTop">
    <i class="fas fa-arrow-up"></i>
</a>

<!-- JavaScript -->
<script>
// Back to Top Button
window.addEventListener('scroll', function() {
    var backToTop = document.getElementById('backToTop');
    if (window.pageYOffset > 300) {
        backToTop.classList.add('active');
    } else {
        backToTop.classList.remove('active');
    }
});

document.getElementById('backToTop').addEventListener('click', function(e) {
    e.preventDefault();
    window.scrollTo({top: 0, behavior: 'smooth'});
});

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Animate elements on scroll
    const animateOnScroll = function() {
        const elements = document.querySelectorAll('.animate-on-scroll');
        elements.forEach(element => {
            const elementPosition = element.getBoundingClientRect().top;
            const windowHeight = window.innerHeight;
            
            if (elementPosition < windowHeight - 100) {
                element.classList.add('animated');
            }
        });
    };
    
    window.addEventListener('scroll', animateOnScroll);
    animateOnScroll(); // Run once on load
});
</script>