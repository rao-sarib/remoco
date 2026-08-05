<?php
// pm_tasks.php
require_once __DIR__ . '/../includes/session_bootstrap.php';

// Check if user is logged in and is a Project Manager
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'employee' || $_SESSION['designation'] !== 'Project Manager') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/config.php';

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle delete action
    if (isset($_POST['delete_task']) && isset($_POST['task_id'])) {
        csrf_require_page();
        $task_id = $_POST['task_id'];
        try {
            // Ownership is enforced in the statement itself: a manager can only
            // delete a task they created.
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE task_id = ? AND assigned_by = ?");
            $stmt->execute([$task_id, $_SESSION['employee_id']]);
            $_SESSION['success'] = "Task deleted successfully!";
        } catch (PDOException $e) {
            error_log("Error deleting task: " . $e->getMessage());
            $_SESSION['error'] = "Error deleting task. Please try again later.";
        }
        // Redirect to dashboard tasks page
        header("Location: pm_dashboard.php?page=pm_tasks");
        exit;
    }

    // Handle update action - FIXED SYNTAX ERROR HERE
    if (isset($_POST['update_task']) && isset($_POST['task_id'])) {
        csrf_require_page();
        $task_id = $_POST['task_id'];
        $priority = $_POST['priority'];
        $status = $_POST['status'];

        try {
            // Ownership is enforced in the statement itself.
            $stmt = $pdo->prepare("UPDATE tasks SET task_priority = ?, task_status = ?
                                   WHERE task_id = ? AND assigned_by = ?");
            $stmt->execute([$priority, $status, $task_id, $_SESSION['employee_id']]);
            $_SESSION['success'] = "Task updated successfully!";
        } catch (PDOException $e) {
            error_log("Error updating task: " . $e->getMessage());
            $_SESSION['error'] = "Error updating task. Please try again later.";
        }
        // Redirect to dashboard tasks page
        header("Location: pm_dashboard.php?page=pm_tasks");
        exit;
    }
}

// Fetch this manager's tasks, one page at a time. Rendering every row produced a
// response approaching a megabyte once a few hundred tasks existed.
require_once __DIR__ . '/../includes/pagination.php';

$totalTasks = 0;
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_by = ?");
    $countStmt->execute([$_SESSION['employee_id']]);
    $totalTasks = (int) $countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error counting tasks: " . $e->getMessage());
}
$page = paginate($totalTasks, 25);

$tasks = [];
try {
    // Scoped to the tasks this manager created.
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE assigned_by = :pm
                           ORDER BY created_date DESC
                           LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':pm', $_SESSION['employee_id'], PDO::PARAM_INT);
    $stmt->bindValue(':limit', $page['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $page['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $tasks = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching tasks: " . $e->getMessage());
    $_SESSION['error'] = "Error fetching tasks. Please try again later.";
}

// Fetch employee names for IDs
$employeeNames = [];
try {
    // Scoped to this company.
    $stmt = $pdo->prepare("SELECT employee_id, employee_name FROM employees WHERE company_id = ?");
    $stmt->execute([$_SESSION['company_id']]);
    $employees = $stmt->fetchAll();
    foreach ($employees as $emp) {
        $employeeNames[$emp['employee_id']] = $emp['employee_name'];
    }
} catch (PDOException $e) {
    // If we can't get names, we'll just show IDs
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --accent-color: #36b9cc;
            --light-bg: #f8f9fc;
            --dark-bg: #2e4374;
        }
        
        .tasks-container {
            padding: 20px;
            background-color: var(--light-bg);
            height: 100%;
            max-width: 100%;
            overflow-x: hidden;
            box-sizing: border-box;
        }
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary-color);
            flex-wrap: wrap;
        }
        
        .page-title {
            color: var(--primary-color);
            font-weight: 700;
            margin: 0;
            margin-right: 15px;
        }
        
        .table-scroll-container {
            width: 100%;
            overflow-x: auto;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 20px;
        }
        
        .tasks-table-container {
            min-width: fit-content;
            width: 100%;
        }
        
        .tasks-table {
            min-width: 1200px;
            width: 100%;
            margin-bottom: 0;
        }
        
        .tasks-table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 700;
            padding: 15px 20px;
            position: sticky;
            left: 0;
        }
        
        .tasks-table td {
            padding: 12px 20px;
            vertical-align: middle;
        }
        
        /* Increased status column width */
        .status-column {
            min-width: 150px;
        }
        
        .priority-high {
            background-color: #f8d7da;
            color: #721c24;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 20px;
            text-align: center;
        }
        
        .priority-medium {
            background-color: #fff3cd;
            color: #856404;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 20px;
            text-align: center;
        }
        
        .priority-low {
            background-color: #d1ecf1;
            color: #0c5460;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 20px;
            text-align: center;
        }
        
        .status-not-started {
            background-color: #e2e3e5;
            color: #383d41;
            padding: 5px 10px;
            border-radius: 20px;
            text-align: center;
        }
        
        .status-in-progress {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 5px 10px;
            border-radius: 20px;
            text-align: center;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
            padding: 5px 10px;
            border-radius: 20px;
            text-align: center;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: nowrap;
        }
        
        .btn-update {
            background-color: var(--accent-color);
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            color: white;
            white-space: nowrap;
            cursor: pointer;
        }
        
        .btn-delete {
            background-color: #e74a3b;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            color: white;
            white-space: nowrap;
            cursor: pointer;
        }
        
        .btn-update:hover {
            background-color: #2c9faf;
        }
        
        .btn-delete:hover {
            background-color: #c5301e;
        }
        
        .form-select {
            border-radius: 5px;
            padding: 5px 10px;
            border: 1px solid #d1d3e2;
            width: 100%;
            min-width: 120px;
            cursor: pointer;
        }
        
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .message-container {
            margin-bottom: 20px;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px 15px;
            border-radius: 5极;
            display: flex;
            align-items: center;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px 15px;
            border-radius: 5px;
            display: flex;
            align-items: center;
        }
        
        .task-count-badge {
            background-color: var(--primary-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .description-tooltip {
            cursor: help;
            border-bottom: 1px dotted #999;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .header-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .task-count-badge {
                margin-top: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="tasks-container">
        <div class="header-section">
            <h1 class="page-title"><i class="fas fa-tasks me-2"></i>Tasks Management</h1>
            <div class="task-count-badge">
                <i class="fas fa-list me-1"></i> Total Tasks: <?php echo count($tasks); ?>
            </div>
        </div>
        
        <div class="message-container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['error']; ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="table-scroll-container">
            <div class="tasks-table-container">
                <table class="tasks-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Due Date</th>
                            <th>Priority</th>
                            <th>Team Lead</th>
                            <th class="status-column">Status</th> <!-- Added class for status column -->
                            <th>Assigned By</th>
                            <th>Created Date</th>
                            <th>Completion Date</th>
                            <th>Team Members</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tasks)): ?>
                            <tr>
                                <td colspan="12" class="text-center py-4">No tasks found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($task['task_id']); ?></td>
                                    <td><?php echo htmlspecialchars($task['title']); ?></td>
                                    <td>
                                        <div class="description-tooltip" title="<?php echo htmlspecialchars($task['task_description']); ?>">
                                            <?php 
                                            $desc = $task['task_description'];
                                            echo strlen($desc) > 30 ? htmlspecialchars(substr($desc, 0, 30)).'...' : htmlspecialchars($desc); 
                                            ?>
                                        </div>
                                    </td>
                                    <td><?php echo $task['due_date'] ? htmlspecialchars($task['due_date']) : 'N/A'; ?></td>
                                    <td>
                                        <?php 
                                        $priority = $task['task_priority'];
                                        $priorityClass = '';
                                        if ($priority == 'High') $priorityClass = 'priority-high';
                                        if ($priority == 'Medium') $priorityClass = 'priority-medium';
                                        if ($priority == 'Low') $priorityClass = 'priority-low';
                                        ?>
                                        <span class="<?php echo $priorityClass; ?>">
                                            <?php echo htmlspecialchars($priority); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $teamLeadId = $task['team_lead_id'];
                                        if (isset($employeeNames[$teamLeadId])) {
                                            echo htmlspecialchars($employeeNames[$teamLeadId] . " ($teamLeadId)");
                                        } else {
                                            echo htmlspecialchars($teamLeadId);
                                        }
                                        ?>
                                    </td>
                                    <td class="status-column"> <!-- Added class for status column -->
                                        <?php 
                                        $status = $task['task_status'];
                                        $statusClass = '';
                                        if ($status == 'Not Started') $statusClass = 'status-not-started';
                                        if ($status == 'In Progress') $statusClass = 'status-in-progress';
                                        if ($status == 'Completed') $statusClass = 'status-completed';
                                        ?>
                                        <span class="<?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $assignedById = $task['assigned_by'];
                                        if (isset($employeeNames[$assignedById])) {
                                            echo htmlspecialchars($employeeNames[$assignedById] . " ($assignedById)");
                                        } else {
                                            echo htmlspecialchars($assignedById);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($task['created_date']); ?></td>
                                    <td><?php echo $task['completion_date'] ? htmlspecialchars($task['completion_date']) : 'N/A'; ?></td>
                                    <td>
                                        <?php
                                        $tmIds = [$task['tm1'], $task['tm2'], $task['tm3']];
                                        $tmNames = [];
                                        foreach ($tmIds as $tmId) {
                                            if ($tmId && isset($employeeNames[$tmId])) {
                                                $tmNames[] = htmlspecialchars($employeeNames[$tmId] . " ($tmId)");
                                            } elseif ($tmId) {
                                                $tmNames[] = htmlspecialchars($tmId);
                                            }
                                        }
                                        echo implode('<br>', $tmNames);
                                        ?>
                                    </td>
                                    <td class="action-buttons">
                                        <!-- Self-contained update form -->
                                        <form method="POST" action="pm_dashboard.php?page=pm_tasks" class="d-inline">
                        <?php echo csrf_field(); ?>
                                            <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                            <div class="mb-2">
                                                <select name="priority" class="form-select form-select-sm">
                                                    <option value="High" <?php echo $task['task_priority'] == 'High' ? 'selected' : ''; ?>>High</option>
                                                    <option value="Medium" <?php echo $task['task_priority'] == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                                    <option value="Low" <?php echo $task['task_priority'] == 'Low' ? 'selected' : ''; ?>>Low</option>
                                                </select>
                                            </div>
                                            <div class="mb-2">
                                                <select name="status" class="form-select form-select-sm">
                                                    <option value="Not Started" <?php echo $task['task_status'] == 'Not Started' ? 'selected' : ''; ?>>Not Started</option>
                                                    <option value="In Progress" <?php echo $task['task_status'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                                    <option value="Completed" <?php echo $task['task_status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                </select>
                                            </div>
                                            <button type="submit" name="update_task" class="btn-update w-100">
                                                <i class="fas fa-save"></i> Update
                                            </button>
                                        </form>
                                        
                                        <!-- Delete form -->
                                        <form method="POST" action="pm_dashboard.php?page=pm_tasks" class="d-inline">
                        <?php echo csrf_field(); ?>
                                            <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                            <button type="submit" name="delete_task" class="btn-delete w-100" onclick="return confirm('Are you sure you want to delete this task?');">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo pagination_controls($page, 'pm_tasks.php'); ?>
        </div>
    </div>
    <?php echo pagination_assets(); ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add tooltip functionality
        $(document).ready(function() {
            // Bootstrap's JS is not bundled by the dashboard shell, and when this
            // panel is injected its own <script src> may not have finished loading.
            // Tooltips are decorative, so initialise them only if the plugin is present.
            if ($.fn.tooltip) {
                $('.description-tooltip').tooltip({
                    trigger: 'hover',
                    placement: 'top'
                });
            }

            // Prevent form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
            
            // Fix for form submission in dashboard context
            $('form').on('submit', function() {
                // Store the current scroll position
                sessionStorage.setItem('scrollPosition', window.scrollY);
            });
            
            // Restore scroll position after page reload
            const scrollPosition = sessionStorage.getItem('scrollPosition');
            if (scrollPosition) {
                window.scrollTo(0, parseInt(scrollPosition));
                sessionStorage.removeItem('scrollPosition');
            }
        });
    </script>
</body>
</html>