<?php
/**
 * Employee directory. Company (admin) only, scoped to the caller's own company.
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_company();
$company_id = caller_company($claims);

try {
    $stmt = $pdo->prepare("SELECT employee_id, employee_name, cnic, email, designation, created_at
                           FROM employees WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    api_error('Could not load employees.', 500, $e);
}

api_respond(['status' => 'success', 'employees' => $employees]);
