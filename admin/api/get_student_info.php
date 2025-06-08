<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

header('Content-Type: application/json');

// Get database connection
$pdo = DatabaseConnection::getInstance()->getConnection();

$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($studentId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid student ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, student_id, full_name 
                          FROM students 
                          WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        echo json_encode($student);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>