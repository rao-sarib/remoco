<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_unset();              // Remove all session variables
session_destroy();            // Destroy the session
header("Location: login.php"); // Redirect to login page
exit;
?>