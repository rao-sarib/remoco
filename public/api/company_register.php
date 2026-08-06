<?php
/**
 * Company self-registration. Public — a company creates its own account.
 * Preserves the original validation and response shape.
 */
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_error('Invalid request.', 405);
}

$in = json_body();
$company_id     = trim($in['company_id'] ?? '');
$company_name   = trim($in['company_name'] ?? '');
$is_registered  = isset($in['is_registered']) ? (int) $in['is_registered'] : 0;
$company_ntn    = ($is_registered === 1) ? trim($in['company_ntn'] ?? '') : null;
$company_sector = trim($in['company_sector'] ?? '');
$email          = trim($in['email'] ?? '');
$password       = trim($in['password'] ?? '');
$confirm        = trim($in['confirm_password'] ?? '');

$errors = [];

if ($company_id === '') {
    $errors['company_id'] = 'Company ID is required';
} elseif (!preg_match('/^[A-Z]{3}\d{3}$/', $company_id)) {
    $errors['company_id'] = 'Company ID must be 3 uppercase letters followed by 3 numbers (e.g., ABC123)';
} else {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE company_id = ?");
        $stmt->execute([$company_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors['company_id'] = 'Company ID already exists - please choose a different ID';
        }
    } catch (PDOException $e) {
        $errors['company_id'] = 'Database error checking company ID';
    }
}

if ($company_name === '')   $errors['company_name'] = 'Company name is required';
if ($is_registered === 1 && ($company_ntn === '' || $company_ntn === null)) {
    $errors['company_ntn'] = 'NTN is required for registered companies';
}
if ($company_sector === '') $errors['company_sector'] = 'Company sector is required';

if ($email === '') {
    $errors['email'] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Invalid email format';
} else {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = 'Email already registered';
        }
    } catch (PDOException $e) {
        $errors['email'] = 'Database error checking email';
    }
}

if ($password === '') {
    $errors['password'] = 'Password is required';
} elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters';
}
if ($password !== $confirm) {
    $errors['confirm_password'] = 'Passwords do not match';
}

if ($errors) {
    api_respond(['status' => 'error', 'message' => 'Validation errors', 'errors' => $errors]);
}

try {
    $stmt = $pdo->prepare("INSERT INTO companies
        (company_id, company_name, is_registered, company_ntn, company_sector, email, password)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $company_id, $company_name, $is_registered, $company_ntn,
        $company_sector, $email, password_hash($password, PASSWORD_DEFAULT),
    ]);
} catch (PDOException $e) {
    api_error('Could not complete registration.', 500, $e);
}

api_respond([
    'status'  => 'success',
    'message' => "Company registered successfully! Company ID: $company_id. Please check your email for details.",
]);
