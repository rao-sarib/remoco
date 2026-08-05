<?php
/**
 * Reporting for the Project Manager role: the tasks this manager created.
 * Loaded as an AJAX fragment by pm_dashboard.php, so no <html>/<body> wrapper.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'employee'
    || $_SESSION['designation'] !== 'Project Manager' || !isset($_SESSION['employee_id'])) {
    die("Access denied");
}

require_once __DIR__ . '/../includes/config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("PM reports: database error: " . $e->getMessage());
    echo '<div class="rpt-error">Reports are unavailable right now. Please try again later.</div>';
    return;
}

$pm_id  = (int) $_SESSION['employee_id'];
$totals = ['total' => 0, 'not_started' => 0, 'in_progress' => 0, 'completed' => 0,
           'high' => 0, 'medium' => 0, 'low' => 0, 'overdue' => 0];
$byLead = [];
$recent = [];
$error  = null;

try {
    $stmt = $pdo->prepare("SELECT
            COUNT(*) AS total,
            SUM(task_status = 'Not Started') AS not_started,
            SUM(task_status = 'In Progress') AS in_progress,
            SUM(task_status = 'Completed')   AS completed,
            SUM(task_priority = 'High')      AS `high`,
            SUM(task_priority = 'Medium')    AS `medium`,
            SUM(task_priority = 'Low')       AS `low`,
            SUM(due_date IS NOT NULL AND due_date < CURDATE() AND task_status <> 'Completed') AS overdue
        FROM tasks WHERE assigned_by = ?");
    $stmt->execute([$pm_id]);
    foreach ($stmt->fetch(PDO::FETCH_ASSOC) as $k => $v) {
        $totals[$k] = (int) $v;
    }

    $stmt = $pdo->prepare("SELECT COALESCE(e.employee_name, 'Unassigned') AS lead_name,
                                  COUNT(*) AS total,
                                  SUM(t.task_status = 'Completed') AS completed,
                                  SUM(t.due_date IS NOT NULL AND t.due_date < CURDATE() AND t.task_status <> 'Completed') AS overdue
                           FROM tasks t
                           LEFT JOIN employees e ON t.team_lead_id = e.employee_id
                           WHERE t.assigned_by = ?
                           GROUP BY lead_name
                           ORDER BY total DESC, lead_name ASC");
    $stmt->execute([$pm_id]);
    $byLead = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT t.task_id, t.title, t.due_date, t.task_priority, t.task_status,
                                  COALESCE(e.employee_name, 'Unassigned') AS lead_name
                           FROM tasks t
                           LEFT JOIN employees e ON t.team_lead_id = e.employee_id
                           WHERE t.assigned_by = ?
                           ORDER BY t.created_date DESC
                           LIMIT 10");
    $stmt->execute([$pm_id]);
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("PM reports: query failed: " . $e->getMessage());
    $error = "Some figures could not be calculated.";
}

$rate = $totals['total'] > 0 ? (int) round($totals['completed'] / $totals['total'] * 100) : 0;
$statusMax = max($totals['not_started'], $totals['in_progress'], $totals['completed'], 1);
$prioMax   = max($totals['high'], $totals['medium'], $totals['low'], 1);

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
        <p>Delivery overview for tasks you created, <strong><?php echo htmlspecialchars($_SESSION['employee_name']); ?></strong></p>
    </div>

    <?php if ($error): ?>
        <div class="rpt-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="rpt-cards">
        <div class="rpt-card"><span class="rpt-card-label">Tasks Created</span><span class="rpt-card-value"><?php echo $totals['total']; ?></span></div>
        <div class="rpt-card"><span class="rpt-card-label">Completed</span><span class="rpt-card-value rpt-good"><?php echo $totals['completed']; ?></span></div>
        <div class="rpt-card"><span class="rpt-card-label">Completion Rate</span><span class="rpt-card-value"><?php echo $rate; ?>%</span></div>
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
            <h3>By Priority</h3>
            <?php foreach (['High'   => ['high',   '#dc2626'],
                            'Medium' => ['medium', '#f59e0b'],
                            'Low'    => ['low',    '#0ea5e9']] as $label => $spec): ?>
                <div class="rpt-row">
                    <span class="rpt-row-label"><?php echo $label; ?></span>
                    <span class="rpt-track"><span class="rpt-fill" style="width: <?php echo rpt_bar($totals[$spec[0]], $prioMax); ?>%; background: <?php echo $spec[1]; ?>;"></span></span>
                    <span class="rpt-row-value"><?php echo $totals[$spec[0]]; ?></span>
                </div>
            <?php endforeach; ?>
        </section>
    </div>

    <section class="rpt-panel">
        <h3>Distribution by Team Lead</h3>
        <?php if (empty($byLead)): ?>
            <div class="rpt-empty"><i class="fas fa-clipboard-list"></i> You have not created any tasks yet.</div>
        <?php else: ?>
            <div class="rpt-scroll">
                <table class="rpt-table">
                    <thead><tr><th>Team Lead</th><th class="rpt-num">Tasks</th><th class="rpt-num">Completed</th><th class="rpt-num">Overdue</th></tr></thead>
                    <tbody>
                        <?php foreach ($byLead as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['lead_name']); ?></td>
                                <td class="rpt-num"><?php echo (int) $row['total']; ?></td>
                                <td class="rpt-num"><?php echo (int) $row['completed']; ?></td>
                                <td class="rpt-num <?php echo (int) $row['overdue'] > 0 ? 'rpt-bad' : ''; ?>"><?php echo (int) $row['overdue']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="rpt-panel">
        <h3>Most Recent Tasks</h3>
        <?php if (empty($recent)): ?>
            <div class="rpt-empty"><i class="fas fa-clipboard-list"></i> Nothing to show yet.</div>
        <?php else: ?>
            <div class="rpt-scroll">
                <table class="rpt-table">
                    <thead><tr><th>ID</th><th>Title</th><th>Team Lead</th><th>Due</th><th>Priority</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent as $row): ?>
                            <tr>
                                <td>#<?php echo (int) $row['task_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo htmlspecialchars($row['lead_name']); ?></td>
                                <td><?php echo $row['due_date'] ? htmlspecialchars(date('M d, Y', strtotime($row['due_date']))) : '—'; ?></td>
                                <td><?php echo htmlspecialchars($row['task_priority']); ?></td>
                                <td><span class="rpt-pill rpt-pill-<?php echo strtolower(str_replace(' ', '', $row['task_status'])); ?>"><?php echo htmlspecialchars($row['task_status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/report_styles.php'; ?>
