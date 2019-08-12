#
# table structure for table `_revisions`
# Tabellenstruktur für Tabelle `_revisions`
# 

CREATE TABLE `_revisions` (
  `revision_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `main_table_name` varchar(63) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `main_record_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `rev_status` enum('live','pending','historic') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `created` datetime NOT NULL,
  `script_url` varchar(63) CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`revision_id`),
  KEY `rev_status` (`rev_status`),
  KEY `main_table_name_main_record_id` (`main_table_name`,`main_record_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
