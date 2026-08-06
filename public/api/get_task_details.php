<?php
/**
 * Full task detail plus the company's team members (for reassignment UI).
 * The task must belong to the caller's company; the company_id is the token's,
 * not the request's. Preserves the response shape:
 *   { task, assigned_by_name, team_members }
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_auth();
$company_id = caller_company($claims);

$task_id = isset($_GET['task_id']) && is_numeric($_GET['task_id']) ? (int) $_GET['task_id'] : 0;
if (!$task_id) {
    api_error('Invalid task ID.');
}

try {
    $task = require_task_in_company($pdo, $task_id, $company_id);

    $assignedByName = 'Unknown';
    if ($task['assigned_by']) {
        $stmt = $pdo->prepare("SELECT employee_name FROM employees WHERE employee_id = ?");
        $stmt->execute([$task['assigned_by']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $assignedByName = $row['employee_name'];
        }
    }

    $stmt = $pdo->prepare("SELECT employee_id, employee_name FROM employees
                           WHERE company_id = ? AND designation = 'Team Member'");
    $stmt->execute([$company_id]);
    $teamMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    api_error('Could not load task.', 500, $e);
}

api_respond([
    'task'             => $task,
    'assigned_by_name' => $assignedByName,
    'team_members'     => $teamMembers,
]);
