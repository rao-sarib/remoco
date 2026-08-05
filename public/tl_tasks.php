<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';

// Check if user is logged in as a Team Lead
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'employee' || $_SESSION['designation'] !== 'Team Lead') {
    die("Access denied");
}

require_once __DIR__ . '/../includes/config.php';

// Create connection
$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get employee ID from session
$employee_id = $_SESSION['employee_id'];

// Fetch tasks assigned to this team lead, one page at a time.
require_once __DIR__ . '/../includes/pagination.php';

$totalTasks = 0;
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE team_lead_id = ?");
    $countStmt->execute([$employee_id]);
    $totalTasks = (int) $countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error counting tasks: " . $e->getMessage());
}
$page = paginate($totalTasks, 25);

$tasks = [];
try {
    $sql = "SELECT
                t.*, 
                a.employee_name AS assigned_by_name,
                m1.employee_name AS tm1_name,
                m2.employee_name AS tm2_name,
                m3.employee_name AS tm3_name
            FROM tasks t
            LEFT JOIN employees a ON t.assigned_by = a.employee_id
            LEFT JOIN employees m1 ON t.tm1 = m1.employee_id
            LEFT JOIN employees m2 ON t.tm2 = m2.employee_id
            LEFT JOIN employees m3 ON t.tm3 = m3.employee_id
            WHERE t.team_lead_id = :employee_id
            ORDER BY t.due_date ASC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $page['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $page['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching tasks: " . $e->getMessage());
    $error = "Error fetching tasks. Please try again later.";
}
?>

<div class="tasks-container">
    <div class="page-header">
        <h2><i class="fas fa-tasks"></i> All Tasks</h2>
        <p>Tasks assigned to you as Team Lead</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($tasks)): ?>
        <div class="no-tasks">
            <i class="fas fa-check-circle"></i>
            <h3>No Tasks Found</h3>
            <p>You don't have any tasks assigned to you at the moment.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tasks-table">
                <thead>
                    <tr>
                        <th>Task ID</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Due Date</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned By</th>
                        <th>Created Date</th>
                        <th>Completion Date</th>
                        <th>Team Member 1</th>
                        <th>Team Member 2</th>
                        <th>Team Member 3</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <?php
                        // Format dates
                        $due_date = $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : 'Not set';
                        $created_date = $task['created_date'] ? date('M d, Y H:i', strtotime($task['created_date'])) : '';
                        $completion_date = $task['completion_date'] ? date('M d, Y', strtotime($task['completion_date'])) : 'Not completed';
                        
                        // Priority styling
                        $priority_class = strtolower($task['task_priority']);
                        
                        // Status styling
                        $status_class = '';
                        switch ($task['task_status']) {
                            case 'Not Started': $status_class = 'status-not-started'; break;
                            case 'In Progress': $status_class = 'status-in-progress'; break;
                            case 'Completed': $status_class = 'status-completed'; break;
                        }
                        ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($task['task_id']); ?></td>
                            <td class="task-title"><?php echo htmlspecialchars($task['title']); ?></td>
                            <td><?php echo htmlspecialchars(substr($task['task_description'], 0, 50) . (strlen($task['task_description']) > 50 ? '...' : '')); ?></td>
                            <td><?php echo $due_date; ?></td>
                            <td><span class="priority-badge <?php echo $priority_class; ?>"><?php echo htmlspecialchars($task['task_priority']); ?></span></td>
                            <td><span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($task['task_status']); ?></span></td>
                            <td><?php echo htmlspecialchars($task['assigned_by_name'] ?: 'N/A'); ?></td>
                            <td><?php echo $created_date; ?></td>
                            <td><?php echo $completion_date; ?></td>
                            <td><?php echo htmlspecialchars($task['tm1_name'] ?: 'Not assigned'); ?></td>
                            <td><?php echo htmlspecialchars($task['tm2_name'] ?: 'Not assigned'); ?></td>
                            <td><?php echo htmlspecialchars($task['tm3_name'] ?: 'Not assigned'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php echo pagination_controls($page, 'tl_tasks.php'); ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .tasks-container {
        max-width: 1800px;
        margin: 0 auto;
        padding: 30px 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }
    
    .page-header {
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .page-header h2 {
        display: flex;
        align-items: center;
        gap: 15px;
        font-size: 1.8rem;
        color: #1e293b;
        margin-bottom: 10px;
    }
    
    .page-header p {
        color: #64748b;
        font-size: 1.1rem;
    }
    
    .alert {
        padding: 15px;
        background-color: #fee2e2;
        color: #b91c1c;
        border-radius: 8px;
        margin-bottom: 25px;
        border-left: 4px solid #b91c1c;
    }
    
    .no-tasks {
        text-align: center;
        padding: 50px 20px;
        background-color: #f8fafc;
        border-radius: 12px;
        border: 1px dashed #cbd5e1;
    }
    
    .no-tasks i {
        font-size: 5rem;
        color: #10b981;
        margin-bottom: 20px;
    }
    
    .no-tasks h3 {
        font-size: 1.8rem;
        color: #1e293b;
        margin-bottom: 15px;
    }
    
    .no-tasks p {
        color: #64748b;
        font-size: 1.1rem;
        max-width: 500px;
        margin: 0 auto;
    }
    
    .table-responsive {
        overflow-x: auto;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        max-width: 100%;
    }
    
    .tasks-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1200px;
    }
    
    .tasks-table th {
        background-color: #2563eb;
        color: white;
        padding: 15px 20px;
        text-align: left;
        font-weight: 600;
        position: sticky;
        top: 0;
    }
    
    .tasks-table td {
        padding: 15px 20px;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
    }
    
    .tasks-table tbody tr {
        transition: background-color 0.2s;
    }
    
    .tasks-table tbody tr:hover {
        background-color: #f8fafc;
    }
    
    .task-title {
        font-weight: 500;
        color: #1e293b;
    }
    
    .priority-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    .priority-badge.high {
        background-color: #fee2e2;
        color: #dc2626;
    }
    
    .priority-badge.medium {
        background-color: #fef3c7;
        color: #d97706;
    }
    
    .priority-badge.low {
        background-color: #dcfce7;
        color: #059669;
    }
    
    .status-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
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
    
    @media (max-width: 1200px) {
        .tasks-container {
            padding: 20px 15px;
        }
        
        .page-header h2 {
            font-size: 1.5rem;
        }
        
        .tasks-table th,
        .tasks-table td {
            padding: 12px 15px;
            font-size: 0.9rem;
        }
    }
</style>
<?php echo pagination_assets(); ?>
