<?php
/**
 * Alerts view for the Company/Admin role.
 *
 * Surfaces the work that needs attention: overdue tasks, tasks falling due
 * shortly, high-priority work that has not been started, and tasks with no team
 * members attached. Thresholds are chosen from the controls on the panel.
 *
 * Loaded as an AJAX fragment by admin_dashboard.php, so no <html>/<body> wrapper.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'company') {
    die("Access denied");
}

require_once __DIR__ . '/../includes/config.php';
// Supplies the .pg-link styling and the delegated click handler that reloads a
// panel in place, reused here for the horizon selector.
require_once __DIR__ . '/../includes/pagination.php';

$company_id = $_SESSION['company_id'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Alerts: database error: " . $e->getMessage());
    echo '<div class="alr-error">Alerts are unavailable right now. Please try again later.</div>';
    return;
}

// How many days ahead counts as "due soon". Clamped so the query stays sane.
$horizon = isset($_GET['horizon']) ? (int) $_GET['horizon'] : 7;
if ($horizon < 1)  { $horizon = 1; }
if ($horizon > 90) { $horizon = 90; }

$scope = "(:company_id IN (pm.company_id, tl.company_id, m1.company_id, m2.company_id, m3.company_id))";
$joins = "FROM tasks t
          LEFT JOIN employees pm ON t.assigned_by  = pm.employee_id
          LEFT JOIN employees tl ON t.team_lead_id = tl.employee_id
          LEFT JOIN employees m1 ON t.tm1 = m1.employee_id
          LEFT JOIN employees m2 ON t.tm2 = m2.employee_id
          LEFT JOIN employees m3 ON t.tm3 = m3.employee_id";

$select = "SELECT t.task_id, t.title, t.due_date, t.task_priority, t.task_status,
                  COALESCE(tl.employee_name, 'Unassigned') AS lead_name";

$groups = [];
$error  = null;

try {
    // Overdue
    $stmt = $pdo->prepare("$select $joins
        WHERE $scope AND t.due_date IS NOT NULL AND t.due_date < CURDATE()
              AND t.task_status <> 'Completed'
        ORDER BY t.due_date ASC");
    $stmt->execute(['company_id' => $company_id]);
    $groups[] = ['key' => 'overdue', 'label' => 'Overdue', 'tone' => 'danger',
                 'icon' => 'fa-circle-exclamation',
                 'hint' => 'Past the due date and not yet complete.',
                 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

    // Due soon
    $stmt = $pdo->prepare("$select $joins
        WHERE $scope AND t.due_date IS NOT NULL
              AND t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :horizon DAY)
              AND t.task_status <> 'Completed'
        ORDER BY t.due_date ASC");
    $stmt->execute(['company_id' => $company_id, 'horizon' => $horizon]);
    $groups[] = ['key' => 'due_soon', 'label' => "Due within $horizon day" . ($horizon === 1 ? '' : 's'),
                 'tone' => 'warn', 'icon' => 'fa-clock',
                 'hint' => 'Approaching the due date and not yet complete.',
                 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

    // High priority, not started
    $stmt = $pdo->prepare("$select $joins
        WHERE $scope AND t.task_priority = 'High' AND t.task_status = 'Not Started'
        ORDER BY t.due_date IS NULL, t.due_date ASC");
    $stmt->execute(['company_id' => $company_id]);
    $groups[] = ['key' => 'high_idle', 'label' => 'High priority, not started',
                 'tone' => 'warn', 'icon' => 'fa-fire',
                 'hint' => 'Flagged as high priority but no work has begun.',
                 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

    // No members assigned
    $stmt = $pdo->prepare("$select $joins
        WHERE $scope AND t.tm1 IS NULL AND t.tm2 IS NULL AND t.tm3 IS NULL
              AND t.task_status <> 'Completed'
        ORDER BY t.created_date DESC");
    $stmt->execute(['company_id' => $company_id]);
    $groups[] = ['key' => 'unstaffed', 'label' => 'No team members assigned',
                 'tone' => 'info', 'icon' => 'fa-user-slash',
                 'hint' => 'Delegated to a lead but nobody is working on it yet.',
                 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
} catch (PDOException $e) {
    error_log("Alerts: query failed: " . $e->getMessage());
    $error = "Alerts could not be calculated.";
}

$totalAlerts = 0;
foreach ($groups as $g) {
    $totalAlerts += count($g['rows']);
}
?>

<div class="alr">
    <div class="alr-head">
        <h2><i class="fas fa-bell"></i> Alerts</h2>
        <p>
            <?php if ($totalAlerts === 0): ?>
                Nothing needs attention right now.
            <?php else: ?>
                <strong><?php echo $totalAlerts; ?></strong> item<?php echo $totalAlerts === 1 ? '' : 's'; ?> need attention.
            <?php endif; ?>
        </p>
    </div>

    <?php if ($error): ?>
        <div class="alr-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php
    // Rendered as links rather than a form on purpose. This panel is injected into
    // the dashboard shell over AJAX, and a plain GET form would submit the whole
    // document - navigating away from the dashboard to a bare fragment. Anchors
    // carrying data-pg-url are picked up by the shared handler in pagination.php,
    // which reloads just this panel, and still navigate normally if the page is
    // opened on its own.
    ?>
    <div class="alr-controls">
        <span class="alr-controls-label">Warn when a task is due within</span>
        <?php foreach ([3, 7, 14, 30] as $d):
            $url = 'set_alerts.php?horizon=' . $d;
            if ($d === $horizon): ?>
                <span class="pg-link pg-current"><?php echo $d; ?> days</span>
            <?php else: ?>
                <a class="pg-link" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
                   data-pg-url="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $d; ?> days</a>
            <?php endif;
        endforeach; ?>
    </div>

    <?php foreach ($groups as $group): ?>
        <section class="alr-group alr-<?php echo $group['tone']; ?>">
            <header>
                <span class="alr-title">
                    <i class="fas <?php echo $group['icon']; ?>"></i>
                    <?php echo htmlspecialchars($group['label']); ?>
                </span>
                <span class="alr-count"><?php echo count($group['rows']); ?></span>
            </header>
            <p class="alr-hint"><?php echo htmlspecialchars($group['hint']); ?></p>

            <?php if (empty($group['rows'])): ?>
                <div class="alr-clear"><i class="fas fa-check"></i> Nothing here.</div>
            <?php else: ?>
                <div class="alr-scroll">
                    <table class="alr-table">
                        <thead>
                            <tr>
                                <th>ID</th><th>Task</th><th>Team Lead</th>
                                <th>Due</th><th>Priority</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($group['rows'] as $row): ?>
                                <tr>
                                    <td>#<?php echo (int) $row['task_id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td><?php echo htmlspecialchars($row['lead_name']); ?></td>
                                    <td><?php echo $row['due_date'] ? htmlspecialchars(date('M d, Y', strtotime($row['due_date']))) : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($row['task_priority']); ?></td>
                                    <td><?php echo htmlspecialchars($row['task_status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>

<style>
    .alr { padding: 25px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1e293b; }
    .alr-head h2 { font-size: 1.6rem; color: #2563eb; display: flex; align-items: center; gap: 12px; margin-bottom: 6px; }
    .alr-head p { color: #64748b; margin-bottom: 18px; }
    .alr-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626;
                 padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; }
    .alr-controls { display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
                    background: #fff; border-radius: 12px; padding: 14px 18px;
                    box-shadow: 0 4px 16px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .alr-controls-label { color: #475569; font-size: 0.92rem; }
    .alr-group { background: #fff; border-radius: 12px; padding: 18px 20px; margin-bottom: 16px;
                 box-shadow: 0 4px 16px rgba(0,0,0,0.06); border-left: 4px solid #94a3b8; }
    .alr-group.alr-danger { border-left-color: #dc2626; }
    .alr-group.alr-warn   { border-left-color: #f59e0b; }
    .alr-group.alr-info   { border-left-color: #0ea5e9; }
    .alr-group header { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .alr-title { display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 1.02rem; }
    .alr-count { background: #f1f5f9; border-radius: 999px; padding: 2px 12px; font-weight: 700; }
    .alr-danger .alr-count { background: #fee2e2; color: #991b1b; }
    .alr-warn   .alr-count { background: #fef3c7; color: #92400e; }
    .alr-info   .alr-count { background: #e0f2fe; color: #075985; }
    .alr-hint { color: #64748b; font-size: 0.88rem; margin: 6px 0 12px; }
    .alr-clear { color: #10b981; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
    .alr-scroll { width: 100%; overflow-x: auto; }
    .alr-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .alr-table th, .alr-table td { padding: 9px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; white-space: nowrap; }
    .alr-table th { color: #475569; font-weight: 600; }
</style>
<?php echo pagination_assets(); ?>
