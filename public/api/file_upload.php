<?php
/**
 * Upload a file into a chat. The caller must be a participant of that chat, and
 * the uploader is the token's employee id (not a client-supplied value). Uploads
 * are validated against an extension allowlist and a size cap, sanitised, and
 * given a unique name. The file URL is derived from the request so it resolves
 * wherever the API is served.
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_employee();
$employee_id = (int) $claims['sub'];

if (!isset($_POST['chat_id']) || !isset($_FILES['file'])) {
    api_error('Missing chat_id or file.');
}

$chat_id = (int) $_POST['chat_id'];
require_chat_participant($pdo, $chat_id, $employee_id);

$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    api_error('File upload failed.');
}

// 10 MB cap.
if ($file['size'] > 10 * 1024 * 1024) {
    api_error('File is too large (10 MB maximum).');
}

$allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx',
            'ppt', 'pptx', 'txt', 'csv', 'zip'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed, true)) {
    api_error('File type not allowed.');
}

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$original = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
$stored   = uniqid() . '_' . $original;
$target   = $uploadDir . $stored;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    api_error('Could not store the file.', 500);
}

// Build a URL back to this api/uploads directory from the current request.
$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api/file_upload.php')), '/');
$fileUrl = $scheme . '://' . $host . $dir . '/uploads/' . rawurlencode($stored);

try {
    $stmt = $pdo->prepare("INSERT INTO shared_files (chat_id, file_name, file_path, uploaded_by)
                           VALUES (?, ?, ?, ?)");
    $stmt->execute([$chat_id, $stored, $fileUrl, $employee_id]);
} catch (PDOException $e) {
    @unlink($target);   // roll the stored file back if the row fails
    api_error('Could not record the file.', 500, $e);
}

api_respond([
    'status'  => 'success',
    'message' => 'File uploaded successfully',
    'file'    => ['name' => $stored, 'path' => $fileUrl, 'uploaded_by' => $employee_id],
]);
