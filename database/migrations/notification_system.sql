-- Notification System Database Structure
-- This file contains SQL to create the necessary tables for the notification system

-- Create notifications table if it doesn't exist
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `from_user_id` int(11) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `from_user_id` (`from_user_id`),
  KEY `type_index` (`type`),
  KEY `is_read_index` (`is_read`),
  KEY `created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create user_settings table if it doesn't exist
CREATE TABLE IF NOT EXISTS `user_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `settings` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add foreign key constraints (if the tables already exist)
ALTER TABLE `notifications` 
  ADD CONSTRAINT `notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_from_user_fk` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
  
ALTER TABLE `user_settings` 
  ADD CONSTRAINT `user_settings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Add is_read column to messages table if it doesn't exist
ALTER TABLE `messages` ADD COLUMN IF NOT EXISTS 
  `is_read` tinyint(1) NOT NULL DEFAULT 0 AFTER `message`;

-- Add is_read column to forum_replies table if it doesn't exist
ALTER TABLE `forum_replies` ADD COLUMN IF NOT EXISTS 
  `is_read` tinyint(1) NOT NULL DEFAULT 0;
  
-- Create sample notification types for the system
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('notification_types', '["friend_request","message","forum_response","listing_comment","system"]', 'Available notification types in the system');