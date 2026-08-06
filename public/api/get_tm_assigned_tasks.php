<?php
/**
 * Tasks assigned to the signed-in Team Member. Member id comes from the token.
 * Preserves the original response shape: { "tasks": [...] }.
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_employee(['Team Member']);
$employee_id = (int) $claims['sub'];

try {
    $stmt = $pdo->prepare("SELECT task_id, title, due_date, task_priority, task_status
                           FROM tasks
                           WHERE ? IN (tm1, tm2, tm3)
                           ORDER BY due_date ASC");
    $stmt->execute([$employee_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    api_error('Could not load tasks.', 500, $e);
}

api_respond(['tasks' => $tasks]);
