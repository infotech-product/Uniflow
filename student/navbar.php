<?php
// Check if user is logged in
$loggedIn = isset($_SESSION['student_id']);
?>

<nav class="navbar navbar-expand-lg navbar-custom fixed-top">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-graduation-cap me-2"></i>UniFlow
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fas fa-bars text-white"></i>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if ($loggedIn): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class="fas fa-home me-1"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="loans.php"><i class="fas fa-file-invoice-dollar me-1"></i> My Loans</a>
                    </li>
                    
                       <li class="nav-item">
                        <a class="nav-link" href="apply-loan.php"><i class="fas fa-house-1"></i> Apply for Loan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="payments.php"><i class="fas fa-money-bill-wave me-1"></i> Payments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php"><i class="fas fa-user me-1"></i> Profile</a>
                    </li>
                      <li class="nav-item">
                        <a class="nav-link" href="budget_planner.php"><i class="fas fa-house-1"></i> Budget Planner</a>
                    </li>

                       <li class="nav-item">
                        <a class="nav-link" href="academy.php"><i class="fas fa-house-1"></i> Academy</a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <div class="d-flex gap-3">
                <?php if ($loggedIn): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" id="userDropdown" 
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['student_id']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-cog me-1"></i> Account Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-light btn-sm">Login</a>
                    <a href="register.php" class="btn btn-primary-custom btn-sm">Create an account</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<style>
    .navbar-custom {
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
    }
</style>

<script>
    // Add scroll effect to navbar
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar-custom');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
</script>