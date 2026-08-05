<?php
require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json');

// Security settings
$allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
$max_file_size = 10 * 1024 * 1024; // 10MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_json();

    $chat_id = (int)($_POST['chat_id'] ?? 0);
    $user_id = (int)$_SESSION['employee_id'];

    // Only participants of this chat may upload into it.
    require_chat_participant($conn, $chat_id);

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'File upload failed']);
        exit;
    }
    
    $file = $_FILES['file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $file_size = $file['size'];
    
    // Validate file
    if (!in_array($file_ext, $allowed_extensions)) {
        echo json_encode(['status' => 'error', 'message' => 'File type not allowed']);
        exit;
    }
    
    if ($file_size > $max_file_size) {
        echo json_encode(['status' => 'error', 'message' => 'File too large (max 10MB)']);
        exit;
    }
    
    // Generate safe filename
    $safe_name = preg_replace("/[^a-zA-Z0-9\.]/", "", basename($file['name']));
    $unique_name = uniqid() . '_' . $safe_name;
    $target_dir = __DIR__ . '/uploads/';
    $target_file = $target_dir . $unique_name;
    
    // Create directory if needed
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        $file_path = 'uploads/' . $unique_name;
        
        $stmt = $conn->prepare("INSERT INTO shared_files 
                              (chat_id, uploaded_by, file_name, file_path) 
                              VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $chat_id, $user_id, $safe_name, $file_path);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            unlink($target_file);
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'File move failed']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>