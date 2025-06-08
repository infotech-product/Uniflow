<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Start session at the beginning
session_start();

// Redirect if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header("Location: dashboard.php");
    exit();
}

// Initialize variables
$errors = [];
$student_id = '';
$remember_me = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $student_id = trim($_POST['student_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    // Validation
    if (empty($student_id)) {
        $errors['student_id'] = 'Student ID is required';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }
    
    // If no errors, proceed with login
    if (empty($errors)) {
        try {
            $pdo = DatabaseConnection::getInstance()->getConnection();
            
            // Prepare SQL with explicit column selection
            $stmt = $pdo->prepare("
                SELECT id, student_id, password_hash, is_verified, full_name 
                FROM students 
                WHERE student_id = ?
            ");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student) {
                // Verify password
                if (password_verify($password, $student['password_hash'])) {
                    // Check if account is verified
                    if ($student['is_verified']) {
                        // Set session variables
                        $_SESSION = [
                            'student_id' => $student['student_id'],
                            'student_db_id' => $student['id'],
                            'full_name' => $student['full_name'],
                            'logged_in' => true,
                            'last_activity' => time()
                        ];
                        
                        // Set remember me cookie if requested
                        if ($remember_me) {
                            $token = bin2hex(random_bytes(32));
                            $expiry = time() + 60 * 60 * 24 * 30; // 30 days
                            
                            // Store token in database
                            $stmt = $pdo->prepare("
                                UPDATE students 
                                SET remember_token = ?, remember_token_expiry = ? 
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                password_hash($token, PASSWORD_DEFAULT),
                                date('Y-m-d H:i:s', $expiry),
                                $student['id']
                            ]);
                            
                            // Set cookie
                            setcookie(
                                'remember_me', 
                                $student['id'] . ':' . $token,
                                $expiry,
                                '/',
                                '',
                                true,  // Secure
                                true   // HttpOnly
                            );
                        }
                        
                        // Redirect to dashboard
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $errors['general'] = 'Account not verified. Please check your email for verification link.';
                    }
                } else {
                    // Invalid password
                    $errors['general'] = 'Invalid student ID or password';
                    error_log("Failed login attempt for student ID: $student_id");
                }
            } else {
                // Student not found
                $errors['general'] = 'Invalid student ID or password';
                error_log("Failed login attempt for non-existent student ID: $student_id");
            }
        } catch (PDOException $e) {
            $errors['general'] = 'Login failed. Please try again later.';
            error_log("Database error during login: " . $e->getMessage());
        }
    }
}

// Check for remember me cookie
if (empty($_POST) && !empty($_COOKIE['remember_me'])) {
    try {
        list($student_id, $token) = explode(':', $_COOKIE['remember_me']);
        
        $pdo = DatabaseConnection::getInstance()->getConnection();
        $stmt = $pdo->prepare("
            SELECT id, student_id, remember_token, full_name 
            FROM students 
            WHERE id = ? AND remember_token_expiry > NOW()
        ");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student && password_verify($token, $student['remember_token'])) {
            // Set session variables
            $_SESSION = [
                'student_id' => $student['student_id'],
                'student_db_id' => $student['id'],
                'full_name' => $student['full_name'],
                'logged_in' => true,
                'last_activity' => time()
            ];
            
            // Redirect to dashboard
            header("Location: dashboard.php");
            exit();
        }
    } catch (Exception $e) {
        error_log("Remember me token error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - UniFlow Student Loans</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0a1628 0%, #1e3a5f 100%);
            min-height: 100vh;
            color: white;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            margin: 80px auto;
        }
        .login-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff, #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: #4ECDC4;
        }
        .btn-primary {
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
            border: none;
        }
        .error-message {
            color: #FF6B6B;
            background: rgba(255, 107, 107, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #FF6B6B;
        }
        .success-message {
            color: #4ECDC4;
            background: rgba(78, 205, 196, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #4ECDC4;
        }
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="text-center mb-5">
                <h1 class="login-title">Welcome Back</h1>
                <p class="text-muted mt-3">Sign in to manage your student loans</p>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($errors['general']) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['registration']) && $_GET['registration'] === 'success'): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle me-2"></i>Registration successful! Please log in.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle me-2"></i>Password reset successful! Please log in with your new password.
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-4">
                    <label class="form-label">Student ID *</label>
                    <input type="text" class="form-control <?= !empty($errors['student_id']) ? 'is-invalid' : '' ?>" 
                           name="student_id" value="<?= htmlspecialchars($student_id) ?>" required>
                    <?php if (!empty($errors['student_id'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['student_id']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-4 position-relative">
                    <label class="form-label">Password *</label>
                    <input type="password" class="form-control <?= !empty($errors['password']) ? 'is-invalid' : '' ?>" 
                           name="password" id="password" required>
                    <span class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </span>
                    <?php if (!empty($errors['password'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember_me" id="remember_me" <?= $remember_me ? 'checked' : '' ?>>
                        <label class="form-check-label" for="remember_me">Remember me</label>
                    </div>
                    <div>
                        <a href="forgot-password.php" class="text-decoration-none">Forgot password?</a>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 py-3 mb-4">
                    <span id="loginText">Sign In</span>
                    <span id="loginSpinner" class="d-none">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        Signing in...
                    </span>
                </button>

                <div class="text-center">
                    <p class="text-muted">Don't have an account? <a href="register.php" class="text-decoration-none">Sign up</a></p>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
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
        document.querySelector('form').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            const loginText = document.getElementById('loginText');
            const loginSpinner = document.getElementById('loginSpinner');
            
            // Show loading state
            loginText.classList.add('d-none');
            loginSpinner.classList.remove('d-none');
            btn.disabled = true;
            
            // If there are errors, reset the button state after a delay
            if (document.querySelector('.is-invalid')) {
                setTimeout(() => {
                    loginText.classList.remove('d-none');
                    loginSpinner.classList.add('d-none');
                    btn.disabled = false;
                }, 1000);
            }
        });

        // Focus on first field or first error
        document.addEventListener('DOMContentLoaded', function() {
            const firstError = document.querySelector('.is-invalid');
            if (firstError) {
                firstError.focus();
            } else {
                document.querySelector('[name="student_id"]').focus();
            }
        });
    </script>
</body>
</html>