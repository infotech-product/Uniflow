<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Initialize variables
$errors = [];
$success = false;
$email = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    // Validate email
    if (empty($email)) {
        $errors['email'] = 'Email address is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }
    
    // If no errors, proceed with password reset
    if (empty($errors)) {
        try {
            $pdo = DatabaseConnection::getInstance()->getConnection();
            
            // Check if email exists in database
            $stmt = $pdo->prepare("SELECT id, student_id FROM students WHERE email = ?");
            $stmt->execute([$email]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student) {
                // Generate a unique token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
                
                // Store token in database
                $stmt = $pdo->prepare("UPDATE students SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
                $stmt->execute([$token, $expires, $student['id']]);
                
                // Create reset link
                $resetLink = "https://" . $_SERVER['HTTP_HOST'] . "/Uniflow/student/reset-password.php?token=$token";
                
                // Send email (in a real application, you would send an actual email)
                $subject = "UniFlow Password Reset Request";
                $message = "Hello,\n\n";
                $message .= "We received a request to reset your password for your UniFlow account (Student ID: {$student['student_id']}).\n\n";
                $message .= "Please click the following link to reset your password:\n";
                $message .= $resetLink . "\n\n";
                $message .= "This link will expire in 1 hour.\n\n";
                $message .= "If you didn't request this password reset, please ignore this email.\n\n";
                $message .= "Best regards,\nThe UniFlow Team";
                
                // In a production environment, you would use a mail library like PHPMailer
                // For this example, we'll just log it
                error_log("Password reset email to $email:\n$message");
                
                $success = true;
            } else {
                // For security, don't reveal if email doesn't exist
                $success = true;
            }
        } catch (Exception $e) {
            $errors['general'] = 'An error occurred. Please try again later.';
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - UniFlow Student Loans</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

        /* Login Container */
        .login-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 120px 0 60px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 30px;
            padding: 50px;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.3);
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
            background-clip: text;
            margin-bottom: 15px;
        }

        .login-subtitle {
            font-size: 1.1rem;
            opacity: 0.8;
            margin-bottom: 30px;
        }

        /* Form Styling */
        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
        }

        .form-control {
            width: 100%;
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .form-control:focus {
            outline: none;
            border-color: #4ECDC4;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 20px rgba(78, 205, 196, 0.3);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
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
            color: white;
            width: 100%;
        }

        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(255, 107, 107, 0.4);
            background: linear-gradient(135deg, #FF8E53, #FF6B6B);
            color: white;
        }

        /* Security Indicators */
        .security-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(78, 205, 196, 0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            color: #4ECDC4;
            margin-bottom: 20px;
        }

        .security-badge i {
            margin-right: 8px;
        }

        /* Validation Feedback */
        .invalid-feedback {
            display: block;
            color: #FF6B6B;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .form-control.is-invalid {
            border-color: #FF6B6B;
            box-shadow: 0 0 20px rgba(255, 107, 107, 0.3);
        }

        /* Additional styles for error display */
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

        /* Back to login link */
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }

        .back-to-login a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .back-to-login a:hover {
            color: #4ECDC4;
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .login-container {
                padding: 30px 20px;
                margin: 20px;
            }
            
            .login-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animated"></div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom fixed-top">
        <div class="container">
            <a class="navbar-brand" href="../index.html">
                <i class="fas fa-graduation-cap me-2"></i>UniFlow
            </a>
            <div class="d-flex gap-3">
                <a href="login.php" class="btn btn-outline-light btn-sm">Back to Login</a>
            </div>
        </div>
    </nav>

    <!-- Forgot Password Section -->
    <section class="login-section">
        <div class="container">
            <div class="login-container">
                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle me-2"></i>If an account with that email exists, we've sent a password reset link. Please check your email.
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors['general'])): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($errors['general']); ?>
                    </div>
                <?php endif; ?>

                <div class="login-header">
                    <h1 class="login-title">Forgot Password</h1>
                    <p class="login-subtitle">Enter your email address to reset your password</p>
                    <div class="security-badge">
                        <i class="fas fa-shield-alt"></i>
                        256-bit SSL Encryption • Bank-level Security
                    </div>
                </div>

                <!-- Forgot Password Form -->
                <form id="forgotPasswordForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                               name="email" placeholder="Enter your registered email address" 
                               value="<?php echo htmlspecialchars($email); ?>" required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary-custom" id="resetBtn">
                            Send Reset Link <i class="fas fa-paper-plane ms-2"></i>
                        </button>
                    </div>
                </form>

                <!-- Back to Login Link -->
                <div class="back-to-login">
                    <a href="login.php"><i class="fas fa-arrow-left me-2"></i>Back to login</a>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form submission
        document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            const resetBtn = document.getElementById('resetBtn');
            const originalText = resetBtn.innerHTML;
            
            // Show loading state
            resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Sending...';
            resetBtn.disabled = true;
            
            // If there are errors, reset the button after 2 seconds
            <?php if (!empty($errors)): ?>
                setTimeout(() => {
                    resetBtn.innerHTML = originalText;
                    resetBtn.disabled = false;
                }, 2000);
            <?php endif; ?>
        });
    </script>
</body>
</html>