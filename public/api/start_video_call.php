<?php
/**
 * Start a video call on a chat. The caller must be a participant of that chat.
 * The initiator is the token's employee id. Returns the Agora channel + App ID.
 *
 * This is the endpoint the client calls (start_video_call.php); video_call.php is
 * kept as an alias to it.
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_employee();
$employee_id = (int) $claims['sub'];

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_error('Invalid request method.', 405);
}

// Accept either form fields or a JSON body.
$in = $_POST ?: json_body();
$chat_id = isset($in['chat_id']) ? (int) $in['chat_id'] : 0;
if (!$chat_id) {
    api_error('Missing chat_id.');
}

require_chat_participant($pdo, $chat_id, $employee_id);

try {
    $channel = 'remoco_' . bin2hex(random_bytes(8));
    $stmt = $pdo->prepare("INSERT INTO video_calls (chat_id, call_start, initiator_id, agora_channel)
                           VALUES (?, NOW(), ?, ?)");
    $stmt->execute([$chat_id, $employee_id, $channel]);
} catch (PDOException $e) {
    api_error('Failed to initiate call.', 500, $e);
}

api_respond([
    'status'  => 'success',
    'channel' => $channel,
    'appId'   => AGORA_APP_ID,
]);
