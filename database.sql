-- Database: resdigi_db
-- Encoding: utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for instruments
-- ----------------------------
DROP TABLE IF EXISTS `instruments`;
CREATE TABLE `instruments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `asset_tag` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `status` ENUM('Active', 'Due', 'Maintenance') NOT NULL DEFAULT 'Active',
  `next_calibration_date` DATE DEFAULT NULL,
  `last_calibration_date` DATE DEFAULT NULL,
  `location` VARCHAR(100) DEFAULT NULL,
  `frequency_months` INT(11) NOT NULL DEFAULT 12,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_asset_tag` (`asset_tag`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for calibration_logs
-- ----------------------------
DROP TABLE IF EXISTS `calibration_logs`;
CREATE TABLE `calibration_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `instrument_id` INT(11) NOT NULL,
  `calibration_date` DATE NOT NULL,
  `calibrated_by` VARCHAR(100) DEFAULT NULL,
  `certificate_no` VARCHAR(50) DEFAULT NULL,
  `certificate_file` VARCHAR(255) DEFAULT NULL,
  `pass_fail_status` ENUM('Pass', 'Fail') NOT NULL DEFAULT 'Pass',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_instrument_log` FOREIGN KEY (`instrument_id`) REFERENCES `instruments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for audit_trail
-- ----------------------------
DROP TABLE IF EXISTS `audit_trail`;
CREATE TABLE `audit_trail` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `instrument_id` INT(11) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `old_value` TEXT,
  `new_value` TEXT,
  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_instrument_audit` (`instrument_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Triggers
-- ----------------------------
DROP TRIGGER IF EXISTS `instrument_status_audit_trigger`;
DELIMITER ;;
CREATE TRIGGER `instrument_status_audit_trigger` BEFORE UPDATE ON `instruments` FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO audit_trail (instrument_id, action, old_value, new_value, timestamp)
        VALUES (OLD.id, 'STATUS_CHANGE', OLD.status, NEW.status, NOW());
    END IF;
END
;;
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;
