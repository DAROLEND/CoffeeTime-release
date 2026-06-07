-- Add customer_email to orders
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `customer_email` varchar(180) DEFAULT NULL AFTER `customer_surname`;

-- Scheduled reminders table
CREATE TABLE IF NOT EXISTS `order_reminders` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `order_id`    int(11)      NOT NULL,
  `type`        enum('email_customer','telegram_admin') NOT NULL,
  `send_at`     datetime     NOT NULL,
  `sent_at`     datetime     DEFAULT NULL,
  `status`      enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `fail_reason` varchar(255) DEFAULT NULL,
  `created_at`  datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pending` (`status`, `send_at`),
  CONSTRAINT `fk_reminders_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
