<?php
require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_json();

    $chat_id = (int)($_POST['chat_id'] ?? 0);
    $initiator_id = (int)$_SESSION['employee_id'];

    // Only participants of this chat may open a call channel on it.
    require_chat_participant($conn, $chat_id);
    $agora_channel = "remoco_" . bin2hex(random_bytes(8));
    
    $stmt = $conn->prepare("INSERT INTO video_calls 
                          (chat_id, call_start, initiator_id, agora_channel) 
                          VALUES (?, NOW(), ?, ?)");
    $stmt->bind_param("iis", $chat_id, $initiator_id, $agora_channel);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'channel' => $agora_channel,
            'appId' => AGORA_APP_ID
        ]);
    } else {
        error_log("Video call error: " . $stmt->error);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to initiate call'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>