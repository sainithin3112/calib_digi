<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'db.php';

if (!isset($_GET['tag'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Equipment ID Required']);
    exit;
}

$tag = $_GET['tag'];

$sql = "
    SELECT i.id, i.asset_tag, i.name, i.status, i.next_calibration_date, i.last_calibration_date, i.location, 
    (SELECT certificate_file FROM calibration_logs cl WHERE cl.instrument_id = i.id ORDER BY cl.calibration_date DESC, cl.id DESC LIMIT 1) as latest_cert,
    (SELECT calibrated_by FROM calibration_logs cl WHERE cl.instrument_id = i.id ORDER BY cl.calibration_date DESC, cl.id DESC LIMIT 1) as latest_cal_by,
    (SELECT certificate_no FROM calibration_logs cl WHERE cl.instrument_id = i.id ORDER BY cl.calibration_date DESC, cl.id DESC LIMIT 1) as latest_cert_no
    FROM instruments i 
    WHERE i.asset_tag = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$tag]);
$instrument = $stmt->fetch();

if (!$instrument) {
    http_response_code(404);
    echo json_encode(['error' => 'Equipment Not Found']);
    exit;
}

// Calculate generic status for public view
$is_valid = true;
$today = date('Y-m-d');
if ($instrument['next_calibration_date'] && $instrument['next_calibration_date'] < $today) {
    $is_valid = false;
}
if ($instrument['status'] !== 'Active') {
    $is_valid = false;
}

$response = [
    'asset_tag' => $instrument['asset_tag'],
    'name' => $instrument['name'],
    'status' => $instrument['status'],
    'location' => $instrument['location'],
    'valid_calibration' => $is_valid,
    'next_due' => $instrument['next_calibration_date'],
    'last_cal_date' => $instrument['last_calibration_date'],
    'latest_cert' => $instrument['latest_cert'],
    'calibrated_by' => $instrument['latest_cal_by'],
    'certificate_no' => $instrument['latest_cert_no'],
    'last_updated' => date('Y-m-d H:i:s')
];

echo json_encode($response);
?>
