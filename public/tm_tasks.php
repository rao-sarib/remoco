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

// Fetch tasks assigned to this team member, one page at a time.
require_once __DIR__ . '/../includes/pagination.php';

$totalTasks = 0;
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE ? IN (tm1, tm2, tm3)");
    $countStmt->execute([$team_member_id]);
    $totalTasks = (int) $countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error counting tasks: " . $e->getMessage());
}
$page = paginate($totalTasks, 25);

$tasks = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            t.*,
            tl.employee_name AS team_lead_name,
            pm.employee_name AS assigned_by_name
        FROM tasks t
        LEFT JOIN employees tl ON t.team_lead_id = tl.employee_id
        LEFT JOIN employees pm ON t.assigned_by = pm.employee_id
        WHERE :member IN (t.tm1, t.tm2, t.tm3)
        ORDER BY t.due_date ASC, t.created_date DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':member', $team_member_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $page['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $page['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching tasks: " . $e->getMessage());
    $error = "Error fetching tasks. Please try again later.";
}
?>

<div class="tm-tasks-container">
    <div class="header-section">
        <h1><i class="fas fa-tasks"></i> Your Tasks</h1>
        <p>All tasks assigned to you across projects</p>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if (empty($tasks)): ?>
        <div class="no-tasks">
            <i class="fas fa-clipboard-list"></i>
            <h3>No Tasks Assigned</h3>
            <p>You currently don't have any assigned tasks.</p>
        </div>
    <?php else: ?>
        <div class="tasks-table-container">
            <table class="tasks-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Due Date</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Team Lead</th>
                        <th>Assigned By</th>
                        <th>Created Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($task['task_id']); ?></td>
                            <td><?php echo htmlspecialchars($task['title']); ?></td>
                            <td>
                                <div class="task-desc">
                                    <?php 
                                    $desc = $task['task_description'];
                                    if (strlen($desc) > 60) {
                                        echo htmlspecialchars(substr($desc, 0, 60) . '...');
                                    } else {
                                        echo htmlspecialchars($desc);
                                    }
                                    ?>
                                </div>
                            </td>
                            <td>
                                <?php 
                                if ($task['due_date']) {
                                    $due_date = new DateTime($task['due_date']);
                                    echo $due_date->format('M d, Y');
                                } else {
                                    echo 'Not set';
                                }
                                ?>
                            </td>
                            <td class="priority-<?php echo strtolower($task['task_priority']); ?>">
                                <?php echo htmlspecialchars($task['task_priority']); ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $task['task_status'])); ?>">
                                    <?php echo htmlspecialchars($task['task_status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($task['team_lead_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($task['assigned_by_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php 
                                $created_date = new DateTime($task['created_date']);
                                echo $created_date->format('M d, Y');
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php echo pagination_controls($page, 'tm_tasks.php'); ?>
        </div>
    <?php endif; ?>
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
    
    .tm-tasks-container {
        width: 100%;
        height: 100%;
        padding: 25px;
        display: flex;
        flex-direction: column;
        gap: 25px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .header-section {
        margin-bottom: 10px;
    }
    
    .header-section h1 {
        font-size: 1.8rem;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 5px;
    }
    
    .header-section p {
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
    
    .no-tasks {
        text-align: center;
        padding: 50px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 20px;
    }
    
    .no-tasks i {
        font-size: 3.5rem;
        color: var(--gray);
    }
    
    .no-tasks h3 {
        font-size: 1.6rem;
        color: var(--dark);
    }
    
    .no-tasks p {
        color: var(--gray);
        font-size: 1.1rem;
        max-width: 500px;
        margin: 0 auto;
    }
    
    .tasks-table-container {
        flex: 1;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        padding: 25px;
        overflow: auto;
    }
    
    .tasks-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1000px;
    }
    
    .tasks-table th {
        background-color: var(--primary);
        color: white;
        text-align: left;
        padding: 15px;
        font-weight: 600;
        position: sticky;
        top: 0;
        border-bottom: 2px solid var(--primary-dark);
    }
    
    .tasks-table td {
        padding: 15px;
        border-bottom: 1px solid #e2e8f0;
        color: var(--dark);
        vertical-align: top;
    }
    
    .tasks-table tr:nth-child(even) {
        background-color: #f8fafc;
    }
    
    .tasks-table tr:hover {
        background-color: #e0f2fe;
    }
    
    .priority-high {
        color: var(--danger);
        font-weight: 600;
    }
    
    .priority-medium {
        color: #d97706;
        font-weight: 600;
    }
    
    .priority-low {
        color: var(--success);
        font-weight: 600;
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
    
    .task-desc {
        color: var(--gray);
        font-size: 0.95rem;
        line-height: 1.4;
    }
    
    @media (max-width: 1200px) {
        .tasks-table th, 
        .tasks-table td {
            padding: 12px 10px;
            font-size: 0.95rem;
        }
    }
    
    @media (max-width: 768px) {
        .tm-tasks-container {
            padding: 15px;
        }
        
        .header-section h1 {
            font-size: 1.5rem;
        }
        
        .tasks-table-container {
            padding: 15px;
        }
        
        .tasks-table th, 
        .tasks-table td {
            padding: 10px 8px;
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 5px 10px;
            font-size: 0.85rem;
        }
    }
</style>
<?php echo pagination_assets(); ?>
