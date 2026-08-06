<?php
/**
 * Task detail for a Team Member's update screen: the task, the two names, and its
 * checkpoints. The member must actually be assigned to the task.
 * Preserves the response shape:
 *   { task, team_lead_name, assigned_by_name, checkpoints }
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_employee(['Team Member']);
$employee_id = (int) $claims['sub'];

$task_id = isset($_GET['task_id']) ? (int) $_GET['task_id'] : 0;
if (!$task_id) {
    api_error('Missing task_id.');
}

try {
    // Assignment is the authorization boundary.
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE task_id = ? AND ? IN (tm1, tm2, tm3)");
    $stmt->execute([$task_id, $employee_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) {
        api_error('Task not found.', 404);
    }

    $team_lead_name = '';
    if ($task['team_lead_id']) {
        $stmt = $pdo->prepare("SELECT employee_name FROM employees WHERE employee_id = ?");
        $stmt->execute([$task['team_lead_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $team_lead_name = $row ? $row['employee_name'] : 'Unknown';
    }

    $assigned_by_name = '';
    if ($task['assigned_by']) {
        $stmt = $pdo->prepare("SELECT employee_name FROM employees WHERE employee_id = ?");
        $stmt->execute([$task['assigned_by']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $assigned_by_name = $row ? $row['employee_name'] : 'Unknown';
    }

    $stmt = $pdo->prepare("SELECT * FROM checkpoints WHERE task_id = ?");
    $stmt->execute([$task_id]);
    $checkpoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    api_error('Could not load task.', 500, $e);
}

api_respond([
    'task'             => $task,
    'team_lead_name'   => $team_lead_name,
    'assigned_by_name' => $assigned_by_name,
    'checkpoints'      => $checkpoints,
]);
