<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

header('Content-Type: application/json');

// Get database connection
$pdo = DatabaseConnection::getInstance()->getConnection();

$query = isset($_GET['query']) ? trim($_GET['query']) : '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, student_id, full_name 
                          FROM students 
                          WHERE student_id LIKE :query OR full_name LIKE :query
                          LIMIT 10");
    $stmt->execute([':query' => "%$query%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>