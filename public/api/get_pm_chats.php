<?php
/**
 * Chat rooms for the signed-in Project Manager. The PM id comes from the token,
 * so a caller can only ever list their own chats.
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_employee(['Project Manager']);
$pm_id = (int) $claims['sub'];

try {
    $stmt = $pdo->prepare("SELECT chat_id, chat_title, task_id, firebase_room_id
                           FROM chats WHERE pm_id = ?");
    $stmt->execute([$pm_id]);
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    api_error('Could not load chats.', 500, $e);
}

api_respond(['status' => 'success', 'chats' => $chats]);
