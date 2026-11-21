<?php

declare(strict_types=1);

/*
 * Galeriemetadaten-Bundle für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoGaleriemetadatenBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Hauptklasse des Bundles.
 *
 * Abweichende Metadaten für Bilder eines Galerie-Elements: Sie werden am
 * Inhaltselement gespeichert und nur beim Ausgeben dieser einen Galerie
 * anstelle der Angaben aus der Dateiverwaltung verwendet.
 */
class ContaoGaleriemetadatenBundle extends Bundle
{
}
