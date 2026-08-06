<?php
/**
 * Project Manager task statistics. The PM id comes from the token, so the figures
 * are always the caller's own.
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_employee(['Project Manager']);
$pm_id = (int) $claims['sub'];

try {
    $stmt = $pdo->prepare("SELECT
            COUNT(*) AS total_tasks,
            SUM(task_status = 'Not Started') AS not_started,
            SUM(task_status = 'In Progress') AS in_progress,
            SUM(task_status = 'Completed')   AS completed,
            SUM(task_priority = 'High')      AS `high_priority`
        FROM tasks WHERE assigned_by = ?");
    $stmt->execute([$pm_id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    api_error('Could not load statistics.', 500, $e);
}

api_respond([
    'status' => 'success',
    'data'   => [
        'total_tasks'   => (int) $r['total_tasks'],
        'not_started'   => (int) $r['not_started'],
        'in_progress'   => (int) $r['in_progress'],
        'completed'     => (int) $r['completed'],
        'high_priority' => (int) $r['high_priority'],
    ],
]);
