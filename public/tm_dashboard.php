<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';

require_once __DIR__ . '/../includes/config.php';

// Create connection
$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check if user is logged in and is a Team Member
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'employee' || $_SESSION['designation'] !== 'Team Member') {
    header('Location: login.php');
    exit;
}

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

// Handle form submissions for task updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle update action for task status
    if (isset($_POST['update_task_status']) && isset($_POST['task_id'])) {
        csrf_require_page();

        $task_id = (int) $_POST['task_id'];
        $status  = $_POST['status'] ?? '';

        // Only the three values the column accepts.
        $allowedStatuses = ['Not Started', 'In Progress', 'Completed'];

        if (!in_array($status, $allowedStatuses, true)) {
            $_SESSION['error'] = "Invalid task status.";
        } else {
            try {
                // Ownership is enforced in the statement itself: a Team Member may
                // only move a task they are assigned to.
                $stmt = $pdo->prepare("UPDATE tasks SET task_status = ?
                                       WHERE task_id = ?
                                         AND ? IN (tm1, tm2, tm3)");
                $stmt->execute([$status, $task_id, $_SESSION['employee_id']]);

                if ($stmt->rowCount() > 0) {
                    $_SESSION['success'] = "Task status updated successfully!";
                } else {
                    // Same message whether the task is missing or simply not
                    // assigned to this member.
                    $_SESSION['error'] = "Task not found.";
                }
            } catch (PDOException $e) {
                error_log("Error updating task: " . $e->getMessage());
                $_SESSION['error'] = "Error updating task. Please try again later.";
            }
        }
        header("Location: tm_dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REMOCO - Team Member Dashboard</title>
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

        /* Flash messages, styled to match the existing alert language. */
        .flash {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 25px 0;
            padding: 14px 18px;
            border-radius: 8px;
            border-left: 4px solid;
            font-size: 0.95rem;
        }

        .flash-success {
            background-color: #d1fae5;
            color: #065f46;
            border-color: #10b981;
        }

        .flash-error {
            background-color: #fee2e2;
            color: #991b1b;
            border-color: #dc2626;
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

        .dashboard-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 25px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eef2f7;
        }

        .card-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary-dark);
        }

        .card-action {
            color: var(--primary);
            font-size: 0.9rem;
            cursor: pointer;
        }

        .card-action:hover {
            text-decoration: underline;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(250px, 100%), 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary);
            margin: 10px 0;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.95rem;
        }

        .stat-icon {
            align-self: flex-end;
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(37, 99, 235, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.5rem;
        }

        .task-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .task-table th {
            background-color: #f8fafc;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid #e2e8f0;
        }

        .task-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .task-table tr:hover {
            background-color: #f8fafc;
        }

        .priority-high {
            background-color: #fee2e2;
            color: #dc2626;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .priority-medium {
            background-color: #fef3c7;
            color: #d97706;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .priority-low {
            background-color: #dcfce7;
            color: #16a34a;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-not-started {
            background-color: #e5e7eb;
            color: #4b5563;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-in-progress {
            background-color: #dbeafe;
            color: #2563eb;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-completed {
            background-color: #dcfce7;
            color: #16a34a;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .action-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .action-btn:hover {
            background: var(--primary-dark);
        }

        .update-form {
            display: flex;
            gap: 10px;
        }

        .update-form select {
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            background: white;
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
            <li class="menu-item active" id="menu-home" data-page="tm_home">
                <i class="fas fa-home"></i>
                <span>HOME</span>
            </li>
            <li class="menu-item" id="menu-chats" data-page="tm_chats">
                <i class="fas fa-comments"></i>
                <span>CHATS</span>
            </li>
            <li class="menu-item" id="menu-assigned_tasks" data-page="tm_assigned_tasks">
                <i class="fas fa-tasks"></i>
                <span>ASSIGNED TASKS</span>
            </li>
            <li class="menu-item" id="menu-tasks" data-page="tm_tasks">
                <i class="fas fa-list-check"></i>
                <span>TASKS</span>
            </li>
            <li class="menu-item" id="menu-reports" data-page="tm_reports">
                <i class="fas fa-chart-bar"></i>
                <span>REPORTS</span>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="topbar">
            <div class="topbar-left">
                <div class="logo-large">
                    <div class="logo-large-text">REMOCO - Team Member Dashboard</div>
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
            <?php
            // Feedback from the task-status handler above.
            if (!empty($_SESSION['success'])):
                $flash = $_SESSION['success'];
                unset($_SESSION['success']);
            ?>
                <div class="flash flash-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($flash); ?></span>
                </div>
            <?php endif; ?>
            <?php
            if (!empty($_SESSION['error'])):
                $flash = $_SESSION['error'];
                unset($_SESSION['error']);
            ?>
                <div class="flash flash-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($flash); ?></span>
                </div>
            <?php endif; ?>
            <div id="content-container">
                <!-- AJAX content will load here -->
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
                error: function (xhr, status, error) {

                    contentContainer.innerHTML =
                        '<div class="error-message" style="text-align:center; padding:50px; color:#c62828;">' +
                        '<i class="fas fa-exclamation-triangle fa-2x"></i><h3></h3></div>';
                    contentContainer.querySelector('h3').textContent =
                        xhr.status === 404 ? 'Page not found: ' + page : 'Error loading content. Please try again.';
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
            // Get page from URL parameter or default to tm_home
            const pageParam = getUrlParameter('page') || 'tm_home';
            
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
            const pageParam = getUrlParameter('page') || 'tm_home';
            setActiveMenu(pageParam);
            loadContent(pageParam);
        });
    </script>
</body>
</html>