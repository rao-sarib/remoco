<?php
require_once __DIR__ . '/session_bootstrap.php';

require_once __DIR__ . '/config.php';

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log("DB connection failed: " . $conn->connect_error);
    die("Database connection error. Please try again later.");
}

// Verify user session
if (!isset($_SESSION['employee_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    header("Location: login.php");
    exit;
}

/**
 * Is the signed-in employee a participant of this chat?
 *
 * Membership is defined by the chats row itself: the Project Manager, the Team
 * Lead, or one of the three assigned Team Members.
 */
function chat_participant(mysqli $conn, int $chat_id, int $employee_id): bool
{
    $stmt = $conn->prepare(
        "SELECT 1 FROM chats
         WHERE chat_id = ?
           AND ? IN (pm_id, tl_id, tm1_id, tm2_id, tm3_id)
         LIMIT 1"
    );
    $stmt->bind_param("ii", $chat_id, $employee_id);
    $stmt->execute();
    $found = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();

    return $found;
}

/**
 * Membership guard for the JSON endpoints. Refuses with a single generic message
 * so a caller cannot tell "no such chat" apart from "not your chat".
 */
function require_chat_participant(mysqli $conn, int $chat_id): void
{
    if (!chat_participant($conn, $chat_id, (int) $_SESSION['employee_id'])) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Chat not found']);
        exit;
    }
}
?>