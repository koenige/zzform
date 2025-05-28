<!--
# zzform
# help: advanced search tips, DE
#
# Part of »Zugzwang Project«
# https://www.zugzwang.org/modules/zzform
#
# @author Gustaf Mossakowski <gustaf@koenige.org>
# @copyright Copyright © 2025 Gustaf Mossakowski
# @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
#
-->

# Erweiterte Suche für Formulare

Verwenden Sie diese Tipps, um Ihre Suche unter Formularen zu verfeinern
und genauere Ergebnisse zu erhalten.

## So funktioniert die Suche:

Standardmäßig werden Ihre Suchbegriffe **an einer beliebigen Stelle im
Text** abgeglichen. Das bedeutet, wenn Sie nach: 

`Kuchen`

suchen, wird alles zurückgegeben, was das Wort „Kuchen“ enthält, wie
„Kuchenbacken“ oder „Apfelkuchen“.

## Spezielle Operatoren

Sie können Ihre Suche mit Sonderzeichen oder Mustern verfeinern, die
**vor oder nach Ihren Suchbegriffen** platziert werden. In diesem Text
steht `<SP>` für ein Leerzeichen.

Operator              | Bedeutung
--------------------- | ------------------------------------------------
`<SP>Suchbegriff<SP>` | Genaue Übereinstimmung – Einträge, bei denen die vollständige Phrase übereinstimmt
`= Suchbegriff`       | Dasselbe wie oben (genaue Übereinstimmung)
`<SP>Suchbegriff`     | Findet Einträge, die **mit** dem Begriff beginnen
`Suchbegriff<SP>`     | Findet Einträge, die **mit** dem Begriff enden
`! Suchbegriff`       | Schließt Einträge aus, die diesen Begriff enthalten 
`- Suche1 Suche2`     | Sucht Einträge **zwischen** den beiden Werten

## Feldspezifische (bereichsbezogene) Suche

Beim Suchen innerhalb eines bestimmten Felds (z. B. einer Zahlen- oder
Datumsspalte) können Sie Vergleichsoperatoren verwenden:

Operator         | Bedeutung
---------------- | -----------------------------------------------------
`> Wert`         | Größer als
`< Wert`         | Kleiner als
`>= Wert`        | Größer als oder gleich
`<= Wert`        | Kleiner als oder gleich
`NULL`           | Sucht Einträge ohne Wert in diesem Feld
`!NULL`          | Sucht Einträge mit einem Wert in diesem Feld

## Datumssuche

Sie können sowohl nach **lokalisierten** als auch nach
**internationalen** Datumsformaten suchen. Beispiele:


Suchbegriff      | Bedeutung
---------------- | -----------------------------------------------------
`03/2025`        | Einträge im **März 2025**
`Q1/2025`        | Einträge im **ersten Quartal** 2025 (Januar–März)
`> 01.01.2023`   | Termine nach dem 1. Januar 2023
`< 15.11.2023`   | Termine vor dem 11. November 2023
`<= 2024-12-31`  | Termine bis einschließlich 31. Dezember 2024
