ALTER TABLE `media_assets` ADD UNIQUE KEY `uq_media_assets_source_external` (`source`, `external_id`);
ALTER TABLE `media_assets` ADD KEY `ix_media_assets_status` (`analysis_status`);
