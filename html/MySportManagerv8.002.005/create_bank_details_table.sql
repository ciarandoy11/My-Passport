-- First, check if the users table exists and has the correct structure
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(6) UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `type-admin` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Then create the bank_details table
CREATE TABLE IF NOT EXISTS `bank_details` (
    `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(6) UNSIGNED NOT NULL,
    `account_name` VARCHAR(255) NOT NULL,
    `account_number` VARCHAR(50) NOT NULL,
    `sort_code` VARCHAR(20) NOT NULL,
    `bank_name` VARCHAR(255) NOT NULL,
    `is_default` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 