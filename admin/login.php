<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Start session
session_start();

// If admin is already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

// Initialize variables
$errors = [];
$login_attempts = $_SESSION['login_attempts'] ?? 0;
$last_attempt = $_SESSION['last_attempt'] ?? 0;

// Rate limiting - 5 attempts per 15 minutes
$max_attempts = 5;
$lockout_time = 900; // 15 minutes in seconds

if ($login_attempts >= $max_attempts && (time() - $last_attempt) < $lockout_time) {
    $remaining_time = $lockout_time - (time() - $last_attempt);
    $errors['rate_limit'] = 'Too many failed attempts. Try again in ' . ceil($remaining_time / 60) . ' minutes.';
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors['rate_limit'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Basic validation
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }
    
    // If basic validation passes, check credentials
    if (empty($errors)) {
        try {
            $pdo = DatabaseConnection::getInstance()->getConnection();
            $stmt = $pdo->prepare("SELECT id, username, password_hash, created_at, last_login FROM super_admin WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin && password_verify($password, $admin['password_hash'])) {
                // Login successful
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['login_time'] = time();
                
                // Reset login attempts
                unset($_SESSION['login_attempts']);
                unset($_SESSION['last_attempt']);
                
                // Update last login time
                $updateStmt = $pdo->prepare("UPDATE super_admin SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$admin['id']]);
                
                // Log successful login
                error_log("Admin login successful for user: " . $admin['username']);
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit();
            } else {
                // Login failed
                $login_attempts++;
                $_SESSION['login_attempts'] = $login_attempts;
                $_SESSION['last_attempt'] = time();
                
                $errors['credentials'] = 'Invalid username or password';
                
                // Log failed attempt
                error_log("Failed admin login attempt for username: " . $username . " from IP: " . $_SERVER['REMOTE_ADDR']);
            }
            
        } catch (PDOException $e) {
            error_log("Database error during admin login: " . $e->getMessage());
            $errors['system'] = 'System error. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - UniFlow Student Loans</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            margin: 80px auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff, #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        
        .login-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.1rem;
        }
        
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 10px;
            padding: 15px 20px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #4ECDC4;
            box-shadow: 0 0 0 0.2rem rgba(78, 205, 196, 0.25);
            color: white;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .form-label {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4ECDC4, #44A08D);
            border: none;
            border-radius: 10px;
            padding: 15px 30px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(78, 205, 196, 0.3);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .error-message {
            color: #FF6B6B;
            background: rgba(255, 107, 107, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #FF6B6B;
        }
        
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.6);
            font-size: 1.1rem;
        }
        
        .password-toggle:hover {
            color: white;
        }
        
        .admin-icon {
            font-size: 4rem;
            color: #4ECDC4;
            margin-bottom: 20px;
        }
        
        .login-attempts {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }
        
        .login-attempts i {
            color: #ffc107;
        }
        
        .setup-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .setup-link a {
            color: #4ECDC4;
            text-decoration: none;
            font-weight: 500;
        }
        
        .setup-link a:hover {
            color: white;
            text-decoration: underline;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.6);
            z-index: 3;
        }
        
        .form-control.with-icon {
            padding-left: 50px;
        }
        
        .remember-me {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .form-check-input:checked {
            background-color: #4ECDC4;
            border-color: #4ECDC4;
        }
        
        .form-check-input:focus {
            box-shadow: 0 0 0 0.25rem rgba(78, 205, 196, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <i class="fas fa-shield-alt admin-icon"></i>
                <h1 class="login-title">Admin Portal</h1>
                <p class="login-subtitle">Secure access to UniFlow administration</p>
            </div>

            <?php if (!empty($errors['rate_limit'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($errors['rate_limit']) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors['credentials'])): ?>
                <div class="error-message">
                    <i class="fas fa-times-circle me-2"></i><?= htmlspecialchars($errors['credentials']) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors['system'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($errors['system']) ?>
                </div>
            <?php endif; ?>

            <?php if ($login_attempts > 0 && $login_attempts < $max_attempts): ?>
                <div class="login-attempts">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Failed attempts: <?= $login_attempts ?>/<?= $max_attempts ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="mb-4">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" class="form-control with-icon <?= !empty($errors['username']) ? 'is-invalid' : '' ?>" 
                               name="username" placeholder="Enter your username" required 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               <?= !empty($errors['rate_limit']) ? 'disabled' : '' ?>>
                    </div>
                    <?php if (!empty($errors['username'])): ?>
                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['username']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group position-relative">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" class="form-control with-icon <?= !empty($errors['password']) ? 'is-invalid' : '' ?>" 
                               name="password" id="password" placeholder="Enter your password" required
                               <?= !empty($errors['rate_limit']) ? 'disabled' : '' ?>>
                        <span class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <?php if (!empty($errors['password'])): ?>
                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['password']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-4">
                    <div class="form-check remember-me">
                        <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me">
                        <label class="form-check-label" for="rememberMe">
                            Remember this session
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 py-3 mb-4" id="loginBtn"
                        <?= !empty($errors['rate_limit']) ? 'disabled' : '' ?>>
                    <span id="loginText">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </span>
                    <span id="loginSpinner" class="d-none">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Signing In...
                    </span>
                </button>

                <div class="text-center">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Secure admin authentication required
                    </small>
                </div>
            </form>

            <div class="setup-link">
                <small class="text-muted">
                    Need to create an admin account? 
                    <a href="admin-setup.php">Admin Setup</a>
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

        // Form submission handling
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const loginText = document.getElementById('loginText');
            const loginSpinner = document.getElementById('loginSpinner');
            
            if (!btn.disabled) {
                // Show loading state
                loginText.classList.add('d-none');
                loginSpinner.classList.remove('d-none');
                btn.disabled = true;
            }
        });

        // Auto-focus username field
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.querySelector('input[name="username"]');
            if (usernameField && !usernameField.disabled) {
                usernameField.focus();
            }
        });

        // Handle enter key on form fields
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.target.form.querySelector('button[type="submit"]').disabled) {
                    e.target.form.submit();
                }
            });
        });

        // Clear error messages on input
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                const feedback = this.parentNode.nextElementSibling;
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                    feedback.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>