<?php
// config.php - Configuration file for database and application settings

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'uniflow_db');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'Your Application Name');
define('APP_URL', 'http://localhost');
define('APP_DEBUG', true); // Set to false in production

// Security Configuration
define('SECRET_KEY', 'your-secret-key-here'); // Change this to a random string
define('ENCRYPTION_METHOD', 'AES-256-CBC');

// Session Configuration
define('SESSION_LIFETIME', 3600); // Session timeout in seconds (1 hour)
define('SESSION_NAME', 'app_session');

// Error Reporting (for development)
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone Configuration
date_default_timezone_set('UTC');

// File Upload Configuration
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('UPLOAD_PATH', 'uploads/');
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);
?>