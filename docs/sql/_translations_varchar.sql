#
# table structure for table `_translations_varchar`
# Tabellenstruktur für Tabelle `_translations_varchar`
# 

CREATE TABLE `_translations_varchar` (
  `translation_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `translationfield_id` int(10) unsigned NOT NULL DEFAULT '0',
  `field_id` int(10) unsigned NOT NULL DEFAULT '0',
  `translation` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `language_id` int(10) unsigned NOT NULL DEFAULT '0',
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`translation_id`),
  UNIQUE KEY `field_id` (`field_id`,`translationfield_id`,`language_id`),
  KEY `translationfield_id` (`translationfield_id`),
  KEY `language_id` (`language_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
