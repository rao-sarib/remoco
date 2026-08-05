<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';

// Check if user is logged in and is a Project Manager
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'employee' || $_SESSION['designation'] !== 'Project Manager') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/config.php';

// Create connection
$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$company_id = $_SESSION['company_id'];
$employee_name = $_SESSION['employee_name'];
$employee_email = $_SESSION['employee_email'];

// Get employee ID from database
$stmt = $pdo->prepare("SELECT employee_id FROM employees WHERE email = ? AND company_id = ?");
$stmt->execute([$employee_email, $company_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if ($employee) {
    $_SESSION['employee_id'] = $employee['employee_id'];
} else {
    // Handle case where employee record not found
    die("Employee record not found");
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
        // Redirect to an explicit path rather than echoing the request URI back
        // into a response header.
        header('Location: pm_dashboard.php?page=pm_tasks');
        exit;
    }

    // Handle update action
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
        // Redirect to an explicit path rather than echoing the request URI back
        // into a response header.
        header('Location: pm_dashboard.php?page=pm_tasks');
        exit;
    }
}

// Create tasks table if not exists
$createTableSQL = "CREATE TABLE IF NOT EXISTS tasks (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    task_description TEXT,
    due_date DATE,
    task_priority ENUM('High', 'Medium', 'Low') NOT NULL,
    team_lead_id INT,  
    task_status ENUM('Not Started', 'In Progress', 'Completed') DEFAULT 'Not Started',
    assigned_by INT,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completion_date DATE,
    tm1 INT NULL, 
    tm2 INT NULL,  
    tm3 INT NULL,  
    FOREIGN KEY (team_lead_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_by) REFERENCES employees(employee_id) ON DELETE SET NULL,
    FOREIGN KEY (tm1) REFERENCES employees(employee_id) ON DELETE SET NULL,
    FOREIGN KEY (tm2) REFERENCES employees(employee_id) ON DELETE SET NULL,
    FOREIGN KEY (tm3) REFERENCES employees(employee_id) ON DELETE SET NULL
) ENGINE=InnoDB";
$pdo->exec($createTableSQL);

// Process task creation form if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['page']) && $_GET['page'] === 'pm_createtasks') {
    csrf_require_page();

    $errors = [];
    $success = '';

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $priority = $_POST['priority'] ?? '';
    $team_lead_id = $_POST['team_lead_id'] ?? '';

    // Presence
    if ($title === '') $errors[] = 'Task title is required';
    if ($due_date === '') $errors[] = 'Due date is required';
    if ($priority === '') $errors[] = 'Priority is required';
    if ($team_lead_id === '') $errors[] = 'Team lead is required';

    // Shape — validated here rather than relying on the column definitions, so
    // the behaviour is the same regardless of the server's sql_mode.
    if ($title !== '' && mb_strlen($title) > 255) {
        $errors[] = 'Task title must be 255 characters or fewer';
    }
    if ($due_date !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $due_date);
        if (!$d || $d->format('Y-m-d') !== $due_date) {
            $errors[] = 'Due date must be a valid date in YYYY-MM-DD format';
        }
    }
    if ($priority !== '' && !in_array($priority, ['High', 'Medium', 'Low'], true)) {
        $errors[] = 'Priority must be High, Medium or Low';
    }
    if ($team_lead_id !== '' && !ctype_digit((string) $team_lead_id)) {
        $errors[] = 'Team lead selection is invalid';
    }

    // The chosen Team Lead must hold that designation within this company.
    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM employees
                              WHERE employee_id = ? AND company_id = ? AND designation = 'Team Lead'");
        $chk->execute([(int) $team_lead_id, $_SESSION['company_id']]);
        if ((int) $chk->fetchColumn() === 0) {
            $errors[] = 'Selected team lead is not available in your company';
        }
    }

    if (empty($errors)) {
        try {
            // completion_date stays NULL until the task actually completes.
            $stmt = $pdo->prepare("INSERT INTO tasks (
                title,
                task_description,
                due_date,
                task_priority,
                team_lead_id,
                task_status,
                assigned_by,
                completion_date,
                tm1,
                tm2,
                tm3
            ) VALUES (?, ?, ?, ?, ?, 'Not Started', ?, NULL, NULL, NULL, NULL)");
            
            // Execute query
            if ($stmt->execute([$title, $description, $due_date, $priority, $team_lead_id, $_SESSION['employee_id']])) {
                $success = "Task created successfully!";
            } else {
                $errors[] = "Error creating task: " . implode(" ", $stmt->errorInfo());
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $errors[] = "Database error. Please try again later.";
        }
    }
    
    // The create-task panel submits over AJAX and expects JSON. Answer in the
    // shape it asks for when the request came from XMLHttpRequest, and keep the
    // flash-and-redirect path for a plain form post, so both routes work.
    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => empty($errors),
            'message' => $success,
            'errors'  => array_values($errors),
        ]);
        exit;
    }

    // Store messages in session to display after redirect
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_success'] = $success;

    // Redirect back to the create tasks page
    header('Location: pm_dashboard.php?page=pm_createtasks');
    exit;
}

// Fetch team leads for dropdown
$team_leads = [];
if (isset($_GET['page']) && $_GET['page'] === 'pm_createtasks') {
    try {
        $stmt = $pdo->prepare("SELECT employee_id, employee_name 
                               FROM employees 
                               WHERE designation = 'Team Lead' 
                               AND company_id = :company_id");
        $stmt->bindParam(':company_id', $_SESSION['company_id'], PDO::PARAM_INT);
        $stmt->execute();
        $team_leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errors = $_SESSION['form_errors'] ?? [];
        error_log("Error fetching team leads: " . $e->getMessage());
        $errors[] = "Error fetching team leads. Please try again later.";
        $_SESSION['form_errors'] = $errors;
    }
}

// The flash messages set above are deliberately LEFT in the session here.
//
// This shell has no markup to display them; the pm_createtasks panel does, and
// it is fetched over AJAX a moment after this page renders. Reading and clearing
// them here consumed them before the panel could ever show them, which is why
// task creation appeared to give no feedback at all - neither the validation
// errors nor the success confirmation. The panel now reads and clears them.
$errors = [];
$success = '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REMOCO - Project Manager Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Firebase SDK -->
<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js"></script>
<script>
  // Initialize Firebase
  const firebaseConfig = {
    apiKey: <?= json_encode(FIREBASE_API_KEY, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>,
    authDomain: <?= json_encode(FIREBASE_AUTH_DOMAIN, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>,
    databaseURL: <?= json_encode(FIREBASE_DATABASE_URL, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>,
    projectId: <?= json_encode(FIREBASE_PROJECT_ID, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>,
    storageBucket: <?= json_encode(FIREBASE_STORAGE_BUCKET, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>,
    messagingSenderId: <?= json_encode(FIREBASE_MESSAGING_SENDER_ID, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>,
    appId: <?= json_encode(FIREBASE_APP_ID, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>
  };
  
  // Initialize Firebase only once
  if (!firebase.apps.length) {
    firebase.initializeApp(firebaseConfig);
  }
</script>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --accent: #f59e0b;
            --light: #f8f9fa;
            --dark: #1e293b;
            --gray: #64748b;
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
            --header-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f1f5f9;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            height: 100vh;
            position: fixed;
            transition: all 0.3s ease;
            z-index: 100;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: var(--header-height);
            padding: 0 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .collapsed .sidebar-header {
            padding: 0;
            justify-content: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
        }

        .collapsed .logo {
            display: none;
        }

        .logo-icon {
            font-size: 1.8rem;
            min-width: 36px;
            display: flex;
            justify-content: center;
        }

        .logo-text {
            font-size: 1.3rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .toggle-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            transition: all 0.3s ease;
        }

        .collapsed .toggle-btn {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            margin: 0;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
            transition: padding 0.3s ease;
        }

        .collapsed .sidebar-menu {
            padding-top: var(--header-height);
        }

        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .menu-item:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }

        .menu-item.active {
            background-color: rgba(255, 255, 255, 0.25);
            border-left: 4px solid var(--accent);
        }

        .menu-item i {
            font-size: 1.2rem;
            min-width: 24px;
            text-align: center;
        }

        .menu-item span {
            font-size: 1rem;
            font-weight: 500;
            transition: opacity 0.3s ease;
        }

        .collapsed .menu-item span {
            opacity: 0;
            width: 0;
            overflow: hidden;
            position: absolute;
        }

        .collapsed .menu-item {
            justify-content: center;
            padding: 12px 5px;
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-width: 0;
            max-width: calc(100% - var(--sidebar-width));
            transition: margin-left 0.3s ease;
        }

        .main-content.collapsed {
            margin-left: var(--sidebar-collapsed-width);
            max-width: calc(100% - var(--sidebar-collapsed-width));
        }

        .topbar {
            height: var(--header-height);
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            position: sticky;
            top: 0;
            z-index: 99;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-large-icon {
            font-size: 2rem;
            color: var(--primary);
        }

        .logo-large-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f1f5f9;
            padding: 5px 15px;
            border-radius: 20px;
        }

        .user-icon {
            background: var(--primary);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--dark);
        }

        .logout-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .logout-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .content-area {
            padding: 0 30px 50px;
            min-height: calc(100vh - var(--header-height));
        }

        #content-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        @media (max-width: 900px) {
            .sidebar {
                width: var(--sidebar-collapsed-width);
            }
            .sidebar:not(.collapsed) {
                width: var(--sidebar-width);
            }
            .main-content {
                margin-left: var(--sidebar-collapsed-width);
                max-width: calc(100% - var(--sidebar-collapsed-width));
            }
            .main-content.collapsed {
                margin-left: 0;
            }
            .logo-large-text {
                font-size: 1.2rem;
            }
            .user-info {
                display: none;
            }
            .logout-btn span {
                display: none;
            }
        }
    </style>

    
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="logo">
                <i class="fas fa-users-cog logo-icon"></i>
                <span class="logo-text">REMOCO</span>
            </a>
            <button class="toggle-btn" id="toggleSidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <ul class="sidebar-menu">
            <li class="menu-item active" id="menu-home" data-page="pm_home">
                <i class="fas fa-home"></i>
                <span>HOME</span>
            </li>
            <li class="menu-item" id="menu-chats" data-page="pm_chats">
                <i class="fas fa-comments"></i>
                <span>CHATS</span>
            </li>
            <li class="menu-item" id="menu-create_tasks" data-page="pm_createtasks">
                <i class="fas fa-plus-circle"></i>
                <span>CREATE TASKS</span>
            </li>
            <li class="menu-item" id="menu-tasks" data-page="pm_tasks">
                <i class="fas fa-tasks"></i>
                <span>TASKS</span>
            </li>
            <li class="menu-item" id="menu-reports" data-page="pm_reports">
                <i class="fas fa-chart-bar"></i>
                <span>REPORTS & ANALYTICS</span>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="topbar">
            <div class="topbar-left">
                <div class="logo-large">
                    <div class="logo-large-text">REMOCO - Project Manager Dashboard</div>
                </div>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($employee_name); ?></div>
                </div>
                <button class="logout-btn" onclick="location.href='logout.php'">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </div>
        </div>


        

        <div class="content-area">
            <div id="content-container">
                <!-- AJAX content loads here -->
            </div>
        </div>
    </div>

    <script>
        document.getElementById('toggleSidebar').addEventListener('click', function () {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
        });

        const menuItems = document.querySelectorAll('.menu-item');

        // The sidebar is the single source of truth for which panels exist.
        // `page` arrives from the URL, so anything not advertised here is refused
        // rather than fetched.
        const allowedPages = Array.from(menuItems)
            .map(item => item.getAttribute('data-page'))
            .filter(Boolean);

        function loadContent(page) {
            const contentContainer = document.getElementById('content-container');

            if (!allowedPages.includes(page)) {
                contentContainer.textContent = 'Unknown page requested.';
                return;
            }
            contentContainer.innerHTML = '<div style="text-align:center; padding:50px;"><i class="fas fa-spinner fa-spin fa-3x"></i></div>';
            $.ajax({
                url: page + '.php',
                type: 'GET',
                success: function (data) {
                    // jQuery .html() evaluates scripts in the injected markup;
                    // assigning to innerHTML does not, which is why the
                    // chat panel rendered but never came alive.
                    $(contentContainer).html(data);
                },
                error: function () {
                    contentContainer.innerHTML = '<div class="error-message" style="text-align:center; padding:50px; color:#c62828;">Error loading content. Please try again.</div>';
                }
            });
        }

        // Function to get URL parameter
        function getUrlParameter(name) {
            name = name.replace(/[\[\]]/g, '\\$&');
            const regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)');
            const results = regex.exec(window.location.href);
            if (!results) return null;
            if (!results[2]) return '';
            return decodeURIComponent(results[2].replace(/\+/g, ' '));
        }

        // Set active menu based on URL parameter
        function setActiveMenu(page) {
            menuItems.forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('data-page') === page) {
                    item.classList.add('active');
                }
            });
        }

        // On page load
        document.addEventListener('DOMContentLoaded', function() {
            // Get page from URL parameter or default to pm_home
            const pageParam = getUrlParameter('page') || 'pm_home';
            
            // Set active menu
            setActiveMenu(pageParam);
            
            // Load content
            loadContent(pageParam);
        });

        // Menu item click event
        menuItems.forEach(item => {
            item.addEventListener('click', function () {
                const pageId = this.getAttribute('data-page');
                setActiveMenu(pageId);
                loadContent(pageId);
                
                // Update URL without reloading page
                const newUrl = window.location.pathname + '?page=' + pageId;
                window.history.pushState({}, '', newUrl);
            });
        });

        // Handle browser back/forward navigation
        window.addEventListener('popstate', function() {
            const pageParam = getUrlParameter('page') || 'pm_home';
            setActiveMenu(pageParam);
            loadContent(pageParam);
        });
    </script>


</body>
</html>