<?php
/**
 * Team Leads in the caller's company, for the create-task assignment dropdown.
 * Any authenticated member of the company may read it; scoped by the token.
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_auth();
$company_id = caller_company($claims);

try {
    $stmt = $pdo->prepare("SELECT employee_id, employee_name
                           FROM employees
                           WHERE designation = 'Team Lead' AND company_id = ?");
    $stmt->execute([$company_id]);
    $teamLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    api_error('Could not load team leads.', 500, $e);
}

api_respond(['status' => 'success', 'team_leads' => $teamLeads]);
