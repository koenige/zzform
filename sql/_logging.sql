-- 
-- Tabellenstruktur für Tabelle `_logging`
-- 

CREATE TABLE `_logging` (
  `log_id` int(10) unsigned NOT NULL auto_increment,
  `query` text NOT NULL,
  `user` varchar(255) default NULL,
  `last_update` timestamp NULL,
  PRIMARY KEY  (`log_id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 ;
