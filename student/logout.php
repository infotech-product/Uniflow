<?php
session_start();

// Include database connection if needed for logging

require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Optional: Log the logout activity
if (isset($_SESSION['student_id'])) {
    $student_id = $_SESSION['student_id'];
    
    // You can add logging here if needed
    // Example: Log logout time to database
    /*
    try {
        $stmt = $pdo->prepare("UPDATE students SET last_logout = NOW() WHERE id = ?");
        $stmt->execute([$student_id]);
    } catch (PDOException $e) {
        // Handle error silently in logout
        error_log("Logout logging error: " . $e->getMessage());
    }
    */
}

// Unset all session variables
$_SESSION = array();

// Delete the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear any remember me cookies if you have them
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
    unset($_COOKIE['remember_token']);
}

// Prevent caching of this page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login page with success message
header("Location: login.php?logout=success");
exit();
?>