-- Coda di creazione progetti (produzione: li crea il cron bin/provision-queue.php).
ALTER TABLE `projects`
  ADD COLUMN `provisioning_status` VARCHAR(20) NOT NULL DEFAULT 'ready' AFTER `with_demo_data`,
  ADD COLUMN `provisioning_log` TEXT NULL AFTER `provisioning_status`;
