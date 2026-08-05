<?php
// ================= DATABASE SETUP ================= //
require_once __DIR__ . '/../includes/session_bootstrap.php';

require_once __DIR__ . '/../includes/config.php';

$pdo = null;

try {
    // Connect to database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}

// Handle form submissions
$error = '';
$success = '';
$registration_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'company_login':
                // Company login logic
                $company_id = trim($_POST['company_id']);
                $password = trim($_POST['password']);
                
                if (!empty($company_id) && !empty($password)) {
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM companies WHERE company_id = ?");
                        $stmt->execute([$company_id]);
                        $company = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($company && password_verify($password, $company['password'])) {
                            // Issue a fresh session id at the privilege change.
                            // Session data, including the CSRF token, carries over.
                            session_regenerate_id(true);

                            // Login successful
                            $_SESSION['company_id'] = $company['company_id'];
                            $_SESSION['company_name'] = $company['company_name'];
                            $_SESSION['user_type'] = 'company';
                            header('Location: admin_dashboard.php');
                            exit;
                        } else {
                            $error = "Invalid company ID or password";
                        }
                    } catch (PDOException $e) {
                        error_log("Database error: " . $e->getMessage());
                        $error = "Database error. Please try again later.";
                    }
                } else {
                    $error = "Please fill all required fields";
                }
                break;
                
            case 'employee_login':
                // Employee login logic
                $email = trim($_POST['email']);
                $password = trim($_POST['password']);
                $company_id = trim($_POST['company_id']);
                
                if (!empty($email) && !empty($password) && !empty($company_id)) {
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ? AND company_id = ?");
                        $stmt->execute([$email, $company_id]);
                        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($employee && password_verify($password, $employee['password'])) {
                            // Fresh session id at the privilege change - see the
                            // company login branch above.
                            session_regenerate_id(true);

                            // Login successful
                            $_SESSION['employee_id'] = $employee['employee_id'];
                            $_SESSION['employee_name'] = $employee['employee_name'];
                            $_SESSION['employee_email'] = $employee['email'];
                            $_SESSION['company_id'] = $employee['company_id'];
                            $_SESSION['designation'] = $employee['designation']; // Store designation
                            $_SESSION['user_type'] = 'employee';
                            
                            // Redirect based on designation
                            $designation = strtolower($employee['designation']); // Convert to lowercase
        
                            switch($designation) {
                                case 'project manager':
                                    header('Location: pm_dashboard.php');
                                    break;
                                case 'team lead':
                                    header('Location: tl_dashboard.php');
                                    break;
                                case 'team member': // Now properly matches
                                    header('Location: tm_dashboard.php');
                                    break;
                                default:
                                    // Designations without a dashboard of their own are not
                                    // signed in; the account is told to contact its
                                    // administrator instead.
                                    $_SESSION = [];
                                    session_regenerate_id(true);
                                    $error = "Your account has no dashboard assigned yet ("
                                        . htmlspecialchars($employee['designation'])
                                        . "). Please ask your company administrator to set your designation.";
                                    break 2;
                            }
                            exit;
                        } else {
                            $error = "Invalid credentials or company ID";
                        }
                    } catch (PDOException $e) {
                        error_log("Database error: " . $e->getMessage());
                        $error = "Database error. Please try again later.";
                    }
                } else {
                    $error = "Please fill all required fields";
                }
                break;
                
            case 'company_register':
                // Company registration logic (unchanged)
                $company_id = trim($_POST['company_id']);
                $company_name = trim($_POST['company_name']);
                $is_registered = isset($_POST['is_registered']) ? (int)$_POST['is_registered'] : 0;
                $company_ntn = ($is_registered == 1) ? trim($_POST['company_ntn']) : null;
                $company_sector = trim($_POST['company_sector']);
                $email = trim($_POST['email']);
                $password = trim($_POST['password']);
                $confirm_password = trim($_POST['confirm_password']);
                
                // Validate input
                if (empty($company_id)) {
                    $registration_errors['company_id'] = "Company ID is required";
                } elseif (!preg_match('/^[A-Z]{3}\d{3}$/', $company_id)) {
                    $registration_errors['company_id'] = "Company ID must be 3 uppercase letters followed by 3 numbers (e.g., ABC123)";
                } else {
                    // Check if company ID exists
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE company_id = ?");
                        $stmt->execute([$company_id]);
                        if ($stmt->fetchColumn() > 0) {
                            $registration_errors['company_id'] = "Company ID already exists - please choose a different ID";
                        }
                    } catch (PDOException $e) {
                        $registration_errors['company_id'] = "Database error checking company ID";
                    }
                }
                
                if (empty($company_name)) {
                    $registration_errors['company_name'] = "Company name is required";
                }
                
                if ($is_registered == 1 && empty($company_ntn)) {
                    $registration_errors['company_ntn'] = "NTN is required for registered companies";
                }
                
                if (empty($company_sector)) {
                    $registration_errors['company_sector'] = "Company sector is required";
                }
                
                if (empty($email)) {
                    $registration_errors['email'] = "Email is required";
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $registration_errors['email'] = "Invalid email format";
                } else {
                    // Check if email exists
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE email = ?");
                        $stmt->execute([$email]);
                        if ($stmt->fetchColumn() > 0) {
                            $registration_errors['email'] = "Email already registered";
                        }
                    } catch (PDOException $e) {
                        $registration_errors['email'] = "Database error checking email";
                    }
                }
                
                if (empty($password)) {
                    $registration_errors['password'] = "Password is required";
                } elseif (strlen($password) < 8) {
                    $registration_errors['password'] = "Password must be at least 8 characters";
                }
                
                if ($password !== $confirm_password) {
                    $registration_errors['confirm_password'] = "Passwords do not match";
                }
                
                // If no errors, register the company
                if (empty($registration_errors)) {
                    try {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        $stmt = $pdo->prepare("INSERT INTO companies (company_id, company_name, is_registered, company_ntn, company_sector, email, password) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $company_id,
                            $company_name,
                            $is_registered,
                            $company_ntn,
                            $company_sector,
                            $email,
                            $hashed_password
                        ]);
                        
                        // Send email to company (simulated)
                        $to = $email;
                        $subject = "Your REMOCO Company Account";
                        $message = "Hello $company_name,\n\n";
                        $message .= "Your company has been successfully registered on REMOCO.\n";
                        $message .= "Company ID: $company_id\n";
                        $message .= "Please remember your Company ID as it will be required for all logins.\n\n";
                        $message .= "Thank you for choosing REMOCO!";
                        $headers = "From: noreply@remoco.com";
                        
                        // In a real system, you would actually send the email
                        // mail($to, $subject, $message, $headers);
                        
                        $success = "Company registered successfully! Your Company ID is $company_id. Please check your email for details.";
                        
                        // Reset form values
                        $_POST = [];
                    } catch (PDOException $e) {
                        error_log("Database error: " . $e->getMessage());
                        $error = "Database error. Please try again later.";
                    }
                }
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
    <title>REMOCO - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --accent: #f59e0b;
            --light: #f8f9fa;
            --dark: #1e293b;
            --gray: #64748b;
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            height: 100vh;
            display: flex;
            background-color: var(--light);
            overflow: hidden;
        }
        
        /* Branding Section - Fixed */
        .brand-section {
            flex: 1;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            justify-content: center;
            align-items: center;
            position: fixed;
            width: 50%;
            height: 100%;
            overflow: hidden;
            z-index: 10;
        }
        
        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 2;
            padding: 20px;
            text-align: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            margin-bottom: 20px;
        }
        
        .logo-icon {
            font-size: 4rem;
            color: white;
        }
        
        .logo-text {
            font-size: 3.5rem;
            font-weight: 800;
            color: white;
            letter-spacing: -2px;
        }
        
        .tagline {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.8rem;
            text-align: center;
            max-width: 80%;
            line-height: 1.4;
            margin-top: 20px;
        }
        
        .decoration {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
        }
        
        .decoration:nth-child(1) {
            width: 400px;
            height: 400px;
            top: -150px;
            left: -150px;
        }
        
        .decoration:nth-child(2) {
            width: 300px;
            height: 300px;
            bottom: -100px;
            right: -100px;
        }
        
        /* Login Section - Scrollable */
        .login-section {
            flex: 1;
            margin-left: 50%;
            width: 50%;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: white;
            position: relative;
            overflow-y: auto;
        }
        
        .login-container {
            width: 80%;
            max-width: 450px;
            padding: 40px 35px;
            border-radius: 16px;
            z-index: 2;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .login-header h2 {
            font-size: 2.2rem;
            color: var(--primary);
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .login-header p {
            color: var(--gray);
            font-size: 1.1rem;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .user-toggle {
            display: flex;
            background-color: #f0f2f5;
            border-radius: 50px;
            padding: 6px;
            margin-bottom: 30px;
        }
        
        .toggle-btn {
            flex: 1;
            padding: 14px;
            border: none;
            background: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1rem;
            color: #555;
        }
        
        .toggle-btn.active {
            background-color: #fff;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.1);
            color: var(--primary);
        }
        
        .login-form {
            display: none;
        }
        
        .login-form.active {
            display: block;
            animation: fadeIn 0.4s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-group {
            margin-bottom: 24px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 500;
            color: #444;
            font-size: 1rem;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 16px 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .error-text {
            color: #c62828;
            font-size: 0.85rem;
            margin-top: 5px;
            display: block;
        }
        
        .error-field {
            border-color: #c62828 !important;
            background-color: #ffebee;
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 10px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-btn {
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            color: white;
            margin-bottom: 20px;
        }
        
        .login-btn:hover {
            opacity: 0.92;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(37, 99, 235, 0.25);
        }
        
        .register-btn {
            background-color: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
            margin-top: 10px;
        }
        
        .register-btn:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .footer-links {
            text-align: center;
            margin-top: 30px;
            font-size: 0.95rem;
            color: #666;
        }
        
        .footer-links a {
            color: var(--primary);
            text-decoration: none;
            margin: 0 12px;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .footer-links a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .copyright {
            position: relative;
            margin-top: 30px;
            padding-bottom: 20px;
            text-align: center;
            color: #777;
            font-size: 0.9rem;
        }
        
        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }
        
        .modal.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 16px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }
        
        .modal.active .modal-content {
            transform: translateY(0);
        }
        
        .modal-header {
            margin-bottom: 25px;
            text-align: center;
        }
        
        .modal-title {
            color: var(--primary);
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .modal-subtitle {
            color: var(--gray);
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .radio-option input[type="radio"] {
            width: auto;
            margin: 0;
        }
        
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #777;
        }
        
        .modal-footer {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        /* Responsive Design */
        @media (max-width: 900px) {
            body {
                flex-direction: column;
            }
            
            .brand-section {
                position: relative;
                width: 100%;
                height: auto;
                min-height: 40vh;
                padding: 30px 20px;
            }
            
            .login-section {
                width: 100%;
                margin-left: 0;
                min-height: auto;
            }
            
            .logo-icon {
                font-size: 3rem;
            }
            
            .logo-text {
                font-size: 2.5rem;
            }
            
            .tagline {
                font-size: 1.4rem;
            }
            
            .login-container {
                width: 90%;
                margin: 20px auto;
            }
            
            .copyright {
                margin-top: 20px;
                padding-bottom: 10px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .radio-group {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Branding Section -->
    <div class="brand-section">
        <div class="decoration"></div>
        <div class="decoration"></div>
        <div class="logo-container">
            <a href="#" class="logo">
                <i class="fas fa-users-cog logo-icon"></i>
                <div class="logo-text">REMOCO</div>
            </a>
            <div class="tagline">Remote Workforce Management</div>
        </div>
    </div>
    
    <!-- Login Section - Scrollable -->
    <div class="login-section">
        <div class="login-container">
            <div class="login-header">
                <h2>Welcome Back</h2>
                <p>Sign in to your account</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="user-toggle">
                <button class="toggle-btn" id="companyToggle">
                    Company Login
                </button>
                <button class="toggle-btn active" id="employeeToggle">
                    Employee Login
                </button>
            </div>
            
            <!-- Company Login Form -->
            <form class="login-form" id="companyForm" method="post">
                <input type="hidden" name="action" value="company_login">
                <div class="form-group">
                    <label for="companyID">Company ID</label>
                    <input type="text" id="companyID" name="company_id" placeholder="Enter your company ID (e.g., ABC123)" required>
                </div>
                <div class="form-group">
                    <label for="companyPassword">Password</label>
                    <input type="password" id="companyPassword" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn login-btn">Login to Company Account</button>
                <button type="button" class="btn register-btn" id="registerBtn">Register New Company</button>
            </form>
            
            <!-- Employee Login Form -->
            <form class="login-form active" id="employeeForm" method="post">
                <input type="hidden" name="action" value="employee_login">
                <div class="form-group">
                    <label for="employeeEmail">Email Address</label>
                    <input type="email" id="employeeEmail" name="email" placeholder="you@company.com" required>
                </div>
                <div class="form-group">
                    <label for="employeePassword">Password</label>
                    <input type="password" id="employeePassword" name="password" placeholder="Enter your password" required>
                </div>
                <div class="form-group">
                    <label for="employeeCompanyID">Company ID</label>
                    <input type="text" id="employeeCompanyID" name="company_id" placeholder="Enter your company ID (e.g., ABC123)" required>
                </div>
                <button type="submit" class="btn login-btn">Login to Employee Account</button>
            </form>
            
            <div class="footer-links">
                <a href="#"><i class="fas fa-key"></i> Forgot Password?</a>
                <a href="#"><i class="fas fa-question-circle"></i> Help Center</a>
            </div>
            
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> REMOCO. All rights reserved.
            </div>
        </div>
    </div>
    
    <!-- Registration Modal -->
    <div class="modal <?php echo !empty($registration_errors) ? 'active' : ''; ?>" id="registrationModal">
        <div class="modal-content">
            <button class="close-btn" id="closeModal">&times;</button>
            <div class="modal-header">
                <h2 class="modal-title">Register Your Company</h2>
                <p class="modal-subtitle">Fill in your company details to create an account</p>
            </div>
            
            <?php if (!empty($registration_errors)): ?>
                <div class="alert alert-error" style="margin-bottom: 20px;">
                    Please fix the errors below to complete registration
                </div>
            <?php endif; ?>
            
            <form id="registrationForm" method="post">
                <input type="hidden" name="action" value="company_register">
                
                <div class="form-group">
                    <label for="regCompanyID">Company ID * <small>(Format: 3 letters + 3 numbers e.g., ABC123)</small></label>
                    <input type="text" id="regCompanyID" name="company_id" placeholder="Enter unique company ID" required
                           value="<?php echo isset($_POST['company_id']) ? htmlspecialchars($_POST['company_id']) : ''; ?>"
                           class="<?php echo isset($registration_errors['company_id']) ? 'error-field' : ''; ?>">
                    <?php if (isset($registration_errors['company_id'])): ?>
                        <span class="error-text"><?php echo $registration_errors['company_id']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="regCompanyName">Company Name *</label>
                    <input type="text" id="regCompanyName" name="company_name" placeholder="Enter company name" required
                           value="<?php echo isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : ''; ?>">
                    <?php if (isset($registration_errors['company_name'])): ?>
                        <span class="error-text"><?php echo $registration_errors['company_name']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Is your company registered? *</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="regYes" name="is_registered" value="1" 
                                <?php echo (isset($_POST['is_registered']) && $_POST['is_registered'] == '1') ? 'checked' : ''; ?>>
                            <label for="regYes">Yes</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="regNo" name="is_registered" value="0" 
                                <?php echo (isset($_POST['is_registered']) && $_POST['is_registered'] == '0') ? 'checked' : 'checked'; ?>>
                            <label for="regNo">No</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" id="ntnGroup" style="display: none;">
                    <label for="regNTN">NTN Number *</label>
                    <input type="text" id="regNTN" name="company_ntn" placeholder="Enter NTN number"
                           value="<?php echo isset($_POST['company_ntn']) ? htmlspecialchars($_POST['company_ntn']) : ''; ?>">
                    <?php if (isset($registration_errors['company_ntn'])): ?>
                        <span class="error-text"><?php echo $registration_errors['company_ntn']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="regSector">Company Sector *</label>
                    <select id="regSector" name="company_sector" required>
                        <option value="">Select your sector</option>
                        <option value="Technology" <?php echo (isset($_POST['company_sector']) && $_POST['company_sector'] === 'Technology') ? 'selected' : ''; ?>>Technology</option>
                        <option value="Finance" <?php echo (isset($_POST['company_sector']) && $_POST['company_sector'] === 'Finance') ? 'selected' : ''; ?>>Finance</option>
                        <option value="Healthcare" <?php echo (isset($_POST['company_sector']) && $_POST['company_sector'] === 'Healthcare') ? 'selected' : ''; ?>>Healthcare</option>
                        <option value="Education" <?php echo (isset($_POST['company_sector']) && $_POST['company_sector'] === 'Education') ? 'selected' : ''; ?>>Education</option>
                        <option value="Retail" <?php echo (isset($_POST['company_sector']) && $_POST['company_sector'] === 'Retail') ? 'selected' : ''; ?>>Retail</option>
                        <option value="Manufacturing" <?php echo (isset($_POST['company_sector']) && $_POST['company_sector'] === 'Manufacturing') ? 'selected' : ''; ?>>Manufacturing</option>
                        <option value="Other" <?php echo (isset($_POST['company_sector']) && $_POST['company_sector'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                    <?php if (isset($registration_errors['company_sector'])): ?>
                        <span class="error-text"><?php echo $registration_errors['company_sector']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="regEmail">Official Email *</label>
                    <input type="email" id="regEmail" name="email" placeholder="company@example.com" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <?php if (isset($registration_errors['email'])): ?>
                        <span class="error-text"><?php echo $registration_errors['email']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="regPassword">Password *</label>
                        <input type="password" id="regPassword" name="password" placeholder="Create password (min 8 characters)" required>
                        <?php if (isset($registration_errors['password'])): ?>
                            <span class="error-text"><?php echo $registration_errors['password']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="regConfirmPassword">Confirm Password *</label>
                        <input type="password" id="regConfirmPassword" name="confirm_password" placeholder="Confirm password" required>
                        <?php if (isset($registration_errors['confirm_password'])): ?>
                            <span class="error-text"><?php echo $registration_errors['confirm_password']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="submit" class="btn login-btn">Register Company</button>
                    <button type="button" class="btn register-btn" id="cancelRegistration">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle between login forms
        document.getElementById('companyToggle').addEventListener('click', function() {
            document.getElementById('companyForm').classList.add('active');
            document.getElementById('employeeForm').classList.remove('active');
            document.getElementById('companyToggle').classList.add('active');
            document.getElementById('employeeToggle').classList.remove('active');
        });
        
        document.getElementById('employeeToggle').addEventListener('click', function() {
            document.getElementById('employeeForm').classList.add('active');
            document.getElementById('companyForm').classList.remove('active');
            document.getElementById('employeeToggle').classList.add('active');
            document.getElementById('companyToggle').classList.remove('active');
        });
        
        // Registration modal functionality
        const registrationModal = document.getElementById('registrationModal');
        const registerBtn = document.getElementById('registerBtn');
        const closeModal = document.getElementById('closeModal');
        const cancelRegistration = document.getElementById('cancelRegistration');
        
        registerBtn.addEventListener('click', function() {
            registrationModal.classList.add('active');
        });
        
        closeModal.addEventListener('click', function() {
            registrationModal.classList.remove('active');
        });
        
        cancelRegistration.addEventListener('click', function() {
            registrationModal.classList.remove('active');
        });
        
        // Show/hide NTN field based on registration status
        const regYes = document.getElementById('regYes');
        const regNo = document.getElementById('regNo');
        const ntnGroup = document.getElementById('ntnGroup');
        
        // Set initial state
        if (regYes.checked) {
            ntnGroup.style.display = 'block';
        }
        
        regYes.addEventListener('change', function() {
            if (this.checked) {
                ntnGroup.style.display = 'block';
            }
        });
        
        regNo.addEventListener('change', function() {
            if (this.checked) {
                ntnGroup.style.display = 'none';
            }
        });
        
        // Validate company ID format
        const regCompanyID = document.getElementById('regCompanyID');
        regCompanyID.addEventListener('blur', function() {
            const value = this.value;
            if (value && !/^[A-Z]{3}\d{3}$/.test(value)) {
                this.style.borderColor = '#c62828';
            } else {
                this.style.borderColor = '#ddd';
            }
        });
        
        // Password match validation
        const regPassword = document.getElementById('regPassword');
        const regConfirmPassword = document.getElementById('regConfirmPassword');
        
        function validatePasswords() {
            if (regPassword.value !== regConfirmPassword.value) {
                regConfirmPassword.style.borderColor = '#c62828';
            } else {
                regConfirmPassword.style.borderColor = '#ddd';
            }
        }
        
        regPassword.addEventListener('blur', validatePasswords);
        regConfirmPassword.addEventListener('blur', validatePasswords);
        
        // Prevent form submission if passwords don't match
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            if (regPassword.value !== regConfirmPassword.value) {
                e.preventDefault();
                alert('Passwords do not match');
            }
        });
    </script>
</body>
</html>