#
# table structure for table `_revisiondata`
# Tabellenstruktur für Tabelle `_revisiondata`
# 

CREATE TABLE `_revisiondata` (
  `revisiondata_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `revision_id` int(10) unsigned NOT NULL,
  `table_name` varchar(63) NOT NULL,
  `record_id` int(10) unsigned NOT NULL,
  `changed_values` text NOT NULL,
  `complete_values` text NOT NULL,
  `rev_action` enum('insert','update','delete') NOT NULL,
  PRIMARY KEY (`revisiondata_id`),
  KEY `revision_id` (`revision_id`),
  KEY `table_name` (`table_name`),
  KEY `record_id` (`record_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
