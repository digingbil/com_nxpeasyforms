ALTER TABLE `#__nxpeasyforms_forms`
    ADD COLUMN `alias` VARCHAR(255) DEFAULT NULL AFTER `title`;

ALTER TABLE `#__nxpeasyforms_forms`
    ADD UNIQUE KEY `idx_alias` (`alias`);
