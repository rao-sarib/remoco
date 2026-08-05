<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';

// Check if user is logged in as a Team Member
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'employee' || 
    !isset($_SESSION['designation']) || $_SESSION['designation'] !== 'Team Member' ||
    !isset($_SESSION['employee_id'])) {
    die("Access denied");
}

require_once __DIR__ . '/../includes/config.php';

// Create connection
$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get team member ID from session
$team_member_id = $_SESSION['employee_id'];

// Fetch task statistics
$task_stats = [
    'in_progress' => 0,
    'pending' => 0,
    'completed' => 0
];

try {
    // Get tasks assigned to this team member
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN task_status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
            SUM(CASE WHEN task_status = 'Not Started' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN task_status = 'Completed' THEN 1 ELSE 0 END) AS completed
        FROM tasks
        WHERE tm1 = ? OR tm2 = ? OR tm3 = ?
    ");
    $stmt->execute([$team_member_id, $team_member_id, $team_member_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats) {
        $task_stats['in_progress'] = $stats['in_progress'];
        $task_stats['pending'] = $stats['pending'];
        $task_stats['completed'] = $stats['completed'];
    }

} catch (PDOException $e) {
    error_log("Error fetching task statistics: " . $e->getMessage());
    $error = "Error fetching task statistics. Please try again later.";
}
?>

<div class="tm-dashboard-container">
    <div class="welcome-section">
        <h1><i class="fas fa-home"></i> Dashboard</h1>
        <p>Welcome back, <?php echo htmlspecialchars($_SESSION['employee_name']); ?>! Here's your task overview.</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background-color: rgba(37, 99, 235, 0.1);">
                <i class="fas fa-sync-alt" style="color: #2563eb;"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $task_stats['in_progress']; ?></div>
                <div class="stat-label">In Progress Tasks</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background-color: rgba(245, 158, 11, 0.1);">
                <i class="fas fa-clock" style="color: #f59e0b;"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $task_stats['pending']; ?></div>
                <div class="stat-label">Pending Tasks</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background-color: rgba(16, 185, 129, 0.1);">
                <i class="fas fa-check-circle" style="color: #10b981;"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $task_stats['completed']; ?></div>
                <div class="stat-label">Completed Tasks</div>
            </div>
        </div>
    </div>

    <div class="recent-tasks">
        <h2><i class="fas fa-tasks"></i> Your Recent Tasks</h2>
        <div class="tasks-list">
            <?php
            try {
                $stmt = $pdo->prepare("
                    SELECT t.task_id, t.title, t.task_status, t.due_date, e.employee_name as team_lead
                    FROM tasks t
                    JOIN employees e ON t.team_lead_id = e.employee_id
                    WHERE t.tm1 = ? OR t.tm2 = ? OR t.tm3 = ?
                    ORDER BY t.created_date DESC 
                    LIMIT 5
                ");
                $stmt->execute([$team_member_id, $team_member_id, $team_member_id]);
                $recent_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($recent_tasks)): ?>
                    <div class="no-tasks">
                        <i class="fas fa-clipboard-list"></i>
                        <p>No recent tasks found</p>
                    </div>
                <?php else: 
                    foreach ($recent_tasks as $task): ?>
                        <div class="task-item">
                            <div class="task-info">
                                <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                <div class="task-meta">
                                    <?php if ($task['due_date']): 
                                        $due_date = new DateTime($task['due_date']);
                                        echo '<span><i class="far fa-calendar-alt"></i> Due: ' . $due_date->format('M d, Y') . '</span>';
                                    endif; ?>
                                    <span><i class="fas fa-user-tie"></i> TL: <?php echo htmlspecialchars($task['team_lead']); ?></span>
                                </div>
                            </div>
                            <div class="task-status">
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $task['task_status'])); ?>">
                                    <?php echo htmlspecialchars($task['task_status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach;
                endif;
            } catch (PDOException $e) {
                echo '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Error loading recent tasks</div>';
            }
            ?>
        </div>
    </div>
</div>

<style>
    :root {
        --primary: #2563eb;
        --primary-dark: #1e40af;
        --accent: #f59e0b;
        --light: #f8f9fa;
        --dark: #1e293b;
        --gray: #64748b;
        --success: #10b981;
        --danger: #dc2626;
    }
    
    .tm-dashboard-container {
        width: 100%;
        height: 100%;
        padding: 25px;
        display: flex;
        flex-direction: column;
        gap: 30px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .welcome-section {
        padding: 20px 0;
    }
    
    .welcome-section h1 {
        font-size: 1.8rem;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
    }
    
    .welcome-section p {
        color: var(--gray);
        font-size: 1.1rem;
    }
    
    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 10px;
        border-left: 4px solid;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .alert-error {
        background-color: #fee2e2;
        color: #dc2626;
        border-color: #dc2626;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr));
        gap: 20px;
    }
    
    .stat-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        padding: 25px;
        display: flex;
        align-items: center;
        gap: 20px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 25px rgba(0,0,0,0.12);
    }
    
    .stat-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .stat-icon i {
        font-size: 1.8rem;
    }
    
    .stat-content {
        flex: 1;
    }
    
    .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--dark);
        line-height: 1;
    }
    
    .stat-label {
        color: var(--gray);
        font-size: 1.1rem;
        margin-top: 5px;
    }
    
    .recent-tasks {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        padding: 30px;
        margin-top: 10px;
    }
    
    .recent-tasks h2 {
        font-size: 1.5rem;
        color: var(--dark);
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .tasks-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .task-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-radius: 8px;
        border-left: 4px solid var(--primary);
        background-color: #f8fafc;
        transition: all 0.3s ease;
    }
    
    .task-item:hover {
        background-color: #f1f5f9;
        transform: translateX(5px);
    }
    
    .task-info {
        flex: 1;
    }
    
    .task-title {
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 8px;
    }
    
    .task-meta {
        display: flex;
        gap: 15px;
        color: var(--gray);
        font-size: 0.9rem;
        flex-wrap: wrap;
    }
    
    .task-meta span {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .task-status {
        min-width: 120px;
        text-align: right;
    }
    
    .status-badge {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 500;
    }
    
    .status-not-started {
        background-color: #e2e8f0;
        color: #475569;
    }
    
    .status-in-progress {
        background-color: #dbeafe;
        color: #2563eb;
    }
    
    .status-completed {
        background-color: #dcfce7;
        color: #059669;
    }
    
    .no-tasks {
        text-align: center;
        padding: 40px;
        color: var(--gray);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
    }
    
    .no-tasks i {
        font-size: 3rem;
    }
    
    @media (max-width: 768px) {
        .tm-dashboard-container {
            padding: 15px;
        }
        
        .stat-card {
            flex-direction: column;
            text-align: center;
            padding: 20px;
        }
        
        .task-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
        
        .task-status {
            text-align: left;
            min-width: auto;
            width: 100%;
        }
        
        .recent-tasks {
            padding: 20px;
        }
    }
</style>