<?php
/**
 * Company/Admin dashboard counts. Company only, scoped to the caller's company.
 */
require_once __DIR__ . '/_bootstrap.php';

$claims = require_company();
$company_id = caller_company($claims);

try {
    $stmt = $pdo->prepare("SELECT
            COUNT(*) AS total_employees,
            SUM(designation = 'Project Manager') AS project_managers,
            SUM(designation = 'Team Lead')       AS team_leads
        FROM employees WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    api_error('Could not load dashboard.', 500, $e);
}

api_respond([
    'total_employees'  => (int) $r['total_employees'],
    'project_managers' => (int) $r['project_managers'],
    'team_leads'       => (int) $r['team_leads'],
]);
