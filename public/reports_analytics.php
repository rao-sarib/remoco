<?php
/**
 * Company-wide reporting for the Company/Admin role.
 *
 * Loaded as an AJAX fragment by admin_dashboard.php, so it deliberately emits no
 * <html>/<body> wrapper. Read-only: every figure is derived from the existing
 * schema, and the whole page is scoped to the signed-in company.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'company') {
    die("Access denied");
}

require_once __DIR__ . '/../includes/config.php';

$company_id = $_SESSION['company_id'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Reports: database error: " . $e->getMessage());
    echo '<div class="rpt-error">Reports are unavailable right now. Please try again later.</div>';
    return;
}

// Tasks belong to a company through the employees attached to them - the same
// predicate the company task list uses.
$scope = "(:company_id IN (pm.company_id, tl.company_id, m1.company_id, m2.company_id, m3.company_id))";
$joins = "FROM tasks t
          LEFT JOIN employees pm ON t.assigned_by  = pm.employee_id
          LEFT JOIN employees tl ON t.team_lead_id = tl.employee_id
          LEFT JOIN employees m1 ON t.tm1 = m1.employee_id
          LEFT JOIN employees m2 ON t.tm2 = m2.employee_id
          LEFT JOIN employees m3 ON t.tm3 = m3.employee_id";

$totals = ['total' => 0, 'not_started' => 0, 'in_progress' => 0, 'completed' => 0,
           'high' => 0, 'medium' => 0, 'low' => 0, 'overdue' => 0];
$byLead = [];
$staff  = ['total' => 0, 'pm' => 0, 'tl' => 0, 'tm' => 0];
$error  = null;

try {
    $stmt = $pdo->prepare("SELECT
            COUNT(*) AS total,
            SUM(t.task_status = 'Not Started') AS not_started,
            SUM(t.task_status = 'In Progress') AS in_progress,
            SUM(t.task_status = 'Completed')   AS completed,
            SUM(t.task_priority = 'High')      AS `high`,
            SUM(t.task_priority = 'Medium')    AS `medium`,
            SUM(t.task_priority = 'Low')       AS `low`,
            SUM(t.due_date IS NOT NULL AND t.due_date < CURDATE() AND t.task_status <> 'Completed') AS overdue
        $joins WHERE $scope");
    $stmt->execute(['company_id' => $company_id]);
    foreach ($stmt->fetch(PDO::FETCH_ASSOC) as $k => $v) {
        $totals[$k] = (int) $v;
    }

    $stmt = $pdo->prepare("SELECT
            COALESCE(tl.employee_name, 'Unassigned') AS lead_name,
            COUNT(*) AS total,
            SUM(t.task_status = 'Completed')   AS completed,
            SUM(t.task_status = 'In Progress') AS in_progress,
            SUM(t.due_date IS NOT NULL AND t.due_date < CURDATE() AND t.task_status <> 'Completed') AS overdue
        $joins WHERE $scope
        GROUP BY lead_name
        ORDER BY total DESC, lead_name ASC");
    $stmt->execute(['company_id' => $company_id]);
    $byLead = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT
            COUNT(*) AS total,
            SUM(designation = 'Project Manager') AS pm,
            SUM(designation = 'Team Lead')       AS tl,
            SUM(designation = 'Team Member')     AS tm
        FROM employees WHERE company_id = ?");
    $stmt->execute([$company_id]);
    foreach ($stmt->fetch(PDO::FETCH_ASSOC) as $k => $v) {
        $staff[$k] = (int) $v;
    }
} catch (PDOException $e) {
    error_log("Reports: query failed: " . $e->getMessage());
    $error = "Some figures could not be calculated.";
}

$completionRate = $totals['total'] > 0
    ? (int) round($totals['completed'] / $totals['total'] * 100)
    : 0;

// Width of a bar as a percentage of the largest value in its set.
if (!function_exists('rpt_bar')) {
    function rpt_bar(int $value, int $max): int
    {
        return $max > 0 ? (int) round($value / $max * 100) : 0;
    }
}
$statusMax = max($totals['not_started'], $totals['in_progress'], $totals['completed'], 1);
$prioMax   = max($totals['high'], $totals['medium'], $totals['low'], 1);
?>

<div class="rpt">
    <div class="rpt-head">
        <h2><i class="fas fa-chart-bar"></i> Reports &amp; Analytics</h2>
        <p>Company-wide delivery overview for <strong><?php echo htmlspecialchars($_SESSION['company_name'] ?? $company_id); ?></strong></p>
    </div>

    <?php if ($error): ?>
        <div class="rpt-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="rpt-cards">
        <div class="rpt-card">
            <span class="rpt-card-label">Total Tasks</span>
            <span class="rpt-card-value"><?php echo $totals['total']; ?></span>
        </div>
        <div class="rpt-card">
            <span class="rpt-card-label">Completed</span>
            <span class="rpt-card-value rpt-good"><?php echo $totals['completed']; ?></span>
        </div>
        <div class="rpt-card">
            <span class="rpt-card-label">Completion Rate</span>
            <span class="rpt-card-value"><?php echo $completionRate; ?>%</span>
        </div>
        <div class="rpt-card">
            <span class="rpt-card-label">Overdue</span>
            <span class="rpt-card-value <?php echo $totals['overdue'] > 0 ? 'rpt-bad' : ''; ?>"><?php echo $totals['overdue']; ?></span>
        </div>
        <div class="rpt-card">
            <span class="rpt-card-label">Workforce</span>
            <span class="rpt-card-value"><?php echo $staff['total']; ?></span>
        </div>
    </div>

    <div class="rpt-grid">
        <section class="rpt-panel">
            <h3>Tasks by Status</h3>
            <?php foreach ([
                'Not Started' => ['not_started', '#64748b'],
                'In Progress' => ['in_progress', '#2563eb'],
                'Completed'   => ['completed',   '#10b981'],
            ] as $label => $spec): ?>
                <div class="rpt-row">
                    <span class="rpt-row-label"><?php echo $label; ?></span>
                    <span class="rpt-track">
                        <span class="rpt-fill" style="width: <?php echo rpt_bar($totals[$spec[0]], $statusMax); ?>%; background: <?php echo $spec[1]; ?>;"></span>
                    </span>
                    <span class="rpt-row-value"><?php echo $totals[$spec[0]]; ?></span>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="rpt-panel">
            <h3>Tasks by Priority</h3>
            <?php foreach ([
                'High'   => ['high',   '#dc2626'],
                'Medium' => ['medium', '#f59e0b'],
                'Low'    => ['low',    '#0ea5e9'],
            ] as $label => $spec): ?>
                <div class="rpt-row">
                    <span class="rpt-row-label"><?php echo $label; ?></span>
                    <span class="rpt-track">
                        <span class="rpt-fill" style="width: <?php echo rpt_bar($totals[$spec[0]], $prioMax); ?>%; background: <?php echo $spec[1]; ?>;"></span>
                    </span>
                    <span class="rpt-row-value"><?php echo $totals[$spec[0]]; ?></span>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="rpt-panel">
            <h3>Workforce Composition</h3>
            <table class="rpt-table">
                <tbody>
                    <tr><td>Project Managers</td><td class="rpt-num"><?php echo $staff['pm']; ?></td></tr>
                    <tr><td>Team Leads</td><td class="rpt-num"><?php echo $staff['tl']; ?></td></tr>
                    <tr><td>Team Members</td><td class="rpt-num"><?php echo $staff['tm']; ?></td></tr>
                </tbody>
            </table>
        </section>
    </div>

    <section class="rpt-panel">
        <h3>Delivery by Team Lead</h3>
        <?php if (empty($byLead)): ?>
            <div class="rpt-empty"><i class="fas fa-clipboard-list"></i> No tasks recorded yet.</div>
        <?php else: ?>
            <div class="rpt-scroll">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th>Team Lead</th>
                            <th class="rpt-num">Tasks</th>
                            <th class="rpt-num">In Progress</th>
                            <th class="rpt-num">Completed</th>
                            <th class="rpt-num">Overdue</th>
                            <th class="rpt-num">Completion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byLead as $row):
                            $rate = (int) $row['total'] > 0
                                ? (int) round((int) $row['completed'] / (int) $row['total'] * 100)
                                : 0; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['lead_name']); ?></td>
                                <td class="rpt-num"><?php echo (int) $row['total']; ?></td>
                                <td class="rpt-num"><?php echo (int) $row['in_progress']; ?></td>
                                <td class="rpt-num"><?php echo (int) $row['completed']; ?></td>
                                <td class="rpt-num <?php echo (int) $row['overdue'] > 0 ? 'rpt-bad' : ''; ?>"><?php echo (int) $row['overdue']; ?></td>
                                <td class="rpt-num"><?php echo $rate; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/report_styles.php'; ?>
