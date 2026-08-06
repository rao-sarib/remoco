<?php
/**
 * Employee login. Public. On success, returns a signed bearer token carrying the
 * employee's id, company, and designation — the basis for all later authorization.
 */
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_error('Invalid request method.', 405);
}

$in = json_body();
$email      = trim($in['email'] ?? '');
$password   = trim($in['password'] ?? '');
$company_id = trim($in['company_id'] ?? '');

if ($email === '' || $password === '' || $company_id === '') {
    api_error('Please fill all required fields.');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ? AND company_id = ?");
    $stmt->execute([$email, $company_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    api_error('Service temporarily unavailable.', 503, $e);
}

if (!$employee || !password_verify($password, $employee['password'])) {
    api_error('Invalid credentials or company ID.', 401);
}

$token = issue_token([
    'sub'         => (int) $employee['employee_id'],
    'type'        => 'employee',
    'company_id'  => $employee['company_id'],
    'designation' => $employee['designation'],
]);

api_respond([
    'status'        => 'success',
    'token'         => $token,
    'employee_id'   => (int) $employee['employee_id'],
    'employee_name' => $employee['employee_name'],
    'email'         => $employee['email'],
    'company_id'    => $employee['company_id'],
    'designation'   => $employee['designation'],
]);
