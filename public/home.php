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

// Get employee counts
$total_employees = 0;
$project_managers = 0;
$team_leads = 0;

try {
    // Get total employees
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM employees WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_employees = $result['total'];

    // Get project managers
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM employees WHERE company_id = ? AND designation = ?");
    $designation = "Project Manager";
    $stmt->execute([$company_id, $designation]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $project_managers = $result['count'];

    // Get team leads
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM employees WHERE company_id = ? AND designation = ?");
    $designation = "Team Lead";
    $stmt->execute([$company_id, $designation]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $team_leads = $result['count'];
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("Database error. Please try again later.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - REMOCO</title>
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
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .home-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .home-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .home-title {
            font-size: 2.2rem;
            color: var(--dark);
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .home-subtitle {
            font-size: 1.1rem;
            color: var(--gray);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 25px;
            margin-top: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 350px;
            text-align: center;
            transition: all 0.3s ease;
            flex: 1;
            min-width: 250px;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .stat-title {
            font-size: 1.4rem;
            color: var(--primary-dark);
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .stat-value {
            font-size: 2.8rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 1rem;
            color: var(--gray);
        }
        
        /* Responsive Design */
        @media (max-width: 900px) {
            .stats-container {
                flex-direction: column;
                align-items: center;
            }
            
            .stat-card {
                width: 100%;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="home-container">
        <div class="home-header">
            <h1 class="home-title">HOME</h1>
            <p class="home-subtitle">Welcome to your company dashboard. Here's a quick overview of your workforce.</p>
        </div>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-title">TOTAL EMPLOYEES</div>
                <div class="stat-value"><?php echo $total_employees; ?></div>
                <div class="stat-label">All employees in your organization</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-title">PROJECT MANAGERS</div>
                <div class="stat-value"><?php echo $project_managers; ?></div>
                <div class="stat-label">Leading your projects</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-friends"></i>
                </div>
                <div class="stat-title">TEAM LEADS</div>
                <div class="stat-value"><?php echo $team_leads; ?></div>
                <div class="stat-label">Managing your teams</div>
            </div>
        </div>
    </div>
    
    <script>
        // Simple animation for the stats
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            
            statCards.forEach((card, index) => {
                // Add slight delay for each card
                setTimeout(() => {
                    card.style.opacity = 0;
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    
                    // Animate in
                    setTimeout(() => {
                        card.style.opacity = 1;
                        card.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 150);
            });
        });
    </script>
</body>
</html>