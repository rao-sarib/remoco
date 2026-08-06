<?php
/**
 * Shared files for a chat. The caller must be a participant of that chat.
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_employee();
$employee_id = (int) $claims['sub'];

$chat_id = isset($_GET['chat_id']) ? (int) $_GET['chat_id'] : 0;
if (!$chat_id) {
    api_error('Missing chat_id parameter.');
}

try {
    require_chat_participant($pdo, $chat_id, $employee_id);

    $stmt = $pdo->prepare("SELECT file_name, file_path, uploaded_by
                           FROM shared_files WHERE chat_id = ?");
    $stmt->execute([$chat_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    api_error('Could not load files.', 500, $e);
}

api_respond(['status' => 'success', 'files' => $files]);
