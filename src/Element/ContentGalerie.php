<?php

declare(strict_types=1);

/*
 * Galeriemetadaten-Bundle für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoGaleriemetadatenBundle\Element;

use Contao\ContentGallery;
use Schachbulle\ContaoGaleriemetadatenBundle\Galerie\Ueberschreiber;

/**
 * Galerie-Element für Contao 4.13 mit abweichenden Metadaten.
 *
 * Erbt vollständig vom Core-Element und klammert nur dessen Ausgabe ein. Die
 * Klasse wird ausschließlich unter Contao 4.13 verwendet (Austausch in der
 * config.php); Contao 5 kennt ContentGallery nicht mehr und lädt diese Datei
 * deshalb nie.
 */
class ContentGalerie extends ContentGallery
{
    /**
     * Gibt die Galerie aus und verwendet dabei die abweichenden Metadaten.
     *
     * Die Abweichungen werden vor dem Aufruf des Cores in das Modell-Register
     * eingespielt und danach in jedem Fall – auch bei einer Ausnahme, etwa der
     * PageNotFoundException einer ungültigen Seitenzahl – wieder entfernt.
     *
     * @return string Das HTML der Galerie oder ein Leerstring ohne Bilder
     */
    public function generate()
    {
        $ueberschreiber = new Ueberschreiber();
        $ueberschreiber->anwenden($this->galeriemetadaten);

        try {
            return parent::generate();
        } finally {
            $ueberschreiber->wiederherstellen();
        }
    }
}
