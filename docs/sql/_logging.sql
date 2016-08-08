#
# table structure for table `_logging`
#  Tabellenstruktur für Tabelle `_logging`
# 

CREATE TABLE `_logging` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `query` text COLLATE utf8_unicode_ci NOT NULL,
  `record_id` int(10) unsigned DEFAULT NULL,
  `user` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs DEFAULT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
