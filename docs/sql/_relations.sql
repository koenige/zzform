#
# table structure for table `_relations`
# Tabellenstruktur für Tabelle `_relations`
#

CREATE TABLE `_relations` (
  `rel_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `master_db` varchar(127) COLLATE latin1_general_cs NOT NULL DEFAULT '',
  `master_table` varchar(127) COLLATE latin1_general_cs NOT NULL DEFAULT '',
  `master_field` varchar(127) COLLATE latin1_general_cs NOT NULL DEFAULT '',
  `detail_db` varchar(127) COLLATE latin1_general_cs NOT NULL DEFAULT '',
  `detail_table` varchar(127) COLLATE latin1_general_cs NOT NULL DEFAULT '',
  `detail_field` varchar(127) COLLATE latin1_general_cs NOT NULL DEFAULT '',
  `delete` enum('delete','ask','no-delete','update') COLLATE latin1_general_cs NOT NULL DEFAULT 'no-delete',
  `detail_id_field` varchar(127) COLLATE latin1_general_cs NOT NULL DEFAULT '',
  `detail_url` varchar(63) COLLATE latin1_general_cs DEFAULT NULL,
  PRIMARY KEY (`rel_id`),
  UNIQUE KEY `master_db` (`master_db`,`master_table`,`master_field`,`detail_db`,`detail_table`,`detail_field`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_cs;
