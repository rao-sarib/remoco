<?php
/**
 * Company (admin) login. Public — this is how a token is obtained.
 * On success, returns a signed bearer token the client sends on every later call.
 */
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_error('Invalid request method.', 405);
}

$in = json_body();
$company_id = trim($in['company_id'] ?? '');
$password   = trim($in['password'] ?? '');

if ($company_id === '' || $password === '') {
    api_error('Please fill all required fields.');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    api_error('Service temporarily unavailable.', 503, $e);
}

if (!$company || !password_verify($password, $company['password'])) {
    api_error('Invalid company ID or password.', 401);
}

$token = issue_token([
    'sub'        => $company['company_id'],
    'type'       => 'company',
    'company_id' => $company['company_id'],
]);

api_respond([
    'status'       => 'success',
    'token'        => $token,
    'company_id'   => $company['company_id'],
    'company_name' => $company['company_name'],
]);
