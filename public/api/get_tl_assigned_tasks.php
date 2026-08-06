<?php
/**
 * Tasks delegated to the signed-in Team Lead. Lead id comes from the token.
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_employee(['Team Lead']);
$tl_id = (int) $claims['sub'];

try {
    $stmt = $pdo->prepare("SELECT task_id, title, due_date, task_priority, task_status
                           FROM tasks WHERE team_lead_id = ?
                           ORDER BY due_date ASC");
    $stmt->execute([$tl_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    api_error('Could not load tasks.', 500, $e);
}

api_respond(['status' => 'success', 'tasks' => $tasks]);
