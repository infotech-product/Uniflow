<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Start session and check authentication
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Start session and check authentication
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}
$_SESSION['admin_logged_in'] = true;
// Get database connection
$pdo = DatabaseConnection::getInstance()->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_admin'])) {
        // Add new admin
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO administrators 
                                  (username, password_hash, email, full_name, created_at) 
                                  VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$username, $password, $email, $full_name]);
            
            $_SESSION['success_message'] = "Administrator $username successfully created";
            header('Location: admin_management.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error creating administrator: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_admin'])) {
        // Update existing admin
        $admin_id = $_POST['admin_id'];
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            $stmt = $pdo->prepare("UPDATE administrators 
                                  SET email = ?, full_name = ?, is_active = ?
                                  WHERE id = ?");
            $stmt->execute([$email, $full_name, $is_active, $admin_id]);
            
            $_SESSION['success_message'] = "Administrator updated successfully";
            header('Location: admin_management.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error updating administrator: " . $e->getMessage();
        }
    } elseif (isset($_POST['reset_password'])) {
        // Reset admin password
        $admin_id = $_POST['admin_id'];
        $password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("UPDATE administrators 
                                  SET password_hash = ?
                                  WHERE id = ?");
            $stmt->execute([$password, $admin_id]);
            
            $_SESSION['success_message'] = "Password reset successfully";
            header('Location: admin_management.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error resetting password: " . $e->getMessage();
        }
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $admin_id = $_GET['delete'];
    
    try {
        // Prevent deleting the last active super admin
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM administrators WHERE is_super_admin = 1 AND id != ?");
        $stmt->execute([$admin_id]);
        $remaining_super_admins = $stmt->fetchColumn();
        
        if ($remaining_super_admins > 0) {
            $stmt = $pdo->prepare("DELETE FROM administrators WHERE id = ?");
            $stmt->execute([$admin_id]);
            $_SESSION['success_message'] = "Administrator deleted successfully";
        } else {
            $_SESSION['error_message'] = "Cannot delete the last active super administrator";
        }
        
        header('Location: admin_management.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting administrator: " . $e->getMessage();
        header('Location: admin_management.php');
        exit();
    }
}

// Get all administrators
$admins = [];
try {
    $stmt = $pdo->query("SELECT * FROM administrators ORDER BY is_super_admin DESC, full_name ASC");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching administrators: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management | Uniflow</title>
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
            
        .admin-card {
            transition: all 0.3s ease;
            border-left: 5px solid transparent;
        }
        .admin-card.super-admin {
            border-left-color: #6A8DFF;
        }
        .admin-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        .badge-super-admin {
            background-color: rgba(106, 141, 255, 0.2);
            color: #6A8DFF;
        }
        .badge-active {
            background-color: rgba(78, 205, 196, 0.2);
            color: #4ECDC4;
        }
        .badge-inactive {
            background-color: rgba(255, 107, 107, 0.2);
            color: #FF6B6B;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }
        .form-section {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
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
                    <h1 class="welcome-title">Administrator Management</h1>
                    <p class="welcome-subtitle">Manage system administrator accounts and permissions</p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                        <i class="bi bi-plus-circle-fill me-2"></i>Add Administrator
                    </button>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success glass" role="alert">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger glass" role="alert">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Administrators List -->
        <div class="dashboard-card glass visible mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Administrators</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($admins)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $admin): ?>
                                    <tr class="admin-card <?php echo $admin['is_super_admin'] ? 'super-admin' : ''; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($admin['full_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $admin['is_super_admin'] ? 'badge-super-admin' : ''; ?>">
                                                <?php echo $admin['is_super_admin'] ? 'Super Admin' : 'Admin'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $admin['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                                <?php echo $admin['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $admin['last_login'] ? date('M d, Y H:i', strtotime($admin['last_login'])) : 'Never'; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editAdminModal"
                                                        onclick="loadAdminData(<?php echo $admin['id']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#resetPasswordModal"
                                                        onclick="setAdminId(<?php echo $admin['id']; ?>)">
                                                    <i class="bi bi-key"></i>
                                                </button>
                                                <?php if ($_SESSION['admin_id'] != $admin['id']): ?>
                                                    <a href="admin_management.php?delete=<?php echo $admin['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger"
                                                       onclick="return confirm('Are you sure you want to delete this administrator?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-people display-4 text-muted mb-3"></i>
                        <h5>No administrators found</h5>
                        <p class="text-muted">Add your first administrator using the button above</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-dark">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Add New Administrator</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="addUsername" class="form-label">Username</label>
                            <input type="text" class="form-control" id="addUsername" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="addPassword" class="form-label">Password</label>
                            <input type="password" class="form-control" id="addPassword" name="password" required>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                            </div>
                            <small class="text-muted">Minimum 8 characters with uppercase, lowercase, and number</small>
                        </div>
                        <div class="mb-3">
                            <label for="addEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="addEmail" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="addFullName" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="addFullName" name="full_name" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="add_admin" class="btn btn-success">
                                <i class="bi bi-plus-circle-fill me-2"></i>Create Administrator
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Admin Modal -->
    <div class="modal fade" id="editAdminModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-dark">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Edit Administrator</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" id="editAdminId" name="admin_id">
                        <div class="mb-3">
                            <label for="editUsername" class="form-label">Username</label>
                            <input type="text" class="form-control" id="editUsername" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="editEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="editEmail" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="editFullName" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="editFullName" name="full_name" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="editIsActive" name="is_active">
                            <label class="form-check-label" for="editIsActive">Active Account</label>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="update_admin" class="btn btn-primary">
                                <i class="bi bi-save-fill me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-dark">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" id="resetAdminId" name="admin_id">
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="newPassword" name="new_password" required>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="resetPasswordStrengthBar"></div>
                            </div>
                            <small class="text-muted">Minimum 8 characters with uppercase, lowercase, and number</small>
                        </div>
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirmPassword" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="reset_password" class="btn btn-warning">
                                <i class="bi bi-key-fill me-2"></i>Reset Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Password strength indicator
        function checkPasswordStrength(password, strengthBarId) {
            const strengthBar = document.getElementById(strengthBarId);
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength += 1;
            if (password.length >= 12) strength += 1;
            
            // Character type checks
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[a-z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            // Update strength bar
            const width = (strength / 6) * 100;
            strengthBar.style.width = `${width}%`;
            
            // Update color
            if (width < 40) {
                strengthBar.style.backgroundColor = '#FF6B6B';
            } else if (width < 70) {
                strengthBar.style.backgroundColor = '#FF8E53';
            } else {
                strengthBar.style.backgroundColor = '#4ECDC4';
            }
        }
        
        // Event listeners for password fields
        document.getElementById('addPassword')?.addEventListener('input', function() {
            checkPasswordStrength(this.value, 'passwordStrengthBar');
        });
        
        document.getElementById('newPassword')?.addEventListener('input', function() {
            checkPasswordStrength(this.value, 'resetPasswordStrengthBar');
        });
        
        // Form validation for password confirmation
        document.querySelector('#resetPasswordModal form')?.addEventListener('submit', function(e) {
            const password = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                e.preventDefault();
            }
            
            if (password.length < 8) {
                alert('Password must be at least 8 characters long!');
                e.preventDefault();
            }
        });
        
        // Load admin data for editing
        function loadAdminData(adminId) {
            $.ajax({
                url: '/Uniflow/admin/api/get_admin.php',
                method: 'GET',
                data: { id: adminId },
                dataType: 'json',
                success: function(data) {
                    document.getElementById('editAdminId').value = data.id;
                    document.getElementById('editUsername').value = data.username;
                    document.getElementById('editEmail').value = data.email;
                    document.getElementById('editFullName').value = data.full_name;
                    document.getElementById('editIsActive').checked = data.is_active == 1;
                },
                error: function(xhr, status, error) {
                    console.error("Error loading admin data:", error);
                    alert("Error loading administrator information. Please try again.");
                }
            });
        }
        
        // Set admin ID for password reset
        function setAdminId(adminId) {
            document.getElementById('resetAdminId').value = adminId;
        }
    </script>
</body>
</html>