<?php
require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json');

if (isset($_GET['chat_id'])) {
    $chat_id = (int)$_GET['chat_id'];

    // Only participants of this chat may list its files.
    require_chat_participant($conn, $chat_id);

    $stmt = $conn->prepare("
        SELECT f.*, e.employee_name 
        FROM shared_files f
        JOIN employees e ON f.uploaded_by = e.employee_id
        WHERE f.chat_id = ?
        ORDER BY upload_time DESC
    ");
    $stmt->bind_param("i", $chat_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $files = [];
    while ($row = $result->fetch_assoc()) {
        $files[] = [
            'id' => $row['file_id'],
            'name' => $row['file_name'],
            'path' => $row['file_path'],
            'uploader' => $row['employee_name'],
            'time' => date('M d, Y h:i A', strtotime($row['upload_time']))
        ];
    }
    
    echo json_encode(['status' => 'success', 'files' => $files]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Missing chat ID']);
}
?>