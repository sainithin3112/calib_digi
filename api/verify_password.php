<?php
// api/verify_password.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Origin: *");

require_once 'db.php';

$data = json_decode(file_get_contents("php://input"), true);
$username = $_SESSION['user_name'] ?? $data['username'] ?? ''; // Prefer session user
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['valid' => false, 'error' => 'Missing credentials']);
    exit;
}

$stmt = $pdo->prepare("SELECT pass FROM users WHERE employee_id = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['pass'])) {
    echo json_encode(['valid' => true]);
} else {
    echo json_encode(['valid' => false]);
}
?>
