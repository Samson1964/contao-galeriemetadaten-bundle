<?php

declare(strict_types=1);

/*
 * Galeriemetadaten-Bundle für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

use Schachbulle\ContaoGaleriemetadatenBundle\Element\ContentGalerie;
use Schachbulle\ContaoGaleriemetadatenBundle\Widget\GaleriemetadatenWidget;

// Backend-Widget für die abweichenden Metadaten
$GLOBALS['BE_FFL']['galeriemetadaten'] = GaleriemetadatenWidget::class;

// Contao 4.13: Das Galerie-Element ist dort noch eine klassische Klasse.
// Ausgetauscht wird nur, wenn noch die Core-Klasse eingetragen ist – hat ein
// anderes Bundle die Galerie bereits ersetzt, bleibt dessen Eintrag stehen.
// Unter Contao 5 existiert ContentGallery nicht; dort übernimmt der
// GalerieController (siehe ContaoGaleriemetadatenExtension).
if (class_exists(Contao\ContentGallery::class) && ($GLOBALS['TL_CTE']['media']['gallery'] ?? null) === Contao\ContentGallery::class) {
    $GLOBALS['TL_CTE']['media']['gallery'] = ContentGalerie::class;
}
