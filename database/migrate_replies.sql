-- Migration: Update page_replies table to match API column expectations
-- Run this against your production database if the table already exists
-- with old column names (reply_text, reply_file, sender_name)

-- Step 1: Rename existing columns to match API code
ALTER TABLE `page_replies`
  CHANGE COLUMN `sender_name` `visitor_name` VARCHAR(100) NULL,
  CHANGE COLUMN `reply_text` `message` TEXT NULL,
  CHANGE COLUMN `reply_file` `voice_path` VARCHAR(255) NULL;

-- Step 2: Add missing columns
ALTER TABLE `page_replies`
  ADD COLUMN `image_path` VARCHAR(255) NULL AFTER `voice_path`,
  ADD COLUMN `video_path` VARCHAR(255) NULL AFTER `image_path`,
  ADD COLUMN `is_read` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ip`;

-- Step 3: Set default for reply_type
ALTER TABLE `page_replies`
  ALTER COLUMN `reply_type` SET DEFAULT 'text';
