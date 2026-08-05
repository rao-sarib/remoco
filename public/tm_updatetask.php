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

// Get task ID from URL
$task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;

// Fetch task details, together with the team lead and assigner names.
//
// The assignment predicate is the authorization boundary for this whole page:
// a Team Member may only open a task they are actually assigned to. Everything
// below (checkpoint updates, task completion) is gated behind the "Task not
// found" exit, so scoping this one query protects the entire request.
$task = [];
if ($task_id) {
    try {
        $stmt = $pdo->prepare("SELECT t.*,
                                      tl.employee_name AS team_lead_name,
                                      pm.employee_name AS assigned_by_name
                               FROM tasks t
                               LEFT JOIN employees tl ON t.team_lead_id = tl.employee_id
                               LEFT JOIN employees pm ON t.assigned_by = pm.employee_id
                               WHERE t.task_id = ?
                                 AND ? IN (t.tm1, t.tm2, t.tm3)");
        $stmt->execute([$task_id, $_SESSION['employee_id']]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching task: " . $e->getMessage());
        $error = "Error fetching task. Please try again later.";
    }
}

// If task not found - or the member is not assigned to it
if (!$task) {
    die("Task not found");
}

$team_lead_name = '';
if ($task['team_lead_id']) {
    $team_lead_name = $task['team_lead_name'] ?? 'Unknown';
}

$assigned_by_name = '';
if ($task['assigned_by']) {
    $assigned_by_name = $task['assigned_by_name'] ?? 'Unknown';
}

// Fetch checkpoints for this task
$checkpoints = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM checkpoints WHERE task_id = ?");
    $stmt->execute([$task_id]);
    $checkpoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching checkpoints: " . $e->getMessage());
    $error = "Error fetching checkpoints. Please try again later.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_page();

    // Update checkpoint statuses. Only checkpoints belonging to this task are
    // touched, and the task itself was already proven to be assigned to this
    // member by the scoped fetch above.
    $checkpointStmt = $pdo->prepare("UPDATE checkpoints SET status = ?
                                     WHERE checkpoint_id = ? AND task_id = ?");
    $completedCount = 0;
    foreach ($checkpoints as $checkpoint) {
        $checkpoint_id = $checkpoint['checkpoint_id'];
        $isTicked = isset($_POST['checkpoint_' . $checkpoint_id]);
        $status = $isTicked ? 'Completed' : 'Pending';
        if ($isTicked) {
            $completedCount++;
        }

        try {
            $checkpointStmt->execute([$status, $checkpoint_id, $task_id]);
        } catch (PDOException $e) {
            error_log("Error updating checkpoint: " . $e->getMessage());
            $error = "Error updating checkpoint. Please try again later.";
        }
    }

    // Derive the task status from the checkpoint set on every submission, in
    // both directions, so the task and its checkpoints never disagree.
    $total = count($checkpoints);
    if ($total > 0) {
        if ($completedCount === $total) {
            $newStatus = 'Completed';
        } elseif ($completedCount === 0) {
            $newStatus = 'Not Started';
        } else {
            $newStatus = 'In Progress';
        }

        try {
            if ($newStatus === 'Completed') {
                // Keep the original completion date if the task was already complete.
                $stmt = $pdo->prepare("UPDATE tasks
                    SET task_status = 'Completed',
                        completion_date = COALESCE(completion_date, CURDATE())
                    WHERE task_id = ?");
                $stmt->execute([$task_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE tasks
                    SET task_status = ?,
                        completion_date = NULL
                    WHERE task_id = ?");
                $stmt->execute([$newStatus, $task_id]);
            }

            // Refresh task data so the page reflects the new status.
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE task_id = ?");
            $stmt->execute([$task_id]);
            $refreshed = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($refreshed) {
                // Preserve the joined name columns the scoped fetch added.
                $task = array_merge($task, $refreshed);
            }
        } catch (PDOException $e) {
            error_log("Error updating task status: " . $e->getMessage());
            $error = "Error updating task status. Please try again later.";
        }
    }

    // Show success message
    if (!isset($error)) {
        $success = "Checkpoints updated successfully!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Task - REMOCO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --accent: #f59e0b;
            --light: #f8f9fa;
            --dark: #1e293b;
            --gray: #64748b;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f1f5f9;
            padding: 20px;
        }
        
        .task-update-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header-section {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-content {
            flex: 1;
        }
        
        .header-section h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-section h1 i {
            background: rgba(255,255,255,0.2);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .task-id {
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: inline-block;
        }
        
        .back-btn {
            background: white;
            color: var(--primary);
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .back-btn:hover {
            background: #e0e7ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .task-details-section {
            padding: 30px;
        }
        
        .section-title {
            font-size: 1.4rem;
            color: var(--primary);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .task-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }
        
        .detail-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            background: #f8fafc;
        }
        
        .detail-group {
            margin-bottom: 15px;
        }
        
        .detail-label {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--dark);
            word-break: break-word;
        }
        
        .priority-high {
            background-color: #fee2e2;
            color: #dc2626;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 500;
        }
        
        .priority-medium {
            background-color: #fef3c7;
            color: #d97706;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 500;
        }
        
        .priority-low {
            background-color: #dcfce7;
            color: #16a34a;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 500;
        }
        
        .status-not-started {
            background-color: #e5e7eb;
            color: #4b5563;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 500;
        }
        
        .status-in-progress {
            background-color: #dbeafe;
            color: #2563eb;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 500;
        }
        
        .status-completed {
            background-color: #dcfce7;
            color: #16a34a;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 500;
        }
        
        .checkpoints-section {
            padding: 0 30px 30px;
        }
        
        .checkpoints-container {
            background: #f8fafc;
            border-radius: 10px;
            padding: 25px;
            border: 1px solid #e2e8f0;
        }
        
        .checkpoint-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .checkpoint-item:last-child {
            border-bottom: none;
        }
        
        .checkpoint-checkbox {
            margin-right: 15px;
        }
        
        .checkpoint-label {
            font-size: 1.1rem;
            color: var(--dark);
            flex: 1;
        }
        
        .checkpoint-status {
            font-size: 0.9rem;
            font-weight: 500;
            padding: 4px 12px;
            border-radius: 20px;
        }
        
        .status-completed-check {
            background-color: #dcfce7;
            color: #16a34a;
        }
        
        .status-pending {
            background-color: #fee2e2;
            color: #dc2626;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            padding: 30px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
        
        .update-btn {
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 14px 35px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .update-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(37, 99, 235, 0.3);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 30px 0;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border-left: 4px solid #16a34a;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
            border-left: 4px solid #dc2626;
        }
        
        @media (max-width: 768px) {
            .header-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            
            .back-btn {
                align-self: flex-end;
            }
            
            .task-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="task-update-container">
        <!-- Header with back button -->
        <div class="header-section">
            <div class="header-content">
                <h1>
                    <i class="fas fa-tasks"></i>
                    Update Task
                    <span class="task-id">Task #<?php echo htmlspecialchars($task_id); ?></span>
                </h1>
                <p>Update task progress by completing checkpoints</p>
            </div>
            <a href="tm_dashboard.php?page=tm_assigned_tasks" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Assigned Tasks
            </a>
        </div>
        
        <!-- Display messages if any -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
                        <?php echo csrf_field(); ?>
            <!-- Task Details Section -->
            <div class="task-details-section">
                <h2 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Task Information
                </h2>
                
                <div class="task-grid">
                    <div class="detail-card">
                        <div class="detail-group">
                            <div class="detail-label">Task ID</div>
                            <div class="detail-value"><?php echo htmlspecialchars($task['task_id']); ?></div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">Title</div>
                            <div class="detail-value"><?php echo htmlspecialchars($task['title']); ?></div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">Description</div>
                            <div class="detail-value"><?php echo htmlspecialchars($task['task_description'] ?: 'No description provided'); ?></div>
                        </div>
                    </div>
                    
                    <div class="detail-card">
                        <div class="detail-group">
                            <div class="detail-label">Due Date</div>
                            <div class="detail-value">
                                <?php 
                                if ($task['due_date'] && $task['due_date'] != '0000-00-00') {
                                    echo date('M d, Y', strtotime($task['due_date'])); 
                                } else {
                                    echo 'Not set';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">Priority</div>
                            <div class="detail-value">
                                <?php 
                                $priority_class = strtolower($task['task_priority']);
                                echo '<span class="priority-' . $priority_class . '">' . 
                                     htmlspecialchars($task['task_priority']) . 
                                     '</span>'; 
                                ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">Status</div>
                            <div class="detail-value">
                                <?php 
                                $status_class = strtolower(str_replace(' ', '-', $task['task_status']));
                                echo '<span class="status-' . $status_class . '">' . 
                                     htmlspecialchars($task['task_status']) . 
                                     '</span>'; 
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-card">
                        <div class="detail-group">
                            <div class="detail-label">Team Lead</div>
                            <div class="detail-value"><?php echo htmlspecialchars($team_lead_name); ?></div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">Assigned By</div>
                            <div class="detail-value"><?php echo htmlspecialchars($assigned_by_name); ?></div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">Created Date</div>
                            <div class="detail-value">
                                <?php 
                                if ($task['created_date'] && $task['created_date'] != '0000-00-00 00:00:00') {
                                    echo date('M d, Y', strtotime($task['created_date'])); 
                                } else {
                                    echo 'Not set';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">Completion Date</div>
                            <div class="detail-value">
                                <?php 
                                if ($task['completion_date'] && $task['completion_date'] != '0000-00-00') {
                                    echo date('M d, Y', strtotime($task['completion_date'])); 
                                } else {
                                    echo 'Not completed';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Checkpoints Section -->
            <div class="checkpoints-section">
                <h2 class="section-title">
                    <i class="fas fa-list-check"></i>
                    Task Checkpoints
                </h2>
                
                <div class="checkpoints-container">
                    <?php if (empty($checkpoints)): ?>
                        <div class="no-checkpoints" style="text-align: center; padding: 30px; color: var(--gray);">
                            <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 15px;"></i>
                            <h3>No Checkpoints Defined</h3>
                            <p>This task doesn't have any checkpoints yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($checkpoints as $checkpoint): ?>
                            <div class="checkpoint-item">
                                <div class="checkpoint-checkbox">
                                    <input type="checkbox" 
                                           id="checkpoint_<?php echo $checkpoint['checkpoint_id']; ?>" 
                                           name="checkpoint_<?php echo $checkpoint['checkpoint_id']; ?>"
                                           <?php echo ($checkpoint['status'] === 'Completed') ? 'checked' : ''; ?>
                                           style="transform: scale(1.5);">
                                </div>
                                <label for="checkpoint_<?php echo $checkpoint['checkpoint_id']; ?>" 
                                       class="checkpoint-label">
                                    <?php echo htmlspecialchars($checkpoint['checkpoint']); ?>
                                </label>
                                <div class="checkpoint-status status-<?php echo strtolower($checkpoint['status'] === 'Completed' ? 'completed-check' : 'pending'); ?>">
                                    <?php echo htmlspecialchars($checkpoint['status']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="update-btn">
                    <i class="fas fa-save"></i>
                    Update Checkpoints
                </button>
            </div>
        </form>
    </div>
</body>
</html>