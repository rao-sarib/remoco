<?php
/**
 * Resolve an employee id to a display name (used for "assigned by", uploaders,
 * etc.). Restricted to employees within the caller's own company, so it cannot
 * be used to enumerate names across companies.
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_auth();
$company_id = caller_company($claims);

$employee_id = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
if (!$employee_id) {
    api_error('Missing employee_id.');
}

try {
    $stmt = $pdo->prepare("SELECT employee_name FROM employees
                           WHERE employee_id = ? AND company_id = ?");
    $stmt->execute([$employee_id, $company_id]);
    $name = $stmt->fetchColumn();
} catch (PDOException $e) {
    api_error('Could not resolve name.', 500, $e);
}

if ($name === false) {
    api_error('Employee not found.', 404);
}

api_respond(['status' => 'success', 'employee_name' => $name]);
