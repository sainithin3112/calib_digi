<?php
// api/login.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Origin: *");

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and Password required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Successful Login
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        
        echo json_encode([
            'message' => 'Login Successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid Credentials']);
    }
}
?>
