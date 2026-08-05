<?php
/**
 * Reporting for the Team Member role: the tasks this member is attached to and
 * how far through their checkpoints each one is.
 * Loaded as an AJAX fragment by tm_dashboard.php, so no <html>/<body> wrapper.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'employee'
    || $_SESSION['designation'] !== 'Team Member' || !isset($_SESSION['employee_id'])) {
    die("Access denied");
}

require_once __DIR__ . '/../includes/config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("TM reports: database error: " . $e->getMessage());
    echo '<div class="rpt-error">Reports are unavailable right now. Please try again later.</div>';
    return;
}

$tm_id  = (int) $_SESSION['employee_id'];
$totals = ['total' => 0, 'not_started' => 0, 'in_progress' => 0, 'completed' => 0, 'overdue' => 0];
$rows   = [];
$cpAll  = ['checkpoints' => 0, 'done' => 0];
$error  = null;

try {
    $stmt = $pdo->prepare("SELECT
            COUNT(*) AS total,
            SUM(task_status = 'Not Started') AS not_started,
            SUM(task_status = 'In Progress') AS in_progress,
            SUM(task_status = 'Completed')   AS completed,
            SUM(due_date IS NOT NULL AND due_date < CURDATE() AND task_status <> 'Completed') AS overdue
        FROM tasks WHERE ? IN (tm1, tm2, tm3)");
    $stmt->execute([$tm_id]);
    foreach ($stmt->fetch(PDO::FETCH_ASSOC) as $k => $v) {
        $totals[$k] = (int) $v;
    }

    $stmt = $pdo->prepare("SELECT t.task_id, t.title, t.due_date, t.task_priority, t.task_status,
                                  COALESCE(e.employee_name, 'Unassigned') AS lead_name,
                                  COUNT(c.checkpoint_id) AS checkpoints,
                                  SUM(c.status = 'Completed') AS done
                           FROM tasks t
                           LEFT JOIN employees e   ON t.team_lead_id = e.employee_id
                           LEFT JOIN checkpoints c ON c.task_id = t.task_id
                           WHERE ? IN (t.tm1, t.tm2, t.tm3)
                           GROUP BY t.task_id, t.title, t.due_date, t.task_priority, t.task_status, lead_name
                           ORDER BY t.due_date IS NULL, t.due_date ASC");
    $stmt->execute([$tm_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $cpAll['checkpoints'] += (int) $r['checkpoints'];
        $cpAll['done']        += (int) $r['done'];
    }
} catch (PDOException $e) {
    error_log("TM reports: query failed: " . $e->getMessage());
    $error = "Some figures could not be calculated.";
}

$rate      = $totals['total'] > 0 ? (int) round($totals['completed'] / $totals['total'] * 100) : 0;
$cpRate    = $cpAll['checkpoints'] > 0 ? (int) round($cpAll['done'] / $cpAll['checkpoints'] * 100) : 0;
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
        <p>Your workload and checkpoint progress, <strong><?php echo htmlspecialchars($_SESSION['employee_name']); ?></strong></p>
    </div>

    <?php if ($error): ?>
        <div class="rpt-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="rpt-cards">
        <div class="rpt-card"><span class="rpt-card-label">Assigned Tasks</span><span class="rpt-card-value"><?php echo $totals['total']; ?></span></div>
        <div class="rpt-card"><span class="rpt-card-label">Completed</span><span class="rpt-card-value rpt-good"><?php echo $totals['completed']; ?></span></div>
        <div class="rpt-card"><span class="rpt-card-label">Task Completion</span><span class="rpt-card-value"><?php echo $rate; ?>%</span></div>
        <div class="rpt-card"><span class="rpt-card-label">Checkpoints Done</span><span class="rpt-card-value"><?php echo $cpAll['done'] . ' / ' . $cpAll['checkpoints']; ?></span></div>
        <div class="rpt-card"><span class="rpt-card-label">Overdue</span><span class="rpt-card-value <?php echo $totals['overdue'] > 0 ? 'rpt-bad' : ''; ?>"><?php echo $totals['overdue']; ?></span></div>
    </div>

    <div class="rpt-grid">
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
            <h3>Overall Checkpoint Progress</h3>
            <div class="rpt-row">
                <span class="rpt-row-label">Completed</span>
                <span class="rpt-track"><span class="rpt-fill" style="width: <?php echo $cpRate; ?>%; background: #10b981;"></span></span>
                <span class="rpt-row-value"><?php echo $cpRate; ?>%</span>
            </div>
            <p style="color:#64748b; font-size:0.88rem; margin-top:8px;">
                <?php echo $cpAll['done']; ?> of <?php echo $cpAll['checkpoints']; ?> checkpoints ticked off across all your tasks.
            </p>
        </section>
    </div>

    <section class="rpt-panel">
        <h3>Your Tasks</h3>
        <?php if (empty($rows)): ?>
            <div class="rpt-empty"><i class="fas fa-clipboard-list"></i> You have no assigned tasks yet.</div>
        <?php else: ?>
            <div class="rpt-scroll">
                <table class="rpt-table">
                    <thead><tr><th>ID</th><th>Task</th><th>Team Lead</th><th>Due</th><th>Priority</th><th>Status</th><th>Checkpoints</th></tr></thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $cp   = (int) $row['checkpoints'];
                            $done = (int) $row['done'];
                            $pct  = $cp > 0 ? (int) round($done / $cp * 100) : 0; ?>
                            <tr>
                                <td>#<?php echo (int) $row['task_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo htmlspecialchars($row['lead_name']); ?></td>
                                <td><?php echo $row['due_date'] ? htmlspecialchars(date('M d, Y', strtotime($row['due_date']))) : '—'; ?></td>
                                <td><?php echo htmlspecialchars($row['task_priority']); ?></td>
                                <td><span class="rpt-pill rpt-pill-<?php echo strtolower(str_replace(' ', '', $row['task_status'])); ?>"><?php echo htmlspecialchars($row['task_status']); ?></span></td>
                                <td>
                                    <?php if ($cp === 0): ?>
                                        <span style="color:#94a3b8;">none defined</span>
                                    <?php else: ?>
                                        <span class="rpt-track" style="display:inline-block; width:110px; vertical-align:middle;">
                                            <span class="rpt-fill" style="width: <?php echo $pct; ?>%; background: #10b981;"></span>
                                        </span>
                                        <?php echo $done . ' / ' . $cp; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/report_styles.php'; ?>
