<?php
// logout.php - Secure admin logout handler with activity logging

require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Start session
session_start();

// Only proceed if admin is actually logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // Get database connection
    $pdo = DatabaseConnection::getInstance()->getConnection();
    
    try {
        // Log the logout activity
        $logStmt = $pdo->prepare("
            INSERT INTO admin_logs (action_type, target_id, details) 
            VALUES ('admin_logout', ?, 'Admin logged out')
        ");
        $logStmt->execute([$_SESSION['admin_id'] ?? null]);
    } catch (PDOException $e) {
        error_log("Failed to log admin logout: " . $e->getMessage());
    }
}

// Completely destroy the session
$_SESSION = array();

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page with success message
$_SESSION['logout_success'] = "You have been successfully logged out.";
header("Location: /Uniflow/admin/login.php");
exit();
?>