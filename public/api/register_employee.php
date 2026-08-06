<?php
/**
 * Onboard an employee. Requires a company (admin) token; the new employee is
 * created in the caller's own company — the company_id is taken from the token,
 * never from the request, so an account cannot be planted in another company.
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_company();
$company_id = caller_company($claims);

// Accepts form fields (the client posts multipart/form-encoded here).
$fields = ['employee_name', 'cnic', 'email', 'password', 'designation'];
foreach ($fields as $f) {
    if (!isset($_POST[$f]) || trim($_POST[$f]) === '') {
        api_error("Missing or empty field: $f");
    }
}

$employee_name = trim($_POST['employee_name']);
$cnic          = str_replace('-', '', trim($_POST['cnic']));
$email         = trim($_POST['email']);
$password      = trim($_POST['password']);
$designation   = trim($_POST['designation']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_error('Invalid email format.');
}
if (!in_array($designation, ['Project Manager', 'Team Lead', 'Team Member', 'Guest'], true)) {
    api_error('Invalid designation.');
}
if (strlen($password) < 8) {
    api_error('Password must be at least 8 characters.');
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        api_error('Email already registered.');
    }

    $stmt = $pdo->prepare("INSERT INTO employees
        (employee_name, cnic, email, password, company_id, designation, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $employee_name, $cnic, $email,
        password_hash($password, PASSWORD_DEFAULT),
        $company_id, $designation, date('Y-m-d H:i:s'),
    ]);
} catch (PDOException $e) {
    api_error('Could not register employee.', 500, $e);
}

api_respond(['status' => 'success', 'message' => 'Employee registered successfully!']);
