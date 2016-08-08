# 
# table structure for table `_translationfields`
# Tabellenstruktur für Tabelle `_translationfields`
# 

CREATE TABLE `_translationfields` (
  `translationfield_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `db_name` varchar(255) COLLATE latin1_general_cs NOT NULL DEFAULT '',
  `table_name` varchar(255) COLLATE latin1_general_cs NOT NULL DEFAULT '',
  `field_name` varchar(255) COLLATE latin1_general_cs NOT NULL DEFAULT '',
  `field_type` enum('varchar','text') COLLATE latin1_general_cs NOT NULL DEFAULT 'varchar',
  PRIMARY KEY (`translationfield_id`),
  UNIQUE KEY `db_name` (`db_name`,`table_name`,`field_name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_cs;
