<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Initialize variables
$errors = [];
$success = false;
$universities = [];

// Form fields
$fields = [
    'full_name' => '',
    'fnb_account_number' => '',
    'university_id' => 0,
    'student_id' => '',
    'allowance_day' => null
];

// Fetch universities from database
try {
    $db = DatabaseConnection::getInstance()->getConnection();
    $stmt = $db->query("SELECT id, name, location FROM universities ORDER BY name");
    $universities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors['database'] = "Unable to load university list. Please try again later.";
    error_log("University list error: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required = [
        'full_name' => 'Full name is required',
        'university_id' => 'Please select your university',
        'student_id' => 'Student ID is required',
        'password' => 'Password is required',
    ];

    foreach ($required as $field => $message) {
        if (empty($_POST[$field])) {
            $errors[$field] = $message;
        }
    }

    // Validate password strength
    if (!empty($_POST['password']) && strlen($_POST['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long';
    }

    // Validate password match
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

    // Validate terms agreement
    if (!isset($_POST['agree_terms'])) {
        $errors['agree_terms'] = 'You must agree to the terms and conditions';
    }

    // Sanitize inputs
    foreach ($fields as $field => $default) {
        $fields[$field] = !empty($_POST[$field]) ? trim($_POST[$field]) : $default;
    }
    $fields['university_id'] = (int)$fields['university_id'];
    $fields['allowance_day'] = !empty($_POST['allowance_day']) ? (int)$_POST['allowance_day'] : null;

    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            $pdo = DatabaseConnection::getInstance()->getConnection();
            
            // Check if student exists
            $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
            $stmt->execute([$fields['student_id']]);
            
            if ($stmt->fetch()) {
                $errors['general'] = 'A student with this student ID already exists';
            } else {
                // Hash password
                $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                
                // Insert student
                $stmt = $pdo->prepare("INSERT INTO students 
                    (student_id, university_id, full_name, fnb_account_number, password_hash, is_verified) 
                    VALUES (?, ?, ?, ?, ?, 1)");
                
                $stmt->execute([
                    $fields['student_id'],
                    $fields['university_id'],
                    $fields['full_name'],
                    $fields['fnb_account_number'],
                    $password_hash
                ]);
                
                $student_id = $pdo->lastInsertId();
                
                // Insert financial profile
                $stmt = $pdo->prepare("INSERT INTO student_financial_profiles 
                    (student_id, university_id, ai_risk_score, dynamic_loan_limit, allowance_day) 
                    VALUES (?, ?, 50.00, 500.00, ?)");
                
                $stmt->execute([
                    $student_id,
                    $fields['university_id'],
                    $fields['allowance_day']
                ]);
                
                $success = true;
                
                // Set success message and redirect
                $_SESSION['registration_success'] = true;
                header("Location: login.php");
                exit;
            }
        } catch (PDOException $e) {
            $errors['general'] = 'Registration failed. Please try again later.';
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - UniFlow Student Loans</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0a1628 0%, #1e3a5f 100%);
            min-height: 100vh;
            color: white;
        }
        .registration-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            max-width: 800px;
            margin: 80px auto;
        }
        .registration-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff, #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.08);
            color: white;
            border-color: #4ECDC4;
        }
        .btn-primary {
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
            border: none;
        }
        .university-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .university-card:hover, .university-card.selected {
            background: rgba(78, 205, 196, 0.1);
            border-color: #4ECDC4;
        }
        .error-message {
            color: #FF6B6B;
            background: rgba(255, 107, 107, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #FF6B6B;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="registration-container">
            <div class="text-center mb-5">
                <h1 class="registration-title">Join UniFlow</h1>
                <p class="text-muted">Start your journey to financial freedom</p>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="error-message mb-4">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($errors['general']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Personal Information -->
                <div class="mb-4">
                    <h4 class="mb-3"><i class="fas fa-user me-2"></i>Personal Information</h4>
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control <?= !empty($errors['full_name']) ? 'is-invalid' : '' ?>" 
                               name="full_name" value="<?= htmlspecialchars($fields['full_name']) ?>" required>
                        <?php if (!empty($errors['full_name'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['full_name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">FNB Account Number (Optional)</label>
                        <input type="text" class="form-control" 
                               name="fnb_account_number" value="<?= htmlspecialchars($fields['fnb_account_number']) ?>">
                        <small class="text-muted">Helps process loan disbursements faster</small>
                    </div>
                </div>

                <!-- University Information -->
                <div class="mb-4">
                    <h4 class="mb-3"><i class="fas fa-university me-2"></i>University Information</h4>
                    
                    <div class="mb-3">
                        <label class="form-label">Select Your University *</label>
                        <?php if (!empty($errors['university_id'])): ?>
                            <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['university_id']) ?></div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <?php foreach ($universities as $uni): ?>
                                <div class="col-md-6 mb-3">
                                  <div class="university-card" data-university-id="<?= $uni['id'] ?>">
    <input type="radio" name="university_id" value="<?= $uni['id'] ?>" 
           id="uni_<?= $uni['id'] ?>" <?= $fields['university_id'] == $uni['id'] ? 'checked' : '' ?>>
    <div class="university-name"><?= htmlspecialchars($uni['name']) ?></div>
    <div class="text-muted"><?= htmlspecialchars($uni['location']) ?></div>
</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Student ID Number *</label>
                        <input type="text" class="form-control <?= !empty($errors['student_id']) ? 'is-invalid' : '' ?>" 
                               name="student_id" value="<?= htmlspecialchars($fields['student_id']) ?>" required>
                        <?php if (!empty($errors['student_id'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['student_id']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Security Information -->
                <div class="mb-4">
                    <h4 class="mb-3"><i class="fas fa-lock me-2"></i>Security Setup</h4>
                    
                    <div class="mb-3">
                        <label class="form-label">Create Password *</label>
                        <div class="input-group">
                            <input type="password" class="form-control <?= !empty($errors['password']) ? 'is-invalid' : '' ?>" 
                                   name="password" id="password" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <?php if (!empty($errors['password'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div>
                        <?php endif; ?>
                        <small class="text-muted">Minimum 8 characters</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm Password *</label>
                        <div class="input-group">
                            <input type="password" class="form-control <?= !empty($errors['confirm_password']) ? 'is-invalid' : '' ?>" 
                                   name="confirm_password" id="confirm_password" required>
                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <?php if (!empty($errors['confirm_password'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['confirm_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">DTEF Allowance Day (Optional)</label>
                        <select class="form-select" name="allowance_day">
                            <option value="">Select allowance day</option>
                            <?php foreach ([1, 5, 10, 15, 20, 25, 30] as $day): ?>
                                <option value="<?= $day ?>" <?= $fields['allowance_day'] == $day ? 'selected' : '' ?>>
                                    <?= $day ?><sup><?= date('S', mktime(0, 0, 0, 0, $day, 0)) ?></sup> of the month
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input <?= !empty($errors['agree_terms']) ? 'is-invalid' : '' ?>" 
                               type="checkbox" name="agree_terms" id="agree_terms" <?= isset($_POST['agree_terms']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="agree_terms">
                            I agree to the <a href="#" class="text-decoration-none">Terms of Service</a> and 
                            <a href="#" class="text-decoration-none">Privacy Policy</a>
                        </label>
                        <?php if (!empty($errors['agree_terms'])): ?>
                            <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['agree_terms']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg py-3">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </button>
                </div>

                <div class="text-center mt-3">
                    <p class="text-muted">Already have an account? <a href="login.php" class="text-decoration-none">Sign in</a></p>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        
        // University card selection
        document.addEventListener('DOMContentLoaded', function() {
            const universityCards = document.querySelectorAll('.university-card');
            
            universityCards.forEach(card => {
                card.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    this.classList.add('selected');
                    
                    universityCards.forEach(otherCard => {
                        if (otherCard !== this) {
                            otherCard.classList.remove('selected');
                        }
                    });
                });
                
                // Initialize selected state
                if (card.querySelector('input[type="radio"]:checked')) {
                    card.classList.add('selected');
                }
            });
            
            // Password toggle functionality
            document.getElementById('togglePassword').addEventListener('click', function() {
                const password = document.getElementById('password');
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
            
            // Similar for confirm password toggle
        });
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });

        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const password = document.getElementById('confirm_password');
            const type = password.getAttribute('type') === 'password' : 'text' : 'password';
            password.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });

        // Simple client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            let valid = true;
            
            // Check required fields
            document.querySelectorAll('[required]').forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    valid = false;
                }
            });
            
            // Check password match
            const password = document.getElementById('password');
            const confirm = document.getElementById('confirm_password');
            
            if (password.value !== confirm.value) {
                confirm.classList.add('is-invalid');
                valid = false;
            }
            
            // Check terms agreement
            if (!document.getElementById('agree_terms').checked) {
                document.getElementById('agree_terms').classList.add('is-invalid');
                valid = false;
            }
            
            if (!valid) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>