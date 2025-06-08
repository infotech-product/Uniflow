<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Start session
session_start();

// Initialize variables
$errors = [];
$success_message = '';

// Check if admin already exists
try {
    $pdo = DatabaseConnection::getInstance()->getConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM super_admin WHERE username = 'admin'");
    $stmt->execute();
    $admin_exists = $stmt->fetchColumn() > 0;
    
    if ($admin_exists) {
        $errors['general'] = 'Admin account already exists. Only one admin account is allowed.';
    }
} catch (PDOException $e) {
    error_log("Database error checking admin: " . $e->getMessage());
    $errors['general'] = 'System error. Please try again later.';
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$admin_exists) {
    // Get and sanitize inputs
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $setup_key = $_POST['setup_key'] ?? '';
    
    // Validation
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
        $errors['password'] = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character';
    }
    
    if (empty($confirm_password)) {
        $errors['confirm_password'] = 'Please confirm your password';
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    // Setup key validation (you can customize this)
    $valid_setup_key = 'UNIFLOW_ADMIN_SETUP_2025'; // Change this to your desired setup key
    if (empty($setup_key)) {
        $errors['setup_key'] = 'Setup key is required';
    } elseif ($setup_key !== $valid_setup_key) {
        $errors['setup_key'] = 'Invalid setup key';
    }
    
    // If no errors, create admin account
    if (empty($errors)) {
        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO super_admin (username, password_hash, created_at) 
                VALUES ('admin', ?, NOW())
            ");
            
            if ($stmt->execute([$password_hash])) {
                $success_message = 'Admin account created successfully! You can now log in.';
                
                // Log the admin creation
                error_log("Super admin account created successfully");
                
                // Redirect to login after 3 seconds
                header("refresh:3;url=admin-login.php");
            } else {
                $errors['general'] = 'Failed to create admin account. Please try again.';
            }
        } catch (PDOException $e) {
            error_log("Database error creating admin: " . $e->getMessage());
            $errors['general'] = 'System error. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup - UniFlow Student Loans</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .setup-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            margin: 50px auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .setup-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .setup-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff, #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        
        .setup-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.1rem;
        }
        
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 10px;
            padding: 12px 15px;
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
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(78, 205, 196, 0.3);
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
        
        .password-requirements {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            font-size: 0.85rem;
        }
        
        .requirement {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 5px;
        }
        
        .requirement.valid {
            color: #4ECDC4;
        }
        
        .requirement.invalid {
            color: #FF6B6B;
        }
        
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.6);
        }
        
        .password-toggle:hover {
            color: white;
        }
        
        .admin-icon {
            font-size: 4rem;
            color: #4ECDC4;
            margin-bottom: 20px;
        }
        
        .security-note {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .security-note i {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="setup-container">
            <div class="setup-header">
                <i class="fas fa-user-shield admin-icon"></i>
                <h1 class="setup-title">Admin Setup</h1>
                <p class="setup-subtitle">Initialize your UniFlow admin account</p>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                    <div class="mt-2">
                        <small>Redirecting to login page...</small>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors['general'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($errors['general']) ?>
                </div>
            <?php endif; ?>

            <?php if (!$admin_exists && empty($success_message)): ?>
                <div class="security-note">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Security Notice:</strong> This setup page will only work once. After the admin account is created, 
                    this page will be disabled for security purposes.
                </div>

                <form method="POST" action="" id="setupForm">
                    <div class="mb-4">
                        <label class="form-label">Setup Key *</label>
                        <input type="password" class="form-control <?= !empty($errors['setup_key']) ? 'is-invalid' : '' ?>" 
                               name="setup_key" placeholder="Enter setup authorization key" required>
                        <?php if (!empty($errors['setup_key'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['setup_key']) ?></div>
                        <?php endif; ?>
                        <small class="text-muted">Contact your system administrator for the setup key</small>
                    </div>

                    <div class="mb-4 position-relative">
                        <label class="form-label">Admin Password *</label>
                        <input type="password" class="form-control <?= !empty($errors['password']) ? 'is-invalid' : '' ?>" 
                               name="password" id="password" placeholder="Create a strong password" required>
                        <span class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </span>
                        <?php if (!empty($errors['password'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div>
                        <?php endif; ?>
                        
                        <div class="password-requirements">
                            <div class="requirement" id="length">
                                <i class="fas fa-circle" style="font-size: 0.5rem;"></i> At least 8 characters
                            </div>
                            <div class="requirement" id="uppercase">
                                <i class="fas fa-circle" style="font-size: 0.5rem;"></i> One uppercase letter
                            </div>
                            <div class="requirement" id="lowercase">
                                <i class="fas fa-circle" style="font-size: 0.5rem;"></i> One lowercase letter
                            </div>
                            <div class="requirement" id="number">
                                <i class="fas fa-circle" style="font-size: 0.5rem;"></i> One number
                            </div>
                            <div class="requirement" id="special">
                                <i class="fas fa-circle" style="font-size: 0.5rem;"></i> One special character (@$!%*?&)
                            </div>
                        </div>
                    </div>

                    <div class="mb-4 position-relative">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" class="form-control <?= !empty($errors['confirm_password']) ? 'is-invalid' : '' ?>" 
                               name="confirm_password" id="confirm_password" placeholder="Confirm your password" required>
                        <span class="password-toggle" id="toggleConfirmPassword">
                            <i class="fas fa-eye"></i>
                        </span>
                        <?php if (!empty($errors['confirm_password'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['confirm_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 py-3 mb-4" id="setupBtn">
                        <span id="setupText">
                            <i class="fas fa-shield-alt me-2"></i>Create Admin Account
                        </span>
                        <span id="setupSpinner" class="d-none">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            Creating Account...
                        </span>
                    </button>

                    <div class="text-center">
                        <small class="text-muted">
                            By creating this account, you agree to secure and responsible administration of the UniFlow system.
                        </small>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($admin_exists && empty($success_message)): ?>
                <div class="text-center">
                    <p class="mb-4">Admin account setup is complete.</p>
                    <a href="admin-login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Go to Admin Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggles
        function setupPasswordToggle(toggleId, passwordId) {
            document.getElementById(toggleId).addEventListener('click', function() {
                const password = document.getElementById(passwordId);
                const icon = this.querySelector('i');
                
                if (password.type === 'password') {
                    password.type = 'text';
                    icon.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    password.type = 'password';
                    icon.classList.replace('fa-eye-slash', 'fa-eye');
                }
            });
        }

        setupPasswordToggle('togglePassword', 'password');
        setupPasswordToggle('toggleConfirmPassword', 'confirm_password');

        // Password strength validation
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            
            // Check requirements
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /\d/.test(password),
                special: /[@$!%*?&]/.test(password)
            };
            
            // Update requirement indicators
            Object.keys(requirements).forEach(req => {
                const element = document.getElementById(req);
                const icon = element.querySelector('i');
                
                if (requirements[req]) {
                    element.classList.remove('invalid');
                    element.classList.add('valid');
                    icon.classList.replace('fa-circle', 'fa-check-circle');
                } else {
                    element.classList.remove('valid');
                    element.classList.add('invalid');
                    icon.classList.replace('fa-check-circle', 'fa-circle');
                }
            });
        });

        // Form submission handling
        document.getElementById('setupForm')?.addEventListener('submit', function(e) {
            const btn = document.getElementById('setupBtn');
            const setupText = document.getElementById('setupText');
            const setupSpinner = document.getElementById('setupSpinner');
            
            // Show loading state
            setupText.classList.add('d-none');
            setupSpinner.classList.remove('d-none');
            btn.disabled = true;
        });

        // Auto-focus first field
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[name="setup_key"]');
            if (firstInput) {
                firstInput.focus();
            }
        });
    </script>
</body>
</html>