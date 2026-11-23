<?php

declare(strict_types=1);

/*
 * Galeriemetadaten-Bundle für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

// Eigener Abschnitt am Ende der Systemeinstellungen
PaletteManipulator::create()
    ->addLegend('galeriemetadaten_legend', null, PaletteManipulator::POSITION_APPEND)
    ->addField('galeriemetadatenMaxBilder', 'galeriemetadaten_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_settings')
;

// Höchstzahl an Bildern, für die das Galerie-Element Eingabefelder zeigt.
// Leer = Voreinstellung 50 (GaleriemetadatenWidget::GRENZE_STANDARD), 0 = ohne Grenze.
// Der Wert landet in der localconfig.php (DC_File), deshalb keine sql-Angabe.
$GLOBALS['TL_DCA']['tl_settings']['fields']['galeriemetadatenMaxBilder'] = [
    'inputType' => 'text',
    'eval' => [
        'rgxp' => 'natural',
        'maxlength' => 5,
        'tl_class' => 'w50',
    ],
];
