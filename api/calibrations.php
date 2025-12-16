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

} elseif ($method === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = $_POST['id'];
    $date = $_POST['calibration_date'];
    $by = $_POST['calibrated_by'];
    $cert = $_POST['certificate_no'];
    $status = $_POST['pass_fail_status'];
    
    // Handle File Upload
    $upload_sql = "";
    $params = [$date, $by, $cert, $status];
    
    if (isset($_FILES['certificate_file']) && $_FILES['certificate_file']['error'] == 0) {
        $uploadDir = '../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileName = uniqid() . '_' . basename($_FILES['certificate_file']['name']);
        $targetFile = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['certificate_file']['tmp_name'], $targetFile)) {
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
    
    $params[] = $id;
    $sql = "UPDATE calibration_logs SET calibration_date=?, calibrated_by=?, certificate_no=?, pass_fail_status=? $upload_sql WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['message'=>'Updated']);
}
?>
