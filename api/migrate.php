<?php
// api/migrate_step1.php
require_once 'db.php';

try {
    $pdo->exec("ALTER TABLE instruments ADD COLUMN last_calibration_date DATE DEFAULT NULL AFTER next_calibration_date");
    echo "Added last_calibration_date to instruments.\n";
} catch (Exception $e) { /* Ignore if exists */ echo "Column last_calibration_date might already exist.\n"; }

try {
    $pdo->exec("ALTER TABLE calibration_logs ADD COLUMN calibrated_by VARCHAR(100) DEFAULT NULL AFTER calibration_date");
    echo "Added calibrated_by to calibration_logs.\n";
} catch (Exception $e) { /* Ignore */ echo "Column calibrated_by might already exist.\n"; }

try {
    $pdo->exec("ALTER TABLE calibration_logs ADD COLUMN certificate_no VARCHAR(50) DEFAULT NULL AFTER calibrated_by");
    echo "Added certificate_no to calibration_logs.\n";
} catch (Exception $e) { /* Ignore */ echo "Column certificate_no might already exist.\n"; }

echo "Migration Complete.";
?>
