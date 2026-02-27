-- Team chat messages table
CREATE TABLE `team_chat` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bank_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`bank_id`) REFERENCES `banks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);
-- Auto-delete: You can run a scheduled job to delete old messages, or add logic in PHP to delete messages older than X minutes/hours.