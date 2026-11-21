<?php

declare(strict_types=1);

/*
 * Galeriemetadaten-Bundle für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

// Eigene Legende direkt hinter der Bildauswahl der Galerie
PaletteManipulator::create()
    ->addLegend('galeriemetadaten_legend', 'source_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('galeriemetadaten', 'galeriemetadaten_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('gallery', 'tl_content')
;

// Abweichende Metadaten je Bild: [Datei-UUID => [Feld => Wert]]
$GLOBALS['TL_DCA']['tl_content']['fields']['galeriemetadaten'] = [
    'exclude' => true,
    'inputType' => 'galeriemetadaten',
    'eval' => [
        'allowHtml' => true,
        'tl_class' => 'clr',
    ],
    'sql' => 'blob NULL',
];
