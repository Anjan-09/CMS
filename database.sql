-- =========================================================
-- COMPLAINT MANAGEMENT SYSTEM - Nepal Digital Banking
-- Import this file in phpMyAdmin: Database > Import
-- =========================================================

CREATE DATABASE IF NOT EXISTS `complaint_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `complaint_system`;

-- Users table (customers, bank_staff, bank_admin, super_admin)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(15) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('customer','bank_staff','bank_admin','super_admin') NOT NULL DEFAULT 'customer',
  `bank_id` INT DEFAULT NULL,
  `email_verified` TINYINT(1) DEFAULT 0,
  `otp_code` VARCHAR(6) DEFAULT NULL,
  `otp_expiry` DATETIME DEFAULT NULL,
  `staff_status` ENUM('active','offline') DEFAULT 'offline',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Banks table
CREATE TABLE `banks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `type` ENUM('digital_wallet','bank','payment_gateway') NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `is_verified` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Complaints / Tickets
CREATE TABLE `complaints` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_no` VARCHAR(25) NOT NULL UNIQUE,
  `customer_id` INT NOT NULL,
  `bank_id` INT NOT NULL,
  `assigned_to` INT DEFAULT NULL,
  `subject` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `priority` ENUM('high','medium','low') NOT NULL,
  `status` ENUM('pending','in_progress','resolved','overdue') DEFAULT 'pending',
  `screenshot` VARCHAR(255) DEFAULT NULL,
  `sla_deadline` DATETIME NOT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`bank_id`) REFERENCES `banks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Complaint history / audit trail
CREATE TABLE `complaint_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `complaint_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `note` TEXT NOT NULL,
  `old_status` VARCHAR(30) DEFAULT NULL,
  `new_status` VARCHAR(30) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`complaint_id`) REFERENCES `complaints`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity logs
CREATE TABLE `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `action` VARCHAR(200) NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System settings (theme, SMTP)
CREATE TABLE `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `key_name` VARCHAR(60) NOT NULL UNIQUE,
  `key_value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- DEFAULT DATA
-- =========================================================

-- Super Admin  (password: OneTwo3!)
INSERT INTO `users` (`full_name`,`email`,`phone`,`password`,`role`,`email_verified`,`is_active`) VALUES
('Super Administrator','admin@123.com','9800000000',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'super_admin',1,1);

-- Nepal Banks
INSERT INTO `banks` (`name`,`code`,`type`,`email`,`phone`,`is_verified`) VALUES
('Khalti Digital Wallet','KHALTI','digital_wallet','support@khalti.com','9801000001',1),
('eSewa Money Transfer','ESEWA','digital_wallet','support@esewa.com.np','9801000002',1),
('IME Pay','IMEPAY','digital_wallet','support@imepay.com.np','9801000003',1),
('Prabhu Pay','PRABHUPAY','digital_wallet','support@prabhupay.com','9801000004',1),
('Nepal Bank Limited','NBL','bank','info@nbl.com.np','9801000005',1),
('Rastriya Banijya Bank','RBB','bank','info@rbb.com.np','9801000006',1),
('NIC Asia Bank','NICASIA','bank','info@nicasia.com.np','9801000007',1),
('Global IME Bank','GBIME','bank','info@globalimebank.com','9801000008',1),
('Nabil Bank','NABIL','bank','info@nabilbank.com','9801000009',1),
('Everest Bank','EBL','bank','info@everestbankltd.com','9801000010',1);

-- Default settings
INSERT INTO `settings` (`key_name`,`key_value`) VALUES
('smtp_host','smtp.gmail.com'),
('smtp_port','587'),
('smtp_user',''),
('smtp_pass',''),
('smtp_from','noreply@complaintms.np'),
('smtp_name','Complaint Management System'),
('theme_primary','#1a1a2e'),
('theme_accent','#e94560'),
('theme_card','#16213e'),
('site_name','Complaint Management System');
