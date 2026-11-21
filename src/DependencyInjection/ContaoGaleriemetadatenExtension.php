<?php

declare(strict_types=1);

/*
 * Galeriemetadaten-Bundle für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoGaleriemetadatenBundle\DependencyInjection;

use Contao\CoreBundle\Controller\ContentElement\ImagesController;
use Schachbulle\ContaoGaleriemetadatenBundle\Controller\GalerieController;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * DependencyInjection-Extension des Bundles.
 *
 * Die Basisklasse aus dem HttpKernel wird bewusst verwendet: Sie existiert
 * sowohl in Symfony 5.4 (Contao 4.13) als auch in Symfony 7 (Contao 5.7).
 */
class ContaoGaleriemetadatenExtension extends Extension
{
    /**
     * Registriert unter Contao 5 den eigenen Galerie-Controller.
     *
     * Ab Contao 5 rendert der Fragment-Controller ImagesController die Typen
     * „image“ und „gallery“. Der eigene Controller erbt davon und wird nur für
     * „gallery“ mit höherer Priorität angemeldet; RegisterFragmentsPass lässt
     * je Typ den Dienst mit der höchsten Priorität gewinnen, das Einzelbild
     * bleibt also beim Core.
     *
     * Unter Contao 4.13 gibt es den ImagesController nicht. Dort wird nichts
     * registriert; den Austausch übernimmt die config.php über $GLOBALS['TL_CTE'].
     * Die Prüfung per class_exists() lädt GalerieController selbst nicht, sodass
     * dessen fehlende Elternklasse unter 4.13 nie zum Problem wird.
     *
     * Die Konstruktor-Argumente entsprechen der Dienstdefinition des Cores
     * (core-bundle/config/controller.yaml, Contao 5.7). Der Tag
     * container.service_subscriber ist nötig, weil der Dienst nicht über
     * autoconfigure entsteht: RegisterFragmentsPass fügt setContainer() mit
     * einer Referenz auf Psr\Container\ContainerInterface hinzu, die nur über
     * den Subscriber-Mechanismus zu einem Dienst-Locator aufgelöst wird.
     *
     * @param array<mixed>     $configs   Zusammengeführte Bundle-Konfiguration (hier ungenutzt)
     * @param ContainerBuilder $container Der im Aufbau befindliche Container
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        if (!class_exists(ImagesController::class)) {
            return;
        }

        $definition = new Definition(GalerieController::class, [
            new Reference('security.helper'),
            new Reference('contao.filesystem.virtual.files'),
            new Reference('contao.image.studio'),
            new Reference('contao.pagination.factory'),
            '%contao.image.valid_extensions%',
        ]);

        // Der AbstractController des Cores ist ein ServiceSubscriber; ohne diesen
        // Tag fehlt der Dienst-Locator, den setContainer() erwartet.
        $definition->addTag('container.service_subscriber');

        $definition->addTag('contao.content_element', [
            'type' => 'gallery',
            'category' => 'media',
            'priority' => 10,
        ]);

        $container->setDefinition(GalerieController::class, $definition);
    }
}
