<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';

// Check if company is logged in
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'company') {
    header('Location: login.php');
    exit;
}

$company_id = $_SESSION['company_id'];

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/pagination.php';

// Create connection
$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// The tenancy predicate is shared between the count and the page query.
$tenancyJoins = "FROM tasks t
        LEFT JOIN employees tl ON t.team_lead_id = tl.employee_id
        LEFT JOIN employees pm ON t.assigned_by = pm.employee_id
        LEFT JOIN employees tm1 ON t.tm1 = tm1.employee_id
        LEFT JOIN employees tm2 ON t.tm2 = tm2.employee_id
        LEFT JOIN employees tm3 ON t.tm3 = tm3.employee_id
        WHERE :company_id IN (pm.company_id, tl.company_id,
                              tm1.company_id, tm2.company_id, tm3.company_id)";

$totalTasks = 0;
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) $tenancyJoins");
    $countStmt->execute(['company_id' => $company_id]);
    $totalTasks = (int) $countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error counting tasks: " . $e->getMessage());
}
$page = paginate($totalTasks, 25);

// Fetch tasks data
$tasks = [];
try {
    // The tasks table has no company_id of its own, so tenancy is resolved
    // through the participating employees: a task belongs to this company when
    // any participant does. A row with no participants at all is excluded.
    $stmt = $pdo->prepare("
        SELECT t.*,
               tl.employee_name AS team_lead_name,
               pm.employee_name AS assigned_by_name,
               tm1.employee_name AS tm1_name,
               tm2.employee_name AS tm2_name,
               tm3.employee_name AS tm3_name
        FROM tasks t
        LEFT JOIN employees tl ON t.team_lead_id = tl.employee_id
        LEFT JOIN employees pm ON t.assigned_by = pm.employee_id
        LEFT JOIN employees tm1 ON t.tm1 = tm1.employee_id
        LEFT JOIN employees tm2 ON t.tm2 = tm2.employee_id
        LEFT JOIN employees tm3 ON t.tm3 = tm3.employee_id
        WHERE :company_id IN (pm.company_id, tl.company_id,
                              tm1.company_id, tm2.company_id, tm3.company_id)
        ORDER BY t.created_date DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':company_id', $company_id);
    $stmt->bindValue(':limit', $page['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $page['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching tasks: " . $e->getMessage());
    $error = "Unable to load tasks. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REMOCO - Tasks Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --accent: #f59e0b;
            --light: #f8f9fa;
            --dark: #1e293b;
            --gray: #64748b;
            --danger: #dc2626;
            --success: #10b981;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .tasks-container {
            width: 100%;
            height: 100%;
            padding: 20px;
            background: #f1f5f9;
            display: flex;
            flex-direction: column;
        }
        
        .tasks-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }
        
        .tasks-header h1 {
            font-size: 1.8rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .tasks-header h1 i {
            color: var(--primary);
        }
        
        .search-container {
            position: relative;
            width: 300px;
        }
        
        .search-container input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .search-container input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .search-container i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        .tasks-table-container {
            flex: 1;
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 20px;
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
        
        .task-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .task-desc {
            color: var(--gray);
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .no-tasks {
            text-align: center;
            padding: 50px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }
        
        .no-tasks i {
            font-size: 4rem;
            color: #64748b;
            margin-bottom: 20px;
        }
        
        .no-tasks h3 {
            font-size: 1.6rem;
            color: #1e293b;
            margin-bottom: 15px;
        }
        
        .no-tasks p {
            color: #64748b;
            font-size: 1rem;
            max-width: 500px;
            margin: 0 auto 30px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #dc2626;
            border-color: #dc2626;
        }
        
        .team-members {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .member-badge {
            background-color: #dbeafe;
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        @media (max-width: 1200px) {
            .tasks-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            
            .search-container {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .tasks-container {
                padding: 10px;
            }
            
            .tasks-header {
                padding: 15px;
            }
            
            .tasks-header h1 {
                font-size: 1.5rem;
            }
            
            .tasks-table th, .tasks-table td {
                padding: 12px 10px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="tasks-container">
        <div class="tasks-header">
            <h1><i class="fas fa-tasks"></i> Tasks Management</h1>
            <div class="search-container">
                <i class="fas fa-search"></i>
                <input type="text" id="search-tasks" placeholder="Search tasks...">
            </div>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="tasks-table-container">
            <?php if (empty($tasks)): ?>
                <div class="no-tasks">
                    <i class="fas fa-tasks"></i>
                    <h3>No Tasks Found</h3>
                    <p>There are currently no tasks in the system.</p>
                </div>
            <?php else: ?>
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
                            <th>Team Members</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($task['task_id']); ?></td>
                                <td>
                                    <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                </td>
                                <td>
                                    <div class="task-desc">
                                        <?php 
                                        $desc = $task['task_description'];
                                        if (strlen($desc) > 80) {
                                            echo htmlspecialchars(substr($desc, 0, 80) . '...');
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
                                <td><?php echo $task['team_lead_name'] ?? 'Not assigned'; ?></td>
                                <td><?php echo $task['assigned_by_name'] ?? 'Unknown'; ?></td>
                                <td>
                                    <?php 
                                    $created_date = new DateTime($task['created_date']);
                                    echo $created_date->format('M d, Y');
                                    ?>
                                </td>
                                <td>
                                    <div class="team-members">
                                        <?php 
                                        $members = [];
                                        if (!empty($task['tm1_name'])) $members[] = $task['tm1_name'];
                                        if (!empty($task['tm2_name'])) $members[] = $task['tm2_name'];
                                        if (!empty($task['tm3_name'])) $members[] = $task['tm3_name'];
                                        
                                        if (empty($members)) {
                                            echo '<span>Not assigned</span>';
                                        } else {
                                            foreach ($members as $member) {
                                                echo '<span class="member-badge">' . htmlspecialchars($member) . '</span>';
                                            }
                                        }
                                        ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php echo pagination_controls($page, 'tasks.php'); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php echo pagination_assets(); ?>

    <script>
        // Simple search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search-tasks');
            const table = document.querySelector('.tasks-table');
            
            if (searchInput && table) {
                const rows = table.querySelectorAll('tbody tr');
                
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase().trim();
                    
                    rows.forEach(row => {
                        const cells = row.querySelectorAll('td');
                        let found = false;
                        
                        cells.forEach(cell => {
                            if (cell.textContent.toLowerCase().includes(searchTerm)) {
                                found = true;
                            }
                        });
                        
                        if (found) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>