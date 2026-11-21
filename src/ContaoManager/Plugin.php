<?php

declare(strict_types=1);

/*
 * Galeriemetadaten-Bundle für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoGaleriemetadatenBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Schachbulle\ContaoGaleriemetadatenBundle\ContaoGaleriemetadatenBundle;

/**
 * Meldet das Bundle beim Contao Manager an.
 */
class Plugin implements BundlePluginInterface
{
    /**
     * Liefert die Bundle-Konfiguration für den Contao Manager.
     *
     * Das Bundle wird nach dem Core-Bundle geladen. Das ist hier zwingend:
     * Die config.php tauscht unter Contao 4.13 die Klasse des Galerie-Elements
     * in $GLOBALS['TL_CTE'] aus und muss den Eintrag des Cores dafür schon
     * vorfinden; ebenso erweitert die DCA-Datei die Galerie-Palette.
     *
     * @param ParserInterface $parser Vom Manager übergebener Parser (hier ungenutzt)
     *
     * @return array<BundleConfig> Genau ein Eintrag für dieses Bundle
     */
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(ContaoGaleriemetadatenBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
}
