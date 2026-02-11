<?php
date_default_timezone_set('Asia/Kolkata');
// api/db.php

// PRODUCTION CONFIGURATION (Passwordless / IAM Auth)
// This configuration relies on the VM's access context (e.g., Cloud SQL Proxy or IAM).

// Option A: If using Cloud SQL Proxy (Unix Socket) - Recommended for Passwordless
// $dsn = "mysql:unix_socket=/cloudsql/PROJECT:REGION:INSTANCE;dbname=res_erp;charset=utf8mb4";

// Option B: If simply using an IP whitelist with an empty password user (User's request)
$host = '10.128.0.3';
$db   = 'res_erp';
$user = 'erp_user';
$pass = 'ResERPDb@2026#!';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    // Log error internally
    error_log("Database Connection Error: " . $e->getMessage());
    echo json_encode(['error' => 'Server Error. Contact Admin.']);
    exit;
}
?>
