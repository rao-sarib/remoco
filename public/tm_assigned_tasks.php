<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';

// Check if user is logged in as a Team Member
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'employee' || $_SESSION['designation'] !== 'Team Member') {
    die("Access denied");
}

require_once __DIR__ . '/../includes/config.php';

// Create connection
$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get employee ID from session
$employee_id = $_SESSION['employee_id'];

// Fetch tasks assigned to this team member
$tasks = [];
try {
    $stmt = $pdo->prepare("SELECT task_id, title, due_date, task_priority, task_status 
                          FROM tasks 
                          WHERE tm1 = :employee_id 
                             OR tm2 = :employee_id 
                             OR tm3 = :employee_id
                          ORDER BY due_date ASC");
    $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching tasks: " . $e->getMessage());
    $error = "Error fetching tasks. Please try again later.";
}
?>

<div class="assigned-tasks-container">
    <div class="page-header">
        <h2><i class="fas fa-tasks"></i> My Assigned Tasks</h2>
        <p>Tasks assigned directly to you</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($tasks)): ?>
        <div class="no-tasks">
            <i class="fas fa-check-circle"></i>
            <h3>No Tasks Assigned</h3>
            <p>You don't have any tasks assigned to you at the moment.</p>
        </div>
    <?php else: ?>
        <div class="tasks-table-container">
            <table class="tasks-table">
                <thead>
                    <tr>
                        <th>Task ID</th>
                        <th>Title</th>
                        <th>Due Date</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <?php
                        // Format due date
                        $due_date = $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : 'Not set';
                        
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
                            <td><?php echo $due_date; ?></td>
                            <td><span class="priority-badge <?php echo $priority_class; ?>"><?php echo htmlspecialchars($task['task_priority']); ?></span></td>
                            <td><span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($task['task_status']); ?></span></td>
                            <td>
                                <a href="tm_updatetask.php?task_id=<?php echo $task['task_id']; ?>" class="btn-open">
                                    Open <i class="fas fa-arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
    .assigned-tasks-container {
        max-width: 1200px;
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
    
    .tasks-table-container {
        overflow-x: auto;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .tasks-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .tasks-table th {
        background-color: #f1f5f9;
        padding: 15px 20px;
        text-align: left;
        font-weight: 600;
        color: #1e293b;
        border-bottom: 2px solid #e2e8f0;
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
    
    .btn-open {
        background-color: #2563eb;
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    
    .btn-open:hover {
        background-color: #1e40af;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(37, 99, 235, 0.2);
    }
    
    @media (max-width: 768px) {
        .assigned-tasks-container {
            padding: 20px 15px;
        }
        
        .page-header h2 {
            font-size: 1.5rem;
        }
        
        .tasks-table th,
        .tasks-table td {
            padding: 12px 15px;
        }
        
        .btn-open {
            padding: 6px 12px;
            font-size: 0.9rem;
        }
    }
</style>