<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // 1. GET Request: Fetch all instruments
    $sql = "SELECT * FROM instruments";
    $params = [];
    
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $sql .= " WHERE status = ?";
        $params[] = $_GET['status'];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $instruments = $stmt->fetchAll();
    
    $today = date('Y-m-d');
    
    // Dynamic "Overdue" Flagging
    foreach ($instruments as &$inst) {
        $inst['is_overdue'] = false;
        if ($inst['next_calibration_date'] && $inst['next_calibration_date'] < $today) {
            $inst['is_overdue'] = true;
        }
    }
    
    echo json_encode($instruments);

} elseif ($method === 'POST') {
    // 2. POST Request: Add new instrument (JSON Input)
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data) {
            $data = $_POST;
    }

    if (empty($data['asset_tag']) || empty($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    $sql = "INSERT INTO instruments (asset_tag, name, status, next_calibration_date, location, frequency_months) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    try {
        $stmt->execute([
            $data['asset_tag'],
            $data['name'],
            $data['status'] ?? 'Active',
            $data['next_calibration_date'] ?? null,
            $data['location'] ?? '',
            $data['frequency_months'] ?? 12
        ]);
        echo json_encode(['message' => 'Instrument created', 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
} elseif ($method === 'PUT') {
    // 4. PUT Request: Update Instrument Details
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID required']);
        exit;
    }

    $allowed_fields = ['name', 'location', 'status', 'frequency_months', 'next_calibration_date'];
    $updates = [];
    $params = [];

    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    if (empty($updates)) {
        echo json_encode(['message' => 'No changes provided']);
        exit;
    }

    $params[] = $data['id']; // For WHERE clause
    $sql = "UPDATE instruments SET " . implode(', ', $updates) . " WHERE id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['message' => 'Instrument updated successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }

} elseif ($method === 'DELETE') {
    // 5. DELETE Request
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM instruments WHERE id = ?");
        $stmt->execute([$data['id']]);
        echo json_encode(['message' => 'Instrument deleted successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
