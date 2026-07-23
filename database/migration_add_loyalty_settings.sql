-- Migration: Add loyalty program settings to shops table
-- This adds columns for loyalty program configuration

USE `if0_42451104_codexaa`;

-- Add loyalty settings columns to shops table
ALTER TABLE `shops`
ADD COLUMN `loyalty_enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether loyalty program is enabled (0=disabled, 1=enabled)',
ADD COLUMN `loyalty_point_earn_rate` DECIMAL(10,2) NOT NULL DEFAULT 100.00 COMMENT 'Amount of purchase required to earn 1 loyalty point',
ADD COLUMN `loyalty_point_value` DECIMAL(10,2) NOT NULL DEFAULT 1.00 COMMENT 'Monetary value of 1 loyalty point';
