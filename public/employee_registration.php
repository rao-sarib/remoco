<?php

$errors  = $_REQUEST['emp_errors']  ?? [];
$success = $_REQUEST['emp_success'] ?? '';


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
    
    // If no errors, check for duplicate email before insertion
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
    <title>Employee Registration - REMOCO</title>
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
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .registration-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px;
        }
        
        .registration-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .registration-title {
            font-size: 2.2rem;
            color: var(--dark);
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .registration-subtitle {
            font-size: 1.1rem;
            color: var(--gray);
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .registration-form {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 500;
            color: var(--dark);
            font-size: 1.05rem;
        }
        
        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus, 
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .error-text {
            color: #c62828;
            font-size: 0.9rem;
            margin-top: 5px;
            display: block;
        }
        
        .error-field {
            border-color: #c62828 !important;
            background-color: #ffebee;
        }
        
        .password-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gray);
            font-size: 1.1rem;
            z-index: 2;
            background: transparent;
            border: none;
            outline: none;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        .submit-btn {
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 16px 30px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: block;
            width: 100%;
            text-align: center;
        }
        
        .submit-btn:hover {
            opacity: 0.92;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(37, 99, 235, 0.25);
        }
        
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
            border: 1px solid #c8e6c9;
        }
        
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
            border: 1px solid #ffcdd2;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .registration-form {
                padding: 25px;
            }
            
            .registration-container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <div class="registration-header">
            <h1 class="registration-title">Employee Registration</h1>
            <p class="registration-subtitle">Register new employees to join your company's remote workforce</p>
        </div>
        
        <?php if (!empty($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors['database'])): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $errors['database']; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-message"><i class="fas fa-check-circle"></i> <?= $success ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>

        <form class="registration-form" method="POST" id="registrationForm">
                        <?php echo csrf_field(); ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="employee_name">Employee Name *</label>
                    <input type="text" id="employee_name" name="employee_name" 
                           placeholder="Enter full name" required
                           value="<?php echo htmlspecialchars($employee_name); ?>"
                           class="<?php echo isset($errors['employee_name']) ? 'error-field' : ''; ?>">
                    <?php if (isset($errors['employee_name'])): ?>
                        <span class="error-text"><?php echo $errors['employee_name']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="cnic">CNIC * <small>(Format: XXXXXXXXXXXXX)</small></label>
                    <input type="number" id="cnic" name="cnic" 
                           placeholder="Enter CNIC (e.g., 1234512345671)" required
                           maxlength="13" 
                           value="<?php echo htmlspecialchars($cnic); ?>"
                           class="<?php echo isset($errors['cnic']) ? 'error-field' : ''; ?>">
                    <?php if (isset($errors['cnic'])): ?>
                        <span class="error-text"><?php echo $errors['cnic']; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" 
                       placeholder="employee@company.com" required
                       value="<?php echo htmlspecialchars($email); ?>"
                       class="<?php echo isset($errors['email']) ? 'error-field' : ''; ?>">
                <?php if (isset($errors['email'])): ?>
                    <span class="error-text"><?php echo $errors['email']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="designation">Designation *</label>
                    <select id="designation" name="designation" required
                            class="<?php echo isset($errors['designation']) ? 'error-field' : ''; ?>">
                        <option value="">Select designation</option>
                        <option value="Project Manager" <?php echo ($designation === 'Project Manager') ? 'selected' : ''; ?>>Project Manager</option>
                        <option value="Team Lead" <?php echo ($designation === 'Team Lead') ? 'selected' : ''; ?>>Team Lead</option>
                        <option value="Team Member" <?php echo ($designation === 'Team Member') ? 'selected' : ''; ?>>Team Member</option>
                        <option value="Guest" <?php echo ($designation === 'Guest') ? 'selected' : ''; ?>>Guest</option>
                    </select>
                    <?php if (isset($errors['designation'])): ?>
                        <span class="error-text"><?php echo $errors['designation']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="company_id">Company ID</label>
                    <input type="text" id="company_id" value="<?php echo htmlspecialchars($company_id); ?>" readonly>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password *</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" minlength="8"
                           placeholder="Create password" required 
                           class="<?php echo isset($errors['password']) ? 'error-field' : ''; ?>">
                    <span class="toggle-password" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
                <?php if (isset($errors['password'])): ?>
                    <span class="error-text"><?php echo $errors['password']; ?></span>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="submit-btn">
                <i class="fas fa-user-plus"></i> Register Employee
            </button>
        </form>
    </div>
    
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form              = document.getElementById('registrationForm');
    const employeeNameInput = document.getElementById('employee_name');
    const cnicInput         = document.getElementById('cnic');
    const designationInput  = document.getElementById('designation');
    const passwordInput     = document.getElementById('password');
    const toggleButton      = document.getElementById('togglePassword');

    // Utility: get .form-group and its .error-text
    function clearError(el) {
        const group = el.closest('.form-group');
        el.classList.remove('error-field');
        const err = group.querySelector('.error-text');
        if (err) err.textContent = '';
    }
    function setError(el, message) {
        const group = el.closest('.form-group');
        el.classList.add('error-field');
        const err = group.querySelector('.error-text');
        if (err) err.textContent = message;
    }

    form.addEventListener('submit', function (e) {
        let valid = true;

        // Clear previous errors
        [employeeNameInput, cnicInput, designationInput, passwordInput].forEach(clearError);

        // 1) Employee Name non-empty
        if (employeeNameInput.value.trim() === '') {
            valid = false;
            setError(employeeNameInput, 'Employee name is required');
        }

        // 3) Designation selected
        if (designationInput.value === '') {
            valid = false;
            setError(designationInput, 'Please select a designation');
        }

        if (!valid) {
            e.preventDefault();
        }
    });

    // Toggle password visibility
    toggleButton.addEventListener('click', function () {
        const icon = this.querySelector('i');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });

    // CNIC formatting
    cnicInput.addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 13) value = value.slice(0,13);
        if (value.length > 5) value = value.slice(0,5) + '-' + value.slice(5);
        if (value.length > 13) value = value.slice(0,13) + '-' + value.slice(13);
        e.target.value = value;
    });
});
</script>

</body>
</html>