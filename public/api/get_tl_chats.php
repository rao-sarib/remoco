<?php
/**
 * Chat rooms for the signed-in Team Lead. TL id comes from the token.
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_employee(['Team Lead']);
$tl_id = (int) $claims['sub'];

try {
    $stmt = $pdo->prepare("SELECT chat_id, chat_title, task_id, firebase_room_id
                           FROM chats WHERE tl_id = ?");
    $stmt->execute([$tl_id]);
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    api_error('Could not load chats.', 500, $e);
}

api_respond(['status' => 'success', 'chats' => $chats]);
