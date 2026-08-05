<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';

// Check if user is logged in as a Team Lead
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'employee' || 
    !isset($_SESSION['designation']) || $_SESSION['designation'] !== 'Team Lead' ||
    !isset($_SESSION['employee_id'])) {
    die("Access denied");
}

require_once __DIR__ . '/../includes/config.php';

// Create connection
$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get task ID from URL
$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;

// Fetch task details BEFORE form processing
$task = [];
$assigned_by_name = '';
if ($task_id) {
    try {
        // A Team Lead may only act on tasks delegated to them. This predicate is
        // the authorization boundary for the page: the POST handler below refuses
        // to run unless this fetch succeeded.
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE task_id = ? AND team_lead_id = ?");
        $stmt->execute([$task_id, $_SESSION['employee_id']]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get assigned by name
        if ($task && $task['assigned_by']) {
            $stmt = $pdo->prepare("SELECT employee_name FROM employees WHERE employee_id = ?");
            $stmt->execute([$task['assigned_by']]);
            $assigned_by = $stmt->fetch(PDO::FETCH_ASSOC);
            $assigned_by_name = $assigned_by['employee_name'] ?? 'Unknown';
        }
    } catch (PDOException $e) {
        error_log("Error fetching task: " . $e->getMessage());
        $error = "Error fetching task. Please try again later.";
    }
}

// Handle form submission.
// $task is only populated when the task belongs to this Team Lead, so requiring
// it here stops an assignment being written to someone else's task.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $task_id && $task) {
    csrf_require_page();

    // Get selected team members
    $members = [];
    foreach (['tm1', 'tm2', 'tm3'] as $slot) {
        $members[$slot] = !empty($_POST[$slot]) ? (int) $_POST[$slot] : null;
    }

    // Every submitted member must hold the Team Member designation within this
    // company, checked server-side rather than trusting the submitted values.
    $memberCheck = $pdo->prepare("SELECT COUNT(*) FROM employees
                                  WHERE employee_id = ? AND company_id = ? AND designation = 'Team Member'");
    foreach ($members as $slot => $employeeId) {
        if ($employeeId === null) {
            continue;
        }
        $memberCheck->execute([$employeeId, $_SESSION['company_id']]);
        if ((int) $memberCheck->fetchColumn() === 0) {
            $error = 'One of the selected team members is not available in your company.';
            $members[$slot] = null;
        }
    }

    [$tm1, $tm2, $tm3] = [$members['tm1'], $members['tm2'], $members['tm3']];

    // Get checkpoints
    $checkpoints = isset($_POST['checkpoint']) ? $_POST['checkpoint'] : [];

    if (!isset($error)) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Update task with team members and set status to "In Progress"
        $stmt = $pdo->prepare("UPDATE tasks 
                              SET tm1 = ?, tm2 = ?, tm3 = ?, task_status = 'In Progress' 
                              WHERE task_id = ?");
        $stmt->execute([$tm1, $tm2, $tm3, $task_id]);
        
        // Insert checkpoints
        if (!empty($checkpoints)) {
            $checkpointStmt = $pdo->prepare("INSERT INTO checkpoints (task_id, checkpoint, status) VALUES (?, ?, 'Pending')");
            foreach ($checkpoints as $checkpoint) {
                if (!empty(trim($checkpoint))) {
                    $checkpointStmt->execute([$task_id, trim($checkpoint)]);
                }
            }
        }
        
        // Generate unique Firebase room ID (20 characters)
        $firebase_room_id = bin2hex(random_bytes(10));
        
        // Insert into chats table - MODIFIED to include firebase_room_id
        $chatStmt = $pdo->prepare("INSERT INTO chats (chat_title, task_id, pm_id, tl_id, tm1_id, tm2_id, tm3_id, firebase_room_id) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $chatStmt->execute([
            $task['title'],  // Same as task title
            $task_id,        // Task ID from URL parameter
            $task['assigned_by'],  // PM who created the task
            $_SESSION['employee_id'],  // Current TL (logged-in user)
            $tm1,
            $tm2,
            $tm3,
            $firebase_room_id  // Firebase room ID
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        // Redirect to assigned tasks page
        header("Location: tl_dashboard.php?page=tl_assigned_tasks");
        exit;
    } catch (PDOException $e) {
        // Roll back on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error assigning task: " . $e->getMessage());
        $error = "Error assigning task. Please try again later.";
    }
    } // end: no member-validation error
}

// Fetch team members - ONLY those with designation "Team Member"
$team_members = [];
try {
    $stmt = $pdo->prepare("SELECT employee_id, employee_name 
                          FROM employees 
                          WHERE company_id = :company_id 
                          AND designation = 'Team Member'");
    $stmt->bindParam(':company_id', $_SESSION['company_id'], PDO::PARAM_INT);
    $stmt->execute();
    $team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching team members: " . $e->getMessage());
    $error = "Error fetching team members. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REMOCO - Assign Task</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS remains unchanged from previous version */
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
        
        .task-assign-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .header-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 30px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .back-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateX(-3px);
        }
        
        .header-title {
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .task-details-section {
            padding: 30px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .section-title {
            font-size: 1.4rem;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            font-size: 1.2rem;
        }
        
        .task-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr));
            gap: 20px;
        }
        
        .detail-card {
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 15px;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--dark);
            min-width: 150px;
        }
        
        .detail-value {
            color: var(--gray);
            flex: 1;
        }
        
        .priority-high {
            color: #dc2626;
            font-weight: 600;
        }
        
        .priority-medium {
            color: #d97706;
            font-weight: 600;
        }
        
        .priority-low {
            color: #059669;
            font-weight: 600;
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
        
        .team-assignment-section {
            padding: 30px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr));
            gap: 20px;
        }
        
        .team-member-select {
            margin-bottom: 20px;
            position: relative;
        }
        
        .team-member-select label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--dark);
        }
        
        .team-member-select select {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: white;
            font-size: 1rem;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232563eb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }
        
        .checkpoints-section {
            padding: 30px;
        }
        
        .checkpoint-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .checkpoint-list {
            margin-bottom: 30px;
        }
        
        .checkpoint-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 15px;
            background: #f8fafc;
            transition: all 0.3s;
        }
        
        .checkpoint-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .checkpoint-input {
            flex: 1;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
            transition: border 0.3s;
        }
        
        .checkpoint-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .remove-checkpoint {
            background: #fee2e2;
            border: none;
            color: #dc2626;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .remove-checkpoint:hover {
            background: #fecaca;
            transform: rotate(90deg);
        }
        
        .add-checkpoint-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 30px;
            transition: all 0.3s;
        }
        
        .add-checkpoint-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        
        .assign-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            display: block;
            margin: 0 auto;
            transition: all 0.3s;
        }
        
        .assign-btn:hover {
            background: #059669;
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #059669;
            border-color: #059669;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #dc2626;
            border-color: #dc2626;
        }
        
        .no-task {
            text-align: center;
            padding: 50px;
        }
        
        .no-task i {
            font-size: 5rem;
            color: #64748b;
            margin-bottom: 20px;
        }
        
        .no-task h3 {
            font-size: 1.8rem;
            color: #1e293b;
            margin-bottom: 15px;
        }
        
        .no-task p {
            color: #64748b;
            font-size: 1.1rem;
            max-width: 500px;
            margin: 0 auto 30px;
        }
        
        .back-link {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .back-link:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .char-count {
            font-size: 0.8rem;
            color: var(--gray);
            text-align: right;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .header-section {
                padding: 15px 20px;
            }
            
            .header-title {
                font-size: 1.5rem;
            }
            
            .task-details-section, 
            .team-assignment-section, 
            .checkpoints-section {
                padding: 20px;
            }
            
            .section-title {
                font-size: 1.2rem;
            }
            
            .detail-row {
                flex-direction: column;
                gap: 5px;
            }
            
            .detail-label {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="task-assign-container">
        <div class="header-section">
            <div class="header-left">
                <a href="tl_dashboard.php?page=tl_assigned_tasks" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="header-title">Assign Task</h1>
            </div>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($task)): ?>
            <div class="no-task">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Task Not Found</h3>
                <p>The task you are trying to access does not exist or you don't have permission to view it.</p>
                <a href="tl_dashboard.php?page=tl_assigned_tasks" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Assigned Tasks
                </a>
            </div>
        <?php else: ?>
            <form id="assign-form" method="POST">
                        <?php echo csrf_field(); ?>
                <div class="task-details-section">
                    <h2 class="section-title">
                        <i class="fas fa-info-circle"></i> Task Details
                    </h2>
                    
                    <div class="task-details-grid">
                        <div class="detail-card">
                            <div class="detail-row">
                                <div class="detail-label">Task ID:</div>
                                <div class="detail-value">#<?php echo htmlspecialchars($task['task_id']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Title:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($task['title']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Description:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($task['task_description'] ?: 'No description'); ?></div>
                            </div>
                        </div>
                        
                        <div class="detail-card">
                            <div class="detail-row">
                                <div class="detail-label">Due Date:</div>
                                <div class="detail-value">
                                    <?php 
                                    if ($task['due_date']) {
                                        $due_date = new DateTime($task['due_date']);
                                        echo $due_date->format('M d, Y');
                                    } else {
                                        echo 'Not set';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Priority:</div>
                                <div class="detail-value priority-<?php echo strtolower($task['task_priority']); ?>">
                                    <?php echo htmlspecialchars($task['task_priority']); ?>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Status:</div>
                                <div class="detail-value">
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $task['task_status'])); ?>">
                                        <?php echo htmlspecialchars($task['task_status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-card">
                            <div class="detail-row">
                                <div class="detail-label">Assigned By:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($assigned_by_name); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Created Date:</div>
                                <div class="detail-value">
                                    <?php 
                                    $created_date = new DateTime($task['created_date']);
                                    echo $created_date->format('M d, Y H:i');
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="team-assignment-section">
                    <h2 class="section-title">
                        <i class="fas fa-users"></i> Assign Team Members
                    </h2>
                    
                    <div class="team-grid">
                        <div class="team-member-select">
                            <label for="tm1">Select Team Member 1:</label>
                            <select name="tm1" id="tm1" required>
                                <option value="">-- Select Team Member --</option>
                                <?php foreach ($team_members as $member): ?>
                                    <option value="<?php echo $member['employee_id']; ?>" 
                                        <?php if ($task['tm1'] == $member['employee_id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($member['employee_name']); ?> (ID: <?php echo $member['employee_id']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="team-member-select">
                            <label for="tm2">Select Team Member 2:</label>
                            <select name="tm2" id="tm2">
                                <option value="">-- Select Team Member (Optional) --</option>
                                <?php foreach ($team_members as $member): ?>
                                    <option value="<?php echo $member['employee_id']; ?>" 
                                        <?php if ($task['tm2'] == $member['employee_id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($member['employee_name']); ?> (ID: <?php echo $member['employee_id']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="team-member-select">
                            <label for="tm3">Select Team Member 3:</label>
                            <select name="tm3" id="tm3">
                                <option value="">-- Select Team Member (Optional) --</option>
                                <?php foreach ($team_members as $member): ?>
                                    <option value="<?php echo $member['employee_id']; ?>" 
                                        <?php if ($task['tm3'] == $member['employee_id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($member['employee_name']); ?> (ID: <?php echo $member['employee_id']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="checkpoints-section">
                    <div class="checkpoint-header">
                        <h2 class="section-title">
                            <i class="fas fa-tasks"></i> Create Checkpoints
                        </h2>
                    </div>
                    
                    <div class="checkpoint-list" id="checkpoint-list">
                        <div class="checkpoint-item">
                            <input type="text" name="checkpoint[]" class="checkpoint-input" 
                                placeholder="Enter checkpoint description (max 30 characters)" maxlength="30" required
                                oninput="updateCharCount(this)">
                            <div class="char-count"><span id="char-count-0">0</span>/30</div>
                            <button type="button" class="remove-checkpoint" onclick="removeCheckpoint(this)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="button" class="add-checkpoint-btn" onclick="addCheckpoint()">
                        <i class="fas fa-plus"></i> Add Checkpoint
                    </button>
                    
                    <button type="submit" class="assign-btn">
                        <i class="fas fa-check-circle"></i> Assign Task
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
    
    <script>
        let checkpointCount = 1;
        
        function addCheckpoint() {
            const checkpointList = document.getElementById('checkpoint-list');
            const newCheckpoint = document.createElement('div');
            newCheckpoint.className = 'checkpoint-item';
            newCheckpoint.innerHTML = `
                <input type="text" name="checkpoint[]" class="checkpoint-input" 
                    placeholder="Enter checkpoint description (max 30 characters)" maxlength="30" required
                    oninput="updateCharCount(this)">
                <div class="char-count"><span id="char-count-${checkpointCount}">0</span>/30</div>
                <button type="button" class="remove-checkpoint" onclick="removeCheckpoint(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            checkpointList.appendChild(newCheckpoint);
            checkpointCount++;
        }
        
        function removeCheckpoint(button) {
            const checkpointItem = button.closest('.checkpoint-item');
            // Only remove if there's more than one checkpoint
            if (document.querySelectorAll('.checkpoint-item').length > 1) {
                checkpointItem.remove();
            } else {
                // Clear the input instead of removing
                const input = checkpointItem.querySelector('.checkpoint-input');
                input.value = '';
                updateCharCount(input);
            }
        }
        
        function updateCharCount(input) {
            const countSpan = input.nextElementSibling.querySelector('span');
            countSpan.textContent = input.value.length;
            
            // Change color when reaching limit
            if (input.value.length >= 30) {
                countSpan.style.color = '#dc2626';
                countSpan.style.fontWeight = 'bold';
            } else {
                countSpan.style.color = '';
                countSpan.style.fontWeight = '';
            }
        }
        
        // Initialize character count for the first checkpoint
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('.checkpoint-input');
            if (firstInput) {
                updateCharCount(firstInput);
            }
        });
        
        // Form validation
        document.getElementById('assign-form').addEventListener('submit', function(e) {
            let valid = true;
            
            // Check team member 1 is selected
            if (!document.getElementById('tm1').value) {
                valid = false;
                alert('Please select Team Member 1');
            }
            
            // Check at least one checkpoint has text
            const checkpoints = document.querySelectorAll('[name="checkpoint[]"]');
            let hasCheckpoint = false;
            checkpoints.forEach(input => {
                if (input.value.trim() !== '') {
                    hasCheckpoint = true;
                }
            });
            
            if (!hasCheckpoint) {
                valid = false;
                alert('Please add at least one checkpoint');
            }
            
            // Check checkpoint lengths
            checkpoints.forEach(input => {
                if (input.value.trim() !== '' && input.value.length > 30) {
                    valid = false;
                    alert('Checkpoints cannot exceed 30 characters');
                }
            });
            
            if (!valid) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>