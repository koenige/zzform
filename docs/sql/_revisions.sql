#
# table structure for table `_revisions`
# Tabellenstruktur für Tabelle `_revisions`
# 

CREATE TABLE `_revisions` (
  `revision_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `main_table_name` varchar(63) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `rev_status` enum('live','pending','historic') NOT NULL,
  `created` datetime NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`revision_id`),
  KEY `main_table_name` (`main_table_name`),
  KEY `rev_status` (`rev_status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
