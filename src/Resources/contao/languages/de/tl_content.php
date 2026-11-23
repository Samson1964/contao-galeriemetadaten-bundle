<?php

declare(strict_types=1);

/*
 * Galeriemetadaten-Bundle für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten_legend'] = 'Metadaten nur für diese Galerie';
$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten'] = [
    'Abweichende Metadaten',
    'Überschreibt Titel, Alternativtext, Link, Bildunterschrift und den wichtigen Teil einzelner Bilder nur in dieser Galerie. Leere Felder übernehmen die Angaben der Dateiverwaltung (grau als Platzhalter), {{empty}} leert einen Wert.',
];
$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten_hinweis'] = 'Ein Bild anklicken, um seine Metadaten für diese Galerie anzupassen. Nach einer Änderung der Bildauswahl erst speichern, dann erscheinen neue Bilder hier.';
$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten_keineBilder'] = 'Noch keine Bilder ausgewählt. Bitte oben Bilder oder Ordner wählen und speichern.';
$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten_angepasst'] = 'angepasst';
$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten_zuViele'] = 'Diese Galerie enthält %d Bilder. Abweichende Metadaten lassen sich nur für Galerien mit höchstens %d Bildern bearbeiten (Grenze in den Systemeinstellungen, Abschnitt „Galerie-Metadaten“). Bereits gespeicherte Abweichungen für %d Bilder bleiben erhalten und werden weiter ausgegeben.';
$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten_wichtigerTeil'] = 'Wichtiger Teil';
$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten_wtZurueck'] = 'Wichtigen Teil zurücksetzen';
$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten_wtHilfe'] = 'Mit gedrückter Maustaste einen Rahmen um den Bereich ziehen, der beim Beschneiden sichtbar bleiben soll. Gestrichelt: wichtiger Teil aus der Dateiverwaltung. Wirkt nur bei Bildgrößen mit Beschnitt.';
