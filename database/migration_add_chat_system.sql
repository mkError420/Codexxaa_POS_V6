-- Migration for Live Chat System
-- This migration adds tables for live chat functionality between home page visitors and super admin

-- Table for chat conversations/sessions
CREATE TABLE IF NOT EXISTS `chat_sessions` (
  `id` INT AUTO_INCREMENT,
  `visitor_name` VARCHAR(100) NOT NULL,
  `visitor_email` VARCHAR(100) NULL,
  `visitor_phone` VARCHAR(20) NULL,
  `status` ENUM('active', 'closed', 'archived') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_chat_sessions_status` (`status`),
  INDEX `idx_chat_sessions_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for chat messages
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` INT AUTO_INCREMENT,
  `session_id` INT NOT NULL,
  `sender_type` ENUM('visitor', 'admin') NOT NULL,
  `sender_name` VARCHAR(100) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_chat_messages_session` (`session_id`),
  INDEX `idx_chat_messages_created` (`created_at`),
  CONSTRAINT `fk_chat_messages_session`
    FOREIGN KEY (`session_id`)
    REFERENCES `chat_sessions` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
