<?php
// ================= DATABASE CONNECTION ================= //
require_once __DIR__ . '/../includes/session_bootstrap.php';
if (!isset($_SESSION['employee_id'])) {
    die("Access denied");
}

require_once __DIR__ . '/../includes/config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get PM's task statistics
    $pm_id = $_SESSION['employee_id'];
    
    $stats = [
        'total_tasks' => 0,
        'not_started' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'high_priority' => 0
    ];
    
    // All five figures come from the same set of rows, so they are counted in a
    // single pass rather than with one query per statistic.
    $stmt = $pdo->prepare("SELECT
                               COUNT(*) AS total_tasks,
                               SUM(task_status = 'Not Started') AS not_started,
                               SUM(task_status = 'In Progress') AS in_progress,
                               SUM(task_status = 'Completed')   AS completed,
                               -- HIGH_PRIORITY is a reserved SELECT modifier in
                               -- MySQL/MariaDB, so this alias must be quoted.
                               SUM(task_priority = 'High')      AS `high_priority`
                           FROM tasks
                           WHERE assigned_by = :pm_id");
    $stmt->execute(['pm_id' => $pm_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // SUM() returns NULL when no rows match; the counters must stay integers.
    $stats['total_tasks']   = (int) $row['total_tasks'];
    $stats['not_started']   = (int) $row['not_started'];
    $stats['in_progress']   = (int) $row['in_progress'];
    $stats['completed']     = (int) $row['completed'];
    $stats['high_priority'] = (int) $row['high_priority'];

} catch (PDOException $e) {
    // Log error but continue to show UI
    error_log("Database error: " . $e->getMessage());
    $stats = [
        'total_tasks' => 0,
        'not_started' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'high_priority' => 0
    ];
}

$employee_name = $_SESSION['employee_name'];
?>
<!-- ================= DASHBOARD COMPONENT ================= -->
<div class="pm-dashboard">
    <div class="dashboard-welcome">
        <h1 class="dashboard-title">Welcome, <?php echo htmlspecialchars($employee_name); ?></h1>
        <p class="dashboard-subtitle">Your task statistics at a glance</p>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="stat-number"><?php echo $stats['total_tasks']; ?></div>
            <div class="stat-label">Total Tasks</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-hourglass-start"></i>
            </div>
            <div class="stat-number"><?php echo $stats['not_started']; ?></div>
            <div class="stat-label">Not Started</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-spinner"></i>
            </div>
            <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
            <div class="stat-label">In Progress</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-number"><?php echo $stats['completed']; ?></div>
            <div class="stat-label">Completed</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-number"><?php echo $stats['high_priority']; ?></div>
            <div class="stat-label">High Priority</div>
        </div>
    </div>
</div>

<style>
    .pm-dashboard {
        padding: 20px;
    }
    
    .dashboard-welcome {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .dashboard-title {
        font-size: 2rem;
        color: #2563eb;
        margin-bottom: 10px;
        font-weight: 700;
    }
    
    .dashboard-subtitle {
        font-size: 1.1rem;
        color: #64748b;
        max-width: 600px;
        margin: 0 auto;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(200px, 100%), 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        text-align: center;
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }
    
    .stat-icon {
        font-size: 2.2rem;
        color: #2563eb;
        margin-bottom: 15px;
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 10px;
    }
    
    .stat-label {
        font-size: 1rem;
        color: #64748b;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        
        .dashboard-title {
            font-size: 1.6rem;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>