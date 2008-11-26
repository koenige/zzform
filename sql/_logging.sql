#
#  Tabellenstruktur für Tabelle `_logging`
# 

CREATE TABLE `_logging` (
  `log_id` int(10) unsigned NOT NULL auto_increment,
  `query` text NOT NULL,
  `record_id` int(10) unsigned NOT NULL default '0',
  `user` varchar(255) default NULL,
  `last_update` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`log_id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 ;
