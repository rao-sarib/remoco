<?php
// ================= DATABASE SETUP ================= //
require_once __DIR__ . '/../includes/session_bootstrap.php';

require_once __DIR__ . '/../includes/config.php';

try {
    // Connect and create DB/tables if they don't exist
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    $pdo->exec("USE $dbname");

    // Create companies table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS companies (
            company_id VARCHAR(10) PRIMARY KEY,
            company_name VARCHAR(100) NOT NULL,
            is_registered BOOLEAN DEFAULT FALSE,
            company_ntn VARCHAR(20),
            company_sector VARCHAR(50),
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Create employees table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS employees (
            employee_id INT AUTO_INCREMENT PRIMARY KEY,
            employee_name VARCHAR(100) NOT NULL,
            cnic VARCHAR(15) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            company_id VARCHAR(10),
            designation VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(company_id)
        )
    ");

    // Create tasks table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tasks (
            task_id INT(11) NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL, 
            task_description TEXT  NULL,
            due_date DATE NULL,
            task_priority ENUM('High', 'Medium', 'Low')  NOT NULL,
            team_lead_id INT(11) NULL,
            task_status ENUM('Not Started', 'In Progress', 'Completed')  NULL DEFAULT 'Not Started',
            assigned_by INT(11) NULL,
            created_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completion_date DATE NULL,
            tm1 INT(11) NULL,
            tm2 INT(11) NULL,
            tm3 INT(11) NULL,
            PRIMARY KEY (task_id),
            FOREIGN KEY (team_lead_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
            FOREIGN KEY (assigned_by) REFERENCES employees(employee_id) ON DELETE SET NULL,
            FOREIGN KEY (tm1) REFERENCES employees(employee_id) ON DELETE SET NULL,
            FOREIGN KEY (tm2) REFERENCES employees(employee_id) ON DELETE SET NULL,
            FOREIGN KEY (tm3) REFERENCES employees(employee_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Create chats table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chats (
            chat_id INT(11) NOT NULL AUTO_INCREMENT,
            chat_title VARCHAR(255)  NULL,
            task_id INT(11) NULL,
            pm_id INT(11) NULL,
            tl_id INT(11) NULL,
            tm1_id INT(11) NULL,
            tm2_id INT(11) NULL,
            tm3_id INT(11) NULL,
            firebase_room_id VARCHAR(100) NULL,
            PRIMARY KEY (chat_id),
            FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE SET NULL,
            FOREIGN KEY (pm_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
            FOREIGN KEY (tl_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
            FOREIGN KEY (tm1_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
            FOREIGN KEY (tm2_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
            FOREIGN KEY (tm3_id) REFERENCES employees(employee_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Create checkpoints table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS checkpoints (
            checkpoint_id INT(11) NOT NULL AUTO_INCREMENT,
            task_id INT(11) NOT NULL,
            checkpoint VARCHAR(255)  NOT NULL,
            status VARCHAR(50)  NULL DEFAULT 'Pending',
            PRIMARY KEY (checkpoint_id),
            FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Create video_calls table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS video_calls (
            call_id INT(11) PRIMARY KEY AUTO_INCREMENT,
            chat_id INT(11) NOT NULL,
            call_start DATETIME NOT NULL,
            call_end DATETIME DEFAULT NULL,
            call_status ENUM('requested', 'active', 'completed') DEFAULT 'active',
            initiator_id INT(11) NOT NULL,
            agora_channel VARCHAR(255) NOT NULL,
            FOREIGN KEY (chat_id) REFERENCES chats(chat_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Create shared_files table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shared_files (
            file_id INT(11) PRIMARY KEY AUTO_INCREMENT,
            chat_id INT(11) NOT NULL,
            uploaded_by INT(11) NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            upload_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (chat_id) REFERENCES chats(chat_id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES employees(employee_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Create indexes. ALTER TABLE ... ADD INDEX is not idempotent, so each index
    // is checked against information_schema first, keeping this block safe to run
    // on every request.
    $indexes = [
        'video_calls' => 'idx_chat_id',
        'shared_files' => 'idx_chat_id',
    ];
    foreach ($indexes as $table => $index) {
        $exists = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
                                 WHERE table_schema = ? AND table_name = ? AND index_name = ?");
        $exists->execute([$dbname, $table, $index]);
        if ((int) $exists->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `$table` ADD INDEX `$index` (chat_id)");
        }
    }

} catch (PDOException $e) {
    // Hide database errors from users in production
    error_log("Database error: " . $e->getMessage());
    // Continue rendering the page without database functionality
    $pdo = null;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'register':
                // Registration logic would go here
                break;
            case 'login':
                // Login logic would go here
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REMOCO - Remote Team Collaboration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ================= INTERNAL CSS ================= */
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
            --transition: all 0.3s ease;
        }
        
        body {
            background: linear-gradient(135deg, #e6f0ff 0%, #f0f4f8 100%);
            min-height: 100vh;
            color: var(--dark);
            overflow-x: hidden;
            line-height: 1.6;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header */
        header {
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(5px);
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        
        .logo-icon {
            font-size: 32px;
            color: var(--primary);
        }
        
        .logo-text {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(45deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        nav ul {
            display: flex;
            list-style: none;
            gap: 30px;
            align-items: center;
        }
        
        nav a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: var(--transition);
            position: relative;
            padding: 5px 0;
        }
        
        nav a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: var(--transition);
        }
        
        nav a:hover::after {
            width: 100%;
        }
        
        .btn {
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 16px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.25);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(37, 99, 235, 0.3);
        }
        
        /* Hero Section */
        .hero {
            padding: 100px 0 60px;
            display: flex;
            align-items: center;
            min-height: 80vh;
        }
        
        .hero-content {
            max-width: 600px;
        }
        
        .hero h1 {
            font-size: 48px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 20px;
            background: linear-gradient(45deg, var(--dark), var(--primary-dark));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .hero p {
            font-size: 18px;
            color: var(--gray);
            margin-bottom: 30px;
        }
        
        .hero-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-secondary {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        .btn-secondary:hover {
            background: rgba(37, 99, 235, 0.08);
        }
        
        .hero-image {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 45%;
            max-width: 550px;
            z-index: -1;
            opacity: 0.9;
        }
        
        /* Features */
        .features {
            padding: 80px 0;
            background: white;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 50px;
            font-size: 36px;
            font-weight: 700;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--primary);
            border-radius: 2px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        .feature-card {
            background: var(--light);
            border-radius: 15px;
            padding: 30px;
            transition: var(--transition);
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.08);
            border-color: var(--primary);
        }
        
        .feature-icon {
            width: 70px;
            height: 70px;
            background: rgba(37, 99, 235, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            transition: var(--transition);
        }
        
        .feature-card:hover .feature-icon {
            background: var(--primary);
        }
        
        .feature-icon i {
            font-size: 32px;
            color: var(--primary);
            transition: var(--transition);
        }
        
        .feature-card:hover .feature-icon i {
            color: white;
        }
        
        .feature-card h3 {
            margin-bottom: 15px;
            font-size: 22px;
        }
        
        /* Footer */
        footer {
            background: #0f172a;
            color: white;
            padding: 50px 0 20px;
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 40px;
            margin-bottom: 30px;
        }
        
        .footer-logo {
            flex: 1;
            min-width: 300px;
        }
        
        .footer-logo .logo {
            margin-bottom: 20px;
        }
        
        .footer-logo p {
            color: #cbd5e1;
            max-width: 350px;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .social-link:hover {
            background: var(--primary);
            transform: translateY(-5px);
        }
        
        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #1e293b;
            color: #94a3b8;
            font-size: 14px;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .hero {
                text-align: center;
                padding: 80px 0 40px;
            }
            
            .hero-content {
                margin: 0 auto;
            }
            
            .hero h1 {
                font-size: 40px;
            }
            
            .hero-image {
                position: relative;
                width: 100%;
                max-width: 500px;
                margin: 50px auto 0;
                transform: none;
                opacity: 0.7;
            }
            
            .hero-buttons {
                justify-content: center;
            }
        }
        
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 20px;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .hero h1 {
                font-size: 32px;
            }
            
            .section-title {
                font-size: 28px;
            }
            
            .footer-content {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .footer-logo p {
                margin: 0 auto;
            }
            
            .social-links {
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .hero h1 {
                font-size: 28px;
            }
            
            .hero p {
                font-size: 16px;
            }
            
            .hero-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-container">
                <a href="#" class="logo">
                    <i class="fas fa-users-cog logo-icon"></i>
                    <div class="logo-text">REMOCO</div>
                </a>
                
                <nav>
                    <ul>
                        <li><a href="#">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#">Solutions</a></li>
                        <li><button class="btn btn-primary" id="getStartedBtn">Get Started</button></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Collaborate Seamlessly with Your Remote Team</h1>
                <p>REMOCO brings your team together with real-time communication, task management, and secure collaboration tools - all in one intuitive platform designed for distributed teams.</p>
                
                <div class="hero-buttons">
                    <button class="btn btn-primary" id="mainGetStartedBtn">Get Started</button>
                    <button class="btn btn-secondary" id="demoBtn">
                        <i class="fas fa-play-circle"></i> Watch Demo
                    </button>
                </div>
            </div>
            
            <div class="hero-image">
                <svg viewBox="0 0 600 400" xmlns="http://www.w3.org/2000/svg">
                    <rect x="50" y="50" width="500" height="300" rx="20" fill="#ffffff" stroke="#2563eb" stroke-width="2"/>
                    <circle cx="100" cy="100" r="40" fill="#dbeafe"/>
                    <rect x="170" y="80" width="300" height="30" rx="15" fill="#dbeafe"/>
                    <rect x="170" y="130" width="250" height="20" rx="10" fill="#dbeafe"/>
                    <rect x="170" y="160" width="200" height="20" rx="10" fill="#dbeafe"/>
                    <rect x="100" y="200" width="350" height="100" rx="15" fill="#dbeafe"/>
                    <circle cx="480" cy="100" r="20" fill="#2563eb"/>
                    <circle cx="520" cy="100" r="20" fill="#f59e0b"/>
                </svg>
            </div>
        </div>
    </section>

    <!-- Features Section -->
<section id="features" class="features">
    <div class="container">
        <h2 class="section-title">Powerful Collaboration Features</h2>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <h3>Real-Time Chat</h3>
                <p>Communicate instantly with your team through channels and direct messages with end-to-end encryption.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <h3>Task Management</h3>
                <p>Create, assign, and organize tasks with priorities and deadlines. Manage workloads efficiently.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Progress Tracking</h3>
                <p>Monitor task completion rates, visualize team performance, and identify bottlenecks with analytics.</p>
            </div>
        </div>
    </div>
</section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <a href="#" class="logo">
                        <i class="fas fa-users-cog logo-icon"></i>
                        <div class="logo-text">REMOCO</div>
                    </a>
                    <p>Empowering distributed teams to collaborate effectively with secure, all-in-one tools designed for the modern workplace.</p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> REMOCO. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- ================= INTERNAL JAVASCRIPT ================= -->
    <script>
        // Button click handlers
        document.getElementById('getStartedBtn').addEventListener('click', function() {
            window.location.href = 'login.php';
        });

        document.getElementById('mainGetStartedBtn').addEventListener('click', function() {
            window.location.href = 'login.php';
        });

        document.getElementById('demoBtn').addEventListener('click', function() {
            alert('Demo video would play here');
            // In a real implementation, this would open a modal with a video
        });

        // Database status check
        <?php if ($pdo === null): ?>
            console.error('Database connection failed - running in limited mode');
        <?php endif; ?>
    </script>
</body>
</html>