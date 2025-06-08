<?php
// export_logs.php - Admin Logs Export Endpoint
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/dbconnection.php');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Check admin authentication
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// Get database connection
$pdo = DatabaseConnection::getInstance()->getConnection();

// Get export parameters
$format = isset($_POST['format']) ? strtolower($_POST['format']) : 'csv';
$filter = isset($_POST['filter']) ? $_POST['filter'] : 'all';
$search = isset($_POST['search']) ? trim($_POST['search']) : '';

// Validate export format
if (!in_array($format, ['csv', 'pdf'])) {
    http_response_code(400);
    exit('Invalid export format');
}

// Build query with same filters as admin_logs.php
$query = "SELECT * FROM admin_logs";
$params = [];

if ($filter !== 'all') {
    $query .= " WHERE action_type = ?";
    $params[] = $filter;
}

if ($search !== '') {
    $query .= ($filter !== 'all' ? " AND" : " WHERE") . " (details LIKE ? OR target_id = ?)";
    $params[] = "%$search%";
    
    if (is_numeric($search)) {
        $params[] = $search;
    } else {
        $query = str_replace("OR target_id = ?", "", $query);
    }
}

// Add sorting
$query .= " ORDER BY performed_at DESC";

// Get logs
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Action type descriptions
$action_descriptions = [
    'student_edit' => 'Student Profile Edit',
    'loan_approve' => 'Loan Approval',
    'loan_reject' => 'Loan Rejection',
    'payment_view' => 'Payment Viewed',
    'defaulters_view' => 'Defaulters List Viewed',
    'system_change' => 'System Configuration Change'
];

// Generate export file based on format
switch ($format) {
    case 'csv':
        exportCSV($logs, $action_descriptions);
        break;
    case 'pdf':
        exportPDF($logs, $action_descriptions);
        break;
}

function exportCSV($logs, $action_descriptions) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="admin_logs_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV header
    fputcsv($output, ['Action Type', 'Description', 'Target ID', 'Details', 'Timestamp']);
    
    // CSV rows
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['action_type'],
            $action_descriptions[$log['action_type']] ?? ucfirst(str_replace('_', ' ', $log['action_type'])),
            $log['target_id'] ?: 'N/A',
            $log['details'] ?: 'No details',
            date('Y-m-d H:i:s', strtotime($log['performed_at']))
        ]);
    }
    
    fclose($output);
    exit;
}

function exportPDF($logs, $action_descriptions) {
    require_once($_SERVER['DOCUMENT_ROOT'] . '/Uniflow/vendor/autoload.php');
    
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'L'
    ]);
    
    // PDF header
    $html = '
    <style>
        body { font-family: Arial; }
        h1 { color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #2c3e50; color: white; padding: 8px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        .logo { text-align: center; margin-bottom: 20px; }
    </style>
    <div class="logo">
        <h1>Uniflow Admin Activity Logs</h1>
        <p>Generated on ' . date('F j, Y \a\t H:i:s') . '</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>Action</th>
                <th>Description</th>
                <th>Target ID</th>
                <th>Details</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>';
    
    // PDF rows
    foreach ($logs as $log) {
        $html .= '
        <tr>
            <td>' . htmlspecialchars($log['action_type']) . '</td>
            <td>' . htmlspecialchars($action_descriptions[$log['action_type']] ?? ucfirst(str_replace('_', ' ', $log['action_type']))) . '</td>
            <td>' . ($log['target_id'] ?: 'N/A') . '</td>
            <td>' . htmlspecialchars($log['details'] ?: 'No details') . '</td>
            <td>' . date('Y-m-d H:i:s', strtotime($log['performed_at'])) . '</td>
        </tr>';
    }
    
    $html .= '
        </tbody>
    </table>
    <div style="text-align: center; margin-top: 20px; font-size: 0.8em; color: #7f8c8d;">
        ' . count($logs) . ' log entries
    </div>';
    
    $mpdf->WriteHTML($html);
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="admin_logs_' . date('Y-m-d') . '.pdf"');
    
    $mpdf->Output();
    exit;
}