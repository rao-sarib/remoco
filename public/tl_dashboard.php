<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';

require_once __DIR__ . '/../includes/config.php';

// Create connection
$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create checkpoints table if not exists
$createCheckpointsTableSQL = "CREATE TABLE IF NOT EXISTS checkpoints (
    checkpoint_id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    checkpoint VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'Pending',
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE
) ENGINE=InnoDB";
$pdo->exec($createCheckpointsTableSQL);

// Check if user is logged in and is a Team Lead
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'employee' || $_SESSION['designation'] !== 'Team Lead') {
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
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REMOCO - Team Lead Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Dashboard Styles */
        .dashboard-welcome {
            background: white;
            border-radius: 12px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .dashboard-title {
            font-size: 2.2rem;
            color: var(--primary);
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .dashboard-subtitle {
            font-size: 1.2rem;
            color: var(--gray);
            max-width: 700px;
            margin: 0 auto 30px;
            line-height: 1.6;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(250px, 100%), 1fr));
            gap: 20px;
            margin-top: 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 1.1rem;
            color: var(--gray);
        }
        
        .dashboard-content {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .dashboard-row {
            display: flex;
            gap: 30px;
        }
        
        .dashboard-column {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.2rem;
            color: var(--dark);
        }
        
        .card-footer {
            padding: 15px 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-link:hover {
            text-decoration: underline;
        }
        
        .btn-icon {
            background: none;
            border: none;
            color: var(--gray);
            font-size: 1rem;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-icon:hover {
            background-color: #f1f5f9;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .task-list {
            padding: 20px;
        }
        
        .task-item {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            background-color: #f8fafc;
        }
        
        .task-item.priority-high {
            border-left: 4px solid #ef4444;
        }
        
        .task-item.priority-medium {
            border-left: 4px solid #f59e0b;
        }
        
        .task-item.priority-low {
            border-left: 4px solid #10b981;
        }
        
        .task-info {
            margin-bottom: 12px;
        }
        
        .task-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .task-details {
            display: flex;
            gap: 15px;
            font-size: 0.9rem;
            color: var(--gray);
            flex-wrap: wrap;
        }
        
        .task-deadline {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .task-status {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .status-not-started {
            background-color: #e2e8f0;
            color: var(--dark);
        }
        
        .status-in-progress {
            background-color: #dbeafe;
            color: var(--primary);
        }
        
        .status-completed {
            background-color: #dcfce7;
            color: #10b981;
        }
        
        .task-progress {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .progress-bar {
            flex: 1;
            height: 8px;
            background-color: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress {
            height: 100%;
            background-color: var(--primary);
            border-radius: 4px;
        }
        
        .progress-text {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .chart-container {
            padding: 20px;
            height: 250px;
        }
        
        .project-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .project-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            transition: transform 0.3s;
        }
        
        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }
        
        .project-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .project-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            background-color: var(--primary);
        }
        
        .project-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .project-description {
            color: var(--gray);
            font-size: 0.95rem;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .project-progress {
            margin-bottom: 20px;
        }
        
        .project-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .project-deadline {
            font-size: 0.9rem;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .project-priority {
            font-size: 0.85rem;
            padding: 3px 8px;
            border-radius: 4px;
        }
        
        .priority-high {
            background-color: #fee2e2;
            color: #ef4444;
        }
        
        .priority-medium {
            background-color: #fef3c7;
            color: #f59e0b;
        }
        
        .priority-low {
            background-color: #dcfce7;
            color: #10b981;
        }
        
        .deadline-list {
            padding: 20px;
        }
        
        .deadline-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .deadline-item:last-child {
            border-bottom: none;
        }
        
        .deadline-date {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            background-color: #f1f5f9;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .deadline-day {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1;
        }
        
        .deadline-month {
            font-size: 0.85rem;
            color: var(--gray);
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .deadline-details {
            flex: 1;
        }
        
        .deadline-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .deadline-project {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .action-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            padding: 20px;
        }
        
        .action-item {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .action-item:hover {
            background-color: #f1f5f9;
            transform: translateY(-3px);
            border-color: var(--primary);
        }
        
        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #dbeafe;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.5rem;
        }
        
        .action-text {
            font-weight: 500;
            color: var(--dark);
        }
        
        .loading-indicator {
            padding: 30px;
            text-align: center;
            color: var(--gray);
        }
        
        .fa-spinner {
            margin-right: 10px;
        }
        
        .task-form {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .btn-cancel {
            background: white;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }

        @media (max-width: 1200px) {
            .dashboard-row {
                flex-direction: column;
            }
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
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .project-grid {
                grid-template-columns: 1fr;
            }
            
            .action-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
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
            <li class="menu-item active" id="menu-home" data-page="tl_home">
                <i class="fas fa-home"></i>
                <span>HOME</span>
            </li>
            <li class="menu-item" id="menu-chats" data-page="tl_chats">
                <i class="fas fa-comments"></i>
                <span>CHATS</span>
            </li>
            <li class="menu-item" id="menu-assigned_tasks" data-page="tl_assigned_tasks">
                <i class="fas fa-tasks"></i>
                <span>ASSIGNED TASKS</span>
            </li>
            <li class="menu-item" id="menu-tasks" data-page="tl_tasks">
                <i class="fas fa-clipboard-list"></i>
                <span>TASKS</span>
            </li>
            <li class="menu-item" id="menu-reports" data-page="tl_reports">
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
                    <div class="logo-large-text">REMOCO - Team Lead Dashboard</div>
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
                <?php include __DIR__ . '/tl_home.php'; ?>
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
            // Get page from URL parameter or default to tl_home
            const pageParam = getUrlParameter('page') || 'tl_home';
            
            // Set active menu
            setActiveMenu(pageParam);
            
            // Load content if it's not already loaded
            if (pageParam !== 'tl_home') {
                loadContent(pageParam);
            }
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
            const pageParam = getUrlParameter('page') || 'tl_home';
            setActiveMenu(pageParam);
            loadContent(pageParam);
        });
    </script>
</body>
</html>