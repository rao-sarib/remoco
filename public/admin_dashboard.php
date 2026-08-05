<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';

// Check if company is logged in
if (!isset($_SESSION['user_type'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['user_type'] !== 'company') {
    header('Location: login.php');
    exit;
}

$company_id = $_SESSION['company_id'];
$company_name = $_SESSION['company_name'];

require_once __DIR__ . '/../includes/config.php';

$pdo = null;

try {
    // Connect to database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("Database error. Please try again later.");
}

// Initialize variables
$employee_name = $cnic = $email = $password = $designation = '';
$errors = [];
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_page();

    // Get form data
    $employee_name = trim($_POST['employee_name']);
    $cnic = trim($_POST['cnic']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $designation = trim($_POST['designation']);
    
    // Simple validation - only check for empty fields
    if (empty($employee_name)) $errors['employee_name'] = 'Employee name is required';
    if (empty($cnic)) $errors['cnic'] = 'CNIC is required';
    if (empty($email)) $errors['email'] = 'Email is required';
    if (empty($password)) $errors['password'] = 'Password is required';
    if (empty($designation)) $errors['designation'] = 'Designation is required';
    
    // If no errors, insert into database
if (empty($errors)) {
        try {
            // Check if email already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ?");
            $checkStmt->execute([$email]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors['email'] = 'Email is already registered';
            } else {
                // Proceed with insertion if email is unique
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $created_at = date('Y-m-d H:i:s');
                
                // Clean CNIC by removing dashes
                $clean_cnic = str_replace('-', '', $cnic);
                
                $stmt = $pdo->prepare("INSERT INTO employees 
                                      (employee_name, cnic, email, password, company_id, designation, created_at) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $employee_name,
                    $clean_cnic,
                    $email,
                    $hashed_password,
                    $company_id,
                    $designation,
                    $created_at
                ]);
                
                $success = "Employee registered successfully!";
                
                // Reset form values
                $employee_name = $cnic = $email = $password = $designation = '';
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $errors['database'] = "Database error. Please try again later.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REMOCO - Company Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

        .company-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f1f5f9;
            padding: 5px 15px;
            border-radius: 20px;
        }

        .company-icon {
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

        .company-name {
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
            .company-info {
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
            <li class="menu-item" id="menu-home" data-page="home">
                <i class="fas fa-home"></i>
                <span>HOME</span>
            </li>
            <li class="menu-item" id="menu-employee_registration" data-page="employee_registration">
                <i class="fas fa-user-plus"></i>
                <span>EMPLOYEE REGISTRATION</span>
            </li>
            <li class="menu-item" id="menu-employees" data-page="employees">
                <i class="fas fa-users"></i>
                <span>EMPLOYEES</span>
            </li>
            <li class="menu-item" id="menu-tasks" data-page="tasks">
                <i class="fas fa-tasks"></i>
                <span>TASKS</span>
            </li>
            <li class="menu-item" id="menu-reports_analytics" data-page="reports_analytics">
                <i class="fas fa-chart-bar"></i>
                <span>REPORTS & ANALYTICS</span>
            </li>
            <li class="menu-item" id="menu-set_alerts" data-page="set_alerts">
                <i class="fas fa-bell"></i>
                <span>SET ALERTS</span>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="topbar">
            <div class="topbar-left">
                <div class="logo-large">
                    <div class="logo-large-text">REMOCO - Admin Dashboard</div>
                </div>
            </div>
            <div class="topbar-right">
                <div class="company-info">
                    <div class="company-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="company-name">ID: <?php echo htmlspecialchars($company_id); ?></div>
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
            // Get page from URL parameter or default to home
            const pageParam = getUrlParameter('page') || 'home';
            
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
            const pageParam = getUrlParameter('page') || 'home';
            setActiveMenu(pageParam);
            loadContent(pageParam);
        });

        $(document).on('submit', '#registrationForm', function(e) {
  e.preventDefault();
  const $form = $(this);
  const $container = $('#content-container');
  $container.html('<div style="text-align:center; padding:50px;"><i class="fas fa-spinner fa-spin fa-3x"></i></div>');
  
  $.ajax({
    url: 'employee_registration.php', // standalone handler
    type: 'POST',
    data: $form.serialize(),
    success(data) {
      // Replace with the full HTML returned (form + messages)
      $container.html(data);
    },
    error() {
      $container.html('<div class="error-message" style="text-align:center; padding:50px; color:#c62828;">Error submitting form. Please try again.</div>');
    }
  });
});
    </script>
</body>
</html>