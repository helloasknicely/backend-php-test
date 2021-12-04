ALTER TABLE `todos`
ADD COLUMN `status` enum('PROGRESS','COMPLETE')  NOT NULL DEFAULT 'PROGRESS' AFTER `description`;

ALTER TABLE `todos`
ADD INDEX `status` (`status`);