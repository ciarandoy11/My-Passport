CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(6) UNSIGNED NOT NULL,
    `customer_id` INT(6) UNSIGNED NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `quantity` INT NOT NULL,
    `total` DECIMAL(10,2) NOT NULL,
    `due_date` DATE NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL,
    `bank_details_id` INT(6) UNSIGNED NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`bank_details_id`) REFERENCES `bank_details`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 