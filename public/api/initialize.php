<?php
/**
 * Schema installer. Public: the client calls it once on launch to ensure the
 * database and tables exist. It only ever CREATEs when missing and returns no
 * data, so it is safe without authentication.
 */
// Config lives outside the web root; support the repo and flat layouts.
foreach ([__DIR__ . '/../../includes/api_config.php', __DIR__ . '/../includes/api_config.php'] as $candidate) {
    if (is_file($candidate)) { require_once $candidate; break; }
}

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = new PDO("mysql:host=$host", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARSET=utf8mb4");
    $pdo->exec("USE `$dbname`");

    $pdo->exec("CREATE TABLE IF NOT EXISTS companies (
        company_id VARCHAR(10) PRIMARY KEY,
        company_name VARCHAR(100) NOT NULL,
        is_registered BOOLEAN DEFAULT FALSE,
        company_ntn VARCHAR(20),
        company_sector VARCHAR(50),
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employees (
        employee_id INT AUTO_INCREMENT PRIMARY KEY,
        employee_name VARCHAR(100) NOT NULL,
        cnic VARCHAR(15) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        company_id VARCHAR(10),
        designation VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(company_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        task_id INT(11) NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        task_description TEXT NULL,
        due_date DATE NULL,
        task_priority ENUM('High','Medium','Low') NOT NULL,
        team_lead_id INT(11) NULL,
        task_status ENUM('Not Started','In Progress','Completed') NULL DEFAULT 'Not Started',
        assigned_by INT(11) NULL,
        created_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completion_date DATE NULL,
        tm1 INT(11) NULL, tm2 INT(11) NULL, tm3 INT(11) NULL,
        PRIMARY KEY (task_id),
        FOREIGN KEY (team_lead_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
        FOREIGN KEY (assigned_by)  REFERENCES employees(employee_id) ON DELETE SET NULL,
        FOREIGN KEY (tm1) REFERENCES employees(employee_id) ON DELETE SET NULL,
        FOREIGN KEY (tm2) REFERENCES employees(employee_id) ON DELETE SET NULL,
        FOREIGN KEY (tm3) REFERENCES employees(employee_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS chats (
        chat_id INT(11) NOT NULL AUTO_INCREMENT,
        chat_title VARCHAR(255) NULL,
        task_id INT(11) NULL,
        pm_id INT(11) NULL, tl_id INT(11) NULL,
        tm1_id INT(11) NULL, tm2_id INT(11) NULL, tm3_id INT(11) NULL,
        firebase_room_id VARCHAR(100) NULL,
        PRIMARY KEY (chat_id),
        FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE SET NULL,
        FOREIGN KEY (pm_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
        FOREIGN KEY (tl_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
        FOREIGN KEY (tm1_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
        FOREIGN KEY (tm2_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
        FOREIGN KEY (tm3_id) REFERENCES employees(employee_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS checkpoints (
        checkpoint_id INT(11) NOT NULL AUTO_INCREMENT,
        task_id INT(11) NOT NULL,
        checkpoint VARCHAR(255) NOT NULL,
        status VARCHAR(50) NULL DEFAULT 'Pending',
        PRIMARY KEY (checkpoint_id),
        FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS video_calls (
        call_id INT(11) PRIMARY KEY AUTO_INCREMENT,
        chat_id INT(11) NOT NULL,
        call_start DATETIME NOT NULL,
        call_end DATETIME DEFAULT NULL,
        call_status ENUM('requested','active','completed') DEFAULT 'active',
        initiator_id INT(11) NOT NULL,
        agora_channel VARCHAR(255) NOT NULL,
        FOREIGN KEY (chat_id) REFERENCES chats(chat_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS shared_files (
        file_id INT(11) PRIMARY KEY AUTO_INCREMENT,
        chat_id INT(11) NOT NULL,
        uploaded_by INT(11) NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        upload_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (chat_id) REFERENCES chats(chat_id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by) REFERENCES employees(employee_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo json_encode(['status' => 'success', 'message' => 'Database ready']);
} catch (PDOException $e) {
    error_log('[remoco-api] initialize: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Database unavailable']);
}
