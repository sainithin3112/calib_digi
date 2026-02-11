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

    // Server DB Schema Mapping
    // username -> employee_id
    // password -> pass
    // role -> joined from roles table (role_key)
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.employee_id, u.employee_name, u.pass, r.role_key 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.employee_id = ? AND u.status = 1 AND u.is_deleted = 0
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['pass'])) {
        // Successful Login
        $_SESSION['user_id'] = $user['id'];
        // Map 'admin' role_key to 'admin', others to 'user' (or keep specific)
        $_SESSION['role'] = ($user['role_key'] === 'admin') ? 'admin' : 'user';
        $_SESSION['user_name'] = $user['employee_id']; // For verify_password
        
        echo json_encode([
            'message' => 'Login Successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['employee_id'],
                'name' => $user['employee_name'],
                'role' => $_SESSION['role']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid Credentials']);
    }
}
?>
