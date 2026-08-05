<?php
/**
 * Reporting for the Team Lead role: the tasks delegated to this lead, with
 * checkpoint progress and member workload.
 * Loaded as an AJAX fragment by tl_dashboard.php, so no <html>/<body> wrapper.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'employee'
    || $_SESSION['designation'] !== 'Team Lead' || !isset($_SESSION['employee_id'])) {
    die("Access denied");
}

require_once __DIR__ . '/../includes/config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("TL reports: database error: " . $e->getMessage());
    echo '<div class="rpt-error">Reports are unavailable right now. Please try again later.</div>';
    return;
}

$tl_id    = (int) $_SESSION['employee_id'];
$totals   = ['total' => 0, 'not_started' => 0, 'in_progress' => 0, 'completed' => 0, 'overdue' => 0];
$progress = [];
$workload = [];
$error    = null;

try {
    $stmt = $pdo->prepare("SELECT
            COUNT(*) AS total,
            SUM(task_status = 'Not Started') AS not_started,
            SUM(task_status = 'In Progress') AS in_progress,
            SUM(task_status = 'Completed')   AS completed,
            SUM(due_date IS NOT NULL AND due_date < CURDATE() AND task_status <> 'Completed') AS overdue
        FROM tasks WHERE team_lead_id = ?");
    $stmt->execute([$tl_id]);
    foreach ($stmt->fetch(PDO::FETCH_ASSOC) as $k => $v) {
        $totals[$k] = (int) $v;
    }

    // Checkpoint completion per task.
    $stmt = $pdo->prepare("SELECT t.task_id, t.title, t.task_status, t.due_date,
                                  COUNT(c.checkpoint_id) AS checkpoints,
                                  SUM(c.status = 'Completed') AS done
                           FROM tasks t
                           LEFT JOIN checkpoints c ON c.task_id = t.task_id
                           WHERE t.team_lead_id = ?
                           GROUP BY t.task_id, t.title, t.task_status, t.due_date
                           ORDER BY t.due_date IS NULL, t.due_date ASC");
    $stmt->execute([$tl_id]);
    $progress = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // How many of this lead's tasks each member is attached to.
    $stmt = $pdo->prepare("SELECT e.employee_name,
                                  SUM(t.tm1 = e.employee_id) AS as_first,
                                  SUM(t.tm2 = e.employee_id) AS as_second,
                                  SUM(t.tm3 = e.employee_id) AS as_third
                           FROM employees e
                           JOIN tasks t ON t.team_lead_id = :tl
                                       AND e.employee_id IN (t.tm1, t.tm2, t.tm3)
                           WHERE e.company_id = :company
                           GROUP BY e.employee_id, e.employee_name
                           ORDER BY e.employee_name ASC");
    $stmt->execute(['tl' => $tl_id, 'company' => $_SESSION['company_id']]);
    $workload = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("TL reports: query failed: " . $e->getMessage());
    $error = "Some figures could not be calculated.";
}

$rate      = $totals['total'] > 0 ? (int) round($totals['completed'] / $totals['total'] * 100) : 0;
$statusMax = max($totals['not_started'], $totals['in_progress'], $totals['completed'], 1);

if (!function_exists('rpt_bar')) {
    function rpt_bar(int $value, int $max): int
    {
        return $max > 0 ? (int) round($value / $max * 100) : 0;
    }
}
?>

<div class="rpt">
    <div class="rpt-head">
        <h2><i class="fas fa-chart-line"></i> Reports</h2>
        <p>Progress across the tasks assigned to you, <strong><?php echo htmlspecialchars($_SESSION['employee_name']); ?></strong></p>
    </div>

    <?php if ($error): ?>
        <div class="rpt-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="rpt-cards">
        <div class="rpt-card"><span class="rpt-card-label">Tasks Led</span><span class="rpt-card-value"><?php echo $totals['total']; ?></span></div>
        <div class="rpt-card"><span class="rpt-card-label">Completed</span><span class="rpt-card-value rpt-good"><?php echo $totals['completed']; ?></span></div>
        <div class="rpt-card"><span class="rpt-card-label">Completion Rate</span><span class="rpt-card-value"><?php echo $rate; ?>%</span></div>
        <div class="rpt-card"><span class="rpt-card-label">Overdue</span><span class="rpt-card-value <?php echo $totals['overdue'] > 0 ? 'rpt-bad' : ''; ?>"><?php echo $totals['overdue']; ?></span></div>
    </div>

    <section class="rpt-panel">
        <h3>By Status</h3>
        <?php foreach (['Not Started' => ['not_started', '#64748b'],
                        'In Progress' => ['in_progress', '#2563eb'],
                        'Completed'   => ['completed',   '#10b981']] as $label => $spec): ?>
            <div class="rpt-row">
                <span class="rpt-row-label"><?php echo $label; ?></span>
                <span class="rpt-track"><span class="rpt-fill" style="width: <?php echo rpt_bar($totals[$spec[0]], $statusMax); ?>%; background: <?php echo $spec[1]; ?>;"></span></span>
                <span class="rpt-row-value"><?php echo $totals[$spec[0]]; ?></span>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="rpt-panel">
        <h3>Checkpoint Progress</h3>
        <?php if (empty($progress)): ?>
            <div class="rpt-empty"><i class="fas fa-clipboard-list"></i> No tasks have been delegated to you yet.</div>
        <?php else: ?>
            <div class="rpt-scroll">
                <table class="rpt-table">
                    <thead><tr><th>ID</th><th>Task</th><th>Due</th><th>Status</th><th>Checkpoints</th><th class="rpt-num">Progress</th></tr></thead>
                    <tbody>
                        <?php foreach ($progress as $row):
                            $cp   = (int) $row['checkpoints'];
                            $done = (int) $row['done'];
                            $pct  = $cp > 0 ? (int) round($done / $cp * 100) : 0; ?>
                            <tr>
                                <td>#<?php echo (int) $row['task_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo $row['due_date'] ? htmlspecialchars(date('M d, Y', strtotime($row['due_date']))) : '—'; ?></td>
                                <td><span class="rpt-pill rpt-pill-<?php echo strtolower(str_replace(' ', '', $row['task_status'])); ?>"><?php echo htmlspecialchars($row['task_status']); ?></span></td>
                                <td>
                                    <?php if ($cp === 0): ?>
                                        <span style="color:#94a3b8;">none defined</span>
                                    <?php else: ?>
                                        <span class="rpt-track" style="display:inline-block; width:120px; vertical-align:middle;">
                                            <span class="rpt-fill" style="width: <?php echo $pct; ?>%; background: #10b981;"></span>
                                        </span>
                                        <?php echo $done . ' / ' . $cp; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="rpt-num"><?php echo $cp > 0 ? $pct . '%' : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="rpt-panel">
        <h3>Member Workload</h3>
        <?php if (empty($workload)): ?>
            <div class="rpt-empty"><i class="fas fa-users"></i> No members are attached to your tasks yet.</div>
        <?php else: ?>
            <div class="rpt-scroll">
                <table class="rpt-table">
                    <thead><tr><th>Team Member</th><th class="rpt-num">Tasks Attached</th></tr></thead>
                    <tbody>
                        <?php foreach ($workload as $row):
                            $count = (int) $row['as_first'] + (int) $row['as_second'] + (int) $row['as_third']; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                <td class="rpt-num"><?php echo $count; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/report_styles.php'; ?>
