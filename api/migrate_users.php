<?php
// api/migrate_users.php
require_once 'db.php';

$sql = "
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($sql);
    echo "Users table created successfully.\n";

    // Create default admin if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pass = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password, role) VALUES ('admin', '$pass', 'admin')");
        echo "Default admin user created (admin / admin123).\n";
    }

    // Create default normal user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'user'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pass = password_hash('user123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password, role) VALUES ('user', '$pass', 'user')");
        echo "Default normal user created (user / user123).\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
