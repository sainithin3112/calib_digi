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
    // Check if it's a calibration event (File Upload)
    if (isset($_FILES['certificate_file'])) {
        // 3. POST Request (Multipart): Handle "Calibration Event"
        
        $instrument_id = $_POST['instrument_id'] ?? null;
        $calibration_date = $_POST['calibration_date'] ?? date('Y-m-d');
        $pass_fail = $_POST['pass_fail_status'] ?? 'Pass';
        $calibrated_by = $_POST['calibrated_by'] ?? '';
        $certificate_no = $_POST['certificate_no'] ?? '';
        
        if (!$instrument_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Instrument ID is required']);
            exit;
        }

        // Handle File Upload
        $target_dir = "../uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $filename = uniqid() . '_' . basename($_FILES["certificate_file"]["name"]);
        $target_file = $target_dir . $filename;
        
        if (move_uploaded_file($_FILES["certificate_file"]["tmp_name"], $target_file)) {
            $db_file_path = "uploads/" . $filename;
            
            try {
                // Insert Log
                $sql = "INSERT INTO calibration_logs (instrument_id, calibration_date, calibrated_by, certificate_no, pass_fail_status, certificate_file) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$instrument_id, $calibration_date, $calibrated_by, $certificate_no, $pass_fail, $db_file_path]);
                
                // Update Parent Instrument
                if ($pass_fail === 'Pass') {
                    // Get frequency
                    $stmtInst = $pdo->prepare("SELECT frequency_months FROM instruments WHERE id = ?");
                    $stmtInst->execute([$instrument_id]);
                    $inst = $stmtInst->fetch();
                    $freq = $inst['frequency_months'] ?? 12; 
                    
                    $next_date = date('Y-m-d', strtotime($calibration_date . " +$freq months"));
                    
                    $updateSql = "UPDATE instruments SET last_calibration_date = ?, next_calibration_date = ?, status = 'Active' WHERE id = ?";
                    $pdo->prepare($updateSql)->execute([$calibration_date, $next_date, $instrument_id]);
                }
                
                echo json_encode(['message' => 'Calibration logged successfully', 'next_due' => $next_date ?? null]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to upload certificate']);
        }

    } else {
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
