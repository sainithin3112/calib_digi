<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Origin: *");

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (!isset($_GET['instrument_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Instrument ID Required']);
        exit;
    }
    $id = $_GET['instrument_id'];
    $sql = "SELECT id, calibration_date, calibrated_by, certificate_no, pass_fail_status, certificate_file FROM calibration_logs WHERE instrument_id = ? ORDER BY calibration_date DESC, id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} elseif ($method === 'DELETE') {
    // Delete Log
    $data = json_decode(file_get_contents("php://input"), true);
    if(empty($data['id'])) {
        http_response_code(400); echo json_encode(['error'=>'ID Required']); exit;
    }
    
    // First get the file path to delete it
    $stmt = $pdo->prepare("SELECT certificate_file FROM calibration_logs WHERE id = ?");
    $stmt->execute([$data['id']]);
    $file = $stmt->fetchColumn();
    if($file && file_exists("../$file")) {
        unlink("../$file");
    }

    $stmt = $pdo->prepare("DELETE FROM calibration_logs WHERE id = ?");
    $stmt->execute([$data['id']]);
    echo json_encode(['message'=>'Deleted']);


} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        // --- NEW LOGIC MOVED FROM instruments.php ---
        $instrument_id = $_POST['instrument_id'] ?? null;
        $calibration_date = $_POST['calibration_date'] ?? date('Y-m-d');
        $pass_fail = $_POST['pass_fail_status'] ?? 'Pass';
        $calibrated_by = $_POST['calibrated_by'] ?? '';
        $certificate_no = $_POST['certificate_no'] ?? '';
        $manual_next = $_POST['next_due_date'] ?? null;
        
        if (!$instrument_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Instrument ID is required']);
            exit;
        }

        // --- ROBUST FILE UPLOAD HANDLING ---
        if (!isset($_FILES['certificate_file'])) {
             // Check if POST is empty (likely exceeded post_max_size)
             if (empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
                 http_response_code(400);
                 echo json_encode(['error' => 'Upload failed. File exceeds server post limit (' . ini_get('post_max_size') . ')']);
                 exit;
             }
             http_response_code(400);
             echo json_encode(['error' => 'Certificate file is required']);
             exit;
        }

        $file = $_FILES['certificate_file'];

        // 1. Check for Upload Errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msg = 'Upload error';
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE: $msg = 'File exceeds server limit (' . ini_get('upload_max_filesize') . ')'; break;
                case UPLOAD_ERR_FORM_SIZE: $msg = 'File exceeds form limit'; break;
                case UPLOAD_ERR_PARTIAL: $msg = 'File only partially uploaded'; break;
                case UPLOAD_ERR_NO_FILE: $msg = 'No file uploaded'; break;
                case UPLOAD_ERR_NO_TMP_DIR: $msg = 'Missing temporary folder'; break;
                case UPLOAD_ERR_CANT_WRITE: $msg = 'Failed to write to disk'; break;
                case UPLOAD_ERR_EXTENSION: $msg = 'File upload stopped by extension'; break;
            }
            http_response_code(400);
            echo json_encode(['error' => $msg]);
            exit;
        }

        // 2. Check 10MB Limit (Custom)
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            http_response_code(400);
            echo json_encode(['error' => 'File too large. Maximum allowed size is 10MB.']);
            exit;
        }

        // 3. Process Upload
        $target_dir = "../uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $filename = uniqid() . '_' . basename($file["name"]);
        $target_file = $target_dir . $filename;
        
        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            $db_file_path = "uploads/" . $filename;
            
            try {
                // Insert Log
                $sql = "INSERT INTO calibration_logs (instrument_id, calibration_date, calibrated_by, certificate_no, pass_fail_status, certificate_file) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$instrument_id, $calibration_date, $calibrated_by, $certificate_no, $pass_fail, $db_file_path]);
                
                // Update Parent Instrument
                if ($pass_fail === 'Pass' || $pass_fail === 'Compliant') {
                     // Get frequency
                     $stmtInst = $pdo->prepare("SELECT frequency_months FROM instruments WHERE id = ?");
                     $stmtInst->execute([$instrument_id]);
                     $inst = $stmtInst->fetch();
                     $freq = $inst['frequency_months'] ?? 12; 
                     
                     $next_date = $manual_next ? $manual_next : date('Y-m-d', strtotime($calibration_date . " +$freq months"));
                     
                     $updateSql = "UPDATE instruments SET last_calibration_date = ?, next_calibration_date = ?, status = 'Active' WHERE id = ?";
                     $pdo->prepare($updateSql)->execute([$calibration_date, $next_date, $instrument_id]);
                } else {
                     // Fail/Non-Compliant
                     $updateSql = "UPDATE instruments SET last_calibration_date = ?, next_calibration_date = NULL, status = 'Maintenance' WHERE id = ?";
                     $pdo->prepare($updateSql)->execute([$calibration_date, $instrument_id]);
                }
                
                echo json_encode(['message' => 'Calibration logged successfully']);
            } catch (Exception $e) {
                if(file_exists($target_file)) unlink($target_file); // Clean up
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to move uploaded file']);
        }

    } elseif ($action === 'update') {
    $id = $_POST['id'];
    $date = $_POST['calibration_date'];
    $by = $_POST['calibrated_by'];
    $cert = $_POST['certificate_no'];
    $status = $_POST['pass_fail_status'];
    $manual_next = $_POST['next_due_date'] ?? null;
    
    // Handle File Upload
    $upload_sql = "";
    $params = [$date, $by, $cert, $status];
    
    // --- UPDATED ERROR HANDLING FOR EDIT ---
    if (isset($_FILES['certificate_file'])) {
        $file = $_FILES['certificate_file'];
        if ($file['error'] !== UPLOAD_ERR_OK && $file['error'] !== UPLOAD_ERR_NO_FILE) {
             // ... duplicate error switch or helper function ...
             // For brevity, just generic or strictly check if not NO_FILE
             $msg = 'Upload error code: ' . $file['error'];
             if($file['error'] == UPLOAD_ERR_INI_SIZE) $msg = 'File exceeds server limit';
             http_response_code(400); echo json_encode(['error' => $msg]); exit;
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            http_response_code(400); echo json_encode(['error' => 'File too large (>10MB)']); exit;
        }

        if ($file['error'] == 0) {
            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $fileName = uniqid() . '_' . basename($file['name']);
            $targetFile = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                // Get old file to delete
                $stmt = $pdo->prepare("SELECT certificate_file FROM calibration_logs WHERE id = ?");
                $stmt->execute([$id]);
                $oldFile = $stmt->fetchColumn();
                if($oldFile && file_exists("../$oldFile")) {
                    unlink("../$oldFile");
                }
                
                $upload_sql = ", certificate_file=?";
                $params[] = 'uploads/' . $fileName;
            }
        }
    }
    
    $params[] = $id;
    $sql = "UPDATE calibration_logs SET calibration_date=?, calibrated_by=?, certificate_no=?, pass_fail_status=? $upload_sql WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // --- SYNC INSTRUMENT STATUS ---
    // 1. Get Instrument ID
    $stmt = $pdo->prepare("SELECT instrument_id FROM calibration_logs WHERE id = ?");
    $stmt->execute([$id]);
    $instId = $stmt->fetchColumn();

    if ($instId) {
        // 2. Get Latest Log
        $stmt = $pdo->prepare("SELECT * FROM calibration_logs WHERE instrument_id = ? ORDER BY calibration_date DESC LIMIT 1");
        $stmt->execute([$instId]);
        $latest = $stmt->fetch();

        if ($latest) {
            // 3. Update Instrument
            if ($latest['pass_fail_status'] === 'Compliant' || $latest['pass_fail_status'] === 'Pass') {
                // Get frequency
                $stmtFreq = $pdo->prepare("SELECT frequency_months FROM instruments WHERE id = ?");
                $stmtFreq->execute([$instId]);
                $inst = $stmtFreq->fetch();
                $freq = $inst['frequency_months'] ?? 12;

                $next_date = $manual_next ? $manual_next : date('Y-m-d', strtotime($latest['calibration_date'] . " +$freq months"));
                $upd = "UPDATE instruments SET last_calibration_date = ?, next_calibration_date = ?, status = 'Active' WHERE id = ?";
                $pdo->prepare($upd)->execute([$latest['calibration_date'], $next_date, $instId]);
            } else {
                // Fail (Non-Compliant)
                $upd = "UPDATE instruments SET last_calibration_date = ?, next_calibration_date = NULL, status = 'Maintenance' WHERE id = ?";
                $pdo->prepare($upd)->execute([$latest['calibration_date'], $instId]);
            }
        }
    }
    
    echo json_encode(['message'=>'Updated']);
    }
}

?>
