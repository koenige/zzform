# 
# Tabellenstruktur für Tabelle `zzform_translationfields`
# 

CREATE TABLE `zzform_translationfields` (
  `translationfield_id` int(10) unsigned NOT NULL auto_increment,
  `db_name` varchar(255) NOT NULL default '',
  `table_name` varchar(255) NOT NULL default '',
  `field_name` varchar(255) NOT NULL default '',
  `field_type` enum('varchar','text')  NOT NULL default 'varchar',
  PRIMARY KEY  (`translationfield_id`),
  UNIQUE KEY `db_name` (`db_name`,`table_name`,`field_name`)
) ENGINE=MyISAM AUTO_INCREMENT=1 ;

