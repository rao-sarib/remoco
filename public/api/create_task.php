<?php
/**
 * A Project Manager creates and delegates a task. The creator (assigned_by) is
 * the token's employee id, and the chosen Team Lead must be a Team Lead in the
 * caller's company. Inputs are validated rather than trusted.
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_employee(['Project Manager']);
$pm_id = (int) $claims['sub'];
$company_id = caller_company($claims);

$in = json_body();
$title        = trim($in['title'] ?? '');
$description  = trim($in['description'] ?? '');
$due_date     = trim($in['due_date'] ?? '');
$priority     = $in['priority'] ?? '';
$team_lead_id = $in['team_lead_id'] ?? '';

$errors = [];
if ($title === '')        $errors[] = 'Missing field: title';
if ($due_date === '')     $errors[] = 'Missing field: due_date';
if ($priority === '')     $errors[] = 'Missing field: priority';
if ($team_lead_id === '') $errors[] = 'Missing field: team_lead_id';

if (!$errors) {
    if (mb_strlen($title) > 255) {
        $errors[] = 'Title must be 255 characters or fewer';
    }
    $d = DateTime::createFromFormat('Y-m-d', $due_date);
    if (!$d || $d->format('Y-m-d') !== $due_date) {
        $errors[] = 'Due date must be a valid date (YYYY-MM-DD)';
    }
    if (!in_array($priority, ['High', 'Medium', 'Low'], true)) {
        $errors[] = 'Priority must be High, Medium or Low';
    }
    if (!ctype_digit((string) $team_lead_id)) {
        $errors[] = 'Invalid team lead';
    }
}

if (!$errors) {
    // The Team Lead must belong to this company.
    $chk = $pdo->prepare("SELECT COUNT(*) FROM employees
        WHERE employee_id = ? AND company_id = ? AND designation = 'Team Lead'");
    $chk->execute([(int) $team_lead_id, $company_id]);
    if ((int) $chk->fetchColumn() === 0) {
        $errors[] = 'Selected team lead is not available in your company';
    }
}

if ($errors) {
    api_respond(['status' => 'error', 'errors' => $errors]);
}

try {
    // completion_date stays NULL until the task actually completes.
    $stmt = $pdo->prepare("INSERT INTO tasks
        (title, task_description, due_date, task_priority, team_lead_id,
         task_status, assigned_by, completion_date, tm1, tm2, tm3)
        VALUES (?, ?, ?, ?, ?, 'Not Started', ?, NULL, NULL, NULL, NULL)");
    $stmt->execute([$title, $description, $due_date, $priority, (int) $team_lead_id, $pm_id]);
} catch (PDOException $e) {
    api_respond(['status' => 'error', 'errors' => ['Could not create the task.']], 500);
}

api_respond(['status' => 'success', 'message' => 'Task created successfully!']);
