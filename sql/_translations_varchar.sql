#
# Tabellenstruktur für Tabelle `zzform_translations_varchar`
# 

CREATE TABLE `zzform_translations_varchar` (
  `translation_id` int(10) unsigned NOT NULL auto_increment,
  `translationfield_id` int(10) unsigned NOT NULL default '0',
  `field_id` int(10) unsigned NOT NULL default '0',
  `translation` varchar(255) NOT NULL default '',
  `language_id` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`translation_id`),
  UNIQUE KEY `field_id` (`field_id`,`translationfield_id`,`language_id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 ;

