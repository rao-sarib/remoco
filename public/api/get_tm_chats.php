<?php
/**
 * Chat rooms for the signed-in Team Member. Member id comes from the token; a
 * member sees only chats they participate in.
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_employee(['Team Member']);
$tm_id = (int) $claims['sub'];

try {
    $stmt = $pdo->prepare("SELECT chat_id, chat_title, task_id, firebase_room_id
                           FROM chats WHERE ? IN (tm1_id, tm2_id, tm3_id)");
    $stmt->execute([$tm_id]);
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    api_error('Could not load chats.', 500, $e);
}

api_respond(['status' => 'success', 'chats' => $chats]);
