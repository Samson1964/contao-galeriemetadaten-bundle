<?php

declare(strict_types=1);

/*
 * Galeriemetadaten-Bundle für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten_legend'] = 'Metadata for this gallery only';
$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten'] = [
    'Custom metadata',
    'Overrides title, alternative text, link, caption and the important part of single images in this gallery only. Empty fields keep the values from the file manager (shown as placeholder), {{empty}} clears a value.',
];
$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten_hinweis'] = 'Click an image to adjust its metadata for this gallery. After changing the image selection, save first to see new images here.';
$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten_keineBilder'] = 'No images selected yet. Please choose images or folders above and save.';
$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten_angepasst'] = 'customised';
$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten_zuViele'] = 'This gallery contains %d images. Custom metadata can only be edited for galleries with at most %d images (limit in the system settings, section "Gallery metadata"). Existing custom metadata for %d images is kept and still applied.';
$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten_wichtigerTeil'] = 'Important part';
$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten_wtZurueck'] = 'Reset important part';
$GLOBALS['TL_LANG']['tl_content']['galeriemetadaten_wtHilfe'] = 'Hold the mouse button and drag a frame around the area that should stay visible when cropping. Dashed: important part from the file manager. Only affects image sizes with cropping.';
