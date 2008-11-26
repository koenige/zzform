# 
# Tabellenstruktur für Tabelle `zzform_translations_text`
# 

CREATE TABLE `zzform_translations_text` (
  `translation_id` int(10) unsigned NOT NULL auto_increment,
  `translationfield_id` int(10) unsigned NOT NULL default '0',
  `field_id` int(10) unsigned NOT NULL default '0',
  `translation` text NOT NULL,
  `language_id` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`translation_id`),
  UNIQUE KEY `field_id` (`field_id`,`translationfield_id`,`language_id`)
) ENGINE=MyISAM AUTO_INCREMENT=1  ;

