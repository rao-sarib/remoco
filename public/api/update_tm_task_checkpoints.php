<?php
/**
 * A Team Member ticks checkpoints on a task they are assigned to. Task status is
 * recomputed from the checkpoint set on every call, in both directions, so the
 * task and its checkpoints never disagree. The member must be assigned to the
 * task, and only that task's own checkpoints are touched.
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_employee(['Team Member']);
$employee_id = (int) $claims['sub'];

$in = json_body();
if (!isset($in['task_id']) || !isset($in['checkpoints'])) {
    api_error('Missing task_id or checkpoints.');
}
$task_id = (int) $in['task_id'];
$completedIds = is_array($in['checkpoints']) ? array_map('intval', $in['checkpoints']) : [];

try {
    // Assignment is the authorization boundary.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE task_id = ? AND ? IN (tm1, tm2, tm3)");
    $stmt->execute([$task_id, $employee_id]);
    if ((int) $stmt->fetchColumn() === 0) {
        api_error('Task not found.', 404);
    }

    // Only this task's checkpoints are considered, so ids from other tasks are ignored.
    $stmt = $pdo->prepare("SELECT checkpoint_id FROM checkpoints WHERE task_id = ?");
    $stmt->execute([$task_id]);
    $all = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $set = $pdo->prepare("UPDATE checkpoints SET status = ?
                          WHERE checkpoint_id = ? AND task_id = ?");
    $done = 0;
    foreach ($all as $cpId) {
        $isDone = in_array($cpId, $completedIds, true);
        $set->execute([$isDone ? 'Completed' : 'Pending', $cpId, $task_id]);
        if ($isDone) {
            $done++;
        }
    }

    $total = count($all);
    if ($total > 0) {
        if ($done === $total) {
            $pdo->prepare("UPDATE tasks
                SET task_status = 'Completed',
                    completion_date = COALESCE(completion_date, CURDATE())
                WHERE task_id = ?")->execute([$task_id]);
        } elseif ($done === 0) {
            $pdo->prepare("UPDATE tasks SET task_status = 'Not Started', completion_date = NULL
                           WHERE task_id = ?")->execute([$task_id]);
        } else {
            $pdo->prepare("UPDATE tasks SET task_status = 'In Progress', completion_date = NULL
                           WHERE task_id = ?")->execute([$task_id]);
        }
    }
} catch (PDOException $e) {
    api_error('Could not update checkpoints.', 500, $e);
}

api_respond(['success' => true]);
