<?php
/**
 * A Team Lead assigns members to one of their tasks and defines its checkpoints,
 * which also provisions the task's chat room. The caller must be the Team Lead
 * the task is delegated to, and every submitted member must be a Team Member of
 * the same company.
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_employee(['Team Lead']);
$tl_id = (int) $claims['sub'];
$company_id = caller_company($claims);

$in = json_body();
if (!$in) {                       // the client may post form fields instead
    $in = $_POST;
}
$task_id = isset($in['task_id']) ? (int) $in['task_id'] : 0;
$tm1 = !empty($in['tm1']) ? (int) $in['tm1'] : null;
$tm2 = !empty($in['tm2']) ? (int) $in['tm2'] : null;
$tm3 = !empty($in['tm3']) ? (int) $in['tm3'] : null;
$checkpoints = (isset($in['checkpoints']) && is_array($in['checkpoints'])) ? $in['checkpoints'] : [];

if (!$task_id || !$tm1) {
    api_error('Task ID and Team Member 1 are required.');
}

try {
    // The task must be delegated to this Team Lead.
    $stmt = $pdo->prepare("SELECT title, assigned_by FROM tasks
                           WHERE task_id = ? AND team_lead_id = ?");
    $stmt->execute([$task_id, $tl_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) {
        api_error('Task not found.', 404);
    }

    // Every submitted member must be a Team Member of this company.
    $memberCheck = $pdo->prepare("SELECT COUNT(*) FROM employees
        WHERE employee_id = ? AND company_id = ? AND designation = 'Team Member'");
    foreach ([$tm1, $tm2, $tm3] as $mid) {
        if ($mid === null) {
            continue;
        }
        $memberCheck->execute([$mid, $company_id]);
        if ((int) $memberCheck->fetchColumn() === 0) {
            api_error('One of the selected team members is not available in your company.');
        }
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE tasks
        SET tm1 = ?, tm2 = ?, tm3 = ?, task_status = 'In Progress'
        WHERE task_id = ?");
    $stmt->execute([$tm1, $tm2, $tm3, $task_id]);

    if ($checkpoints) {
        $cp = $pdo->prepare("INSERT INTO checkpoints (task_id, checkpoint, status)
                             VALUES (?, ?, 'Pending')");
        foreach ($checkpoints as $c) {
            $c = trim((string) $c);
            if ($c !== '') {
                $cp->execute([$task_id, $c]);
            }
        }
    }

    $firebase_room_id = bin2hex(random_bytes(10));
    $stmt = $pdo->prepare("INSERT INTO chats
        (chat_title, task_id, pm_id, tl_id, tm1_id, tm2_id, tm3_id, firebase_room_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $task['title'], $task_id, $task['assigned_by'], $tl_id,
        $tm1, $tm2, $tm3, $firebase_room_id,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_error('Could not assign the task.', 500, $e);
}

api_respond(['success' => true]);
