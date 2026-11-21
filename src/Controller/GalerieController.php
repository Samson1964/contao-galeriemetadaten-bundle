<?php

declare(strict_types=1);

/*
 * Galeriemetadaten-Bundle für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoGaleriemetadatenBundle\Controller;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\ImagesController;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Schachbulle\ContaoGaleriemetadatenBundle\Galerie\Ueberschreiber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Galerie-Controller für Contao 5 mit abweichenden Metadaten.
 *
 * Erbt den Core-Controller vollständig, damit Sortierung, Seitenumbruch,
 * Lightbox und Templates exakt dem Core entsprechen. Registriert wird er nur
 * für den Typ „gallery“ (siehe ContaoGaleriemetadatenExtension). Unter
 * Contao 4.13 wird diese Datei nie geladen.
 */
class GalerieController extends ImagesController
{
    /**
     * Rendert die Galerie und verwendet dabei die abweichenden Metadaten.
     *
     * Die Figuren des Cores ermitteln ihre Metadaten erst beim Rendern des
     * Templates (verzögert über onDefineMetadata). Das Template wird aber
     * innerhalb von parent::getResponse() gerendert, sodass die Abweichungen
     * währenddessen noch im Modell-Register stehen. Danach werden sie in jedem
     * Fall zurückgenommen.
     *
     * @param FragmentTemplate $template Das vom Core vorbereitete Template
     * @param ContentModel     $model    Das Galerie-Inhaltselement
     * @param Request          $request  Die laufende Anfrage
     *
     * @return Response Die fertige Ausgabe des Core-Controllers
     */
    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $ueberschreiber = new Ueberschreiber();
        $ueberschreiber->anwenden($model->galeriemetadaten);

        try {
            return parent::getResponse($template, $model, $request);
        } finally {
            $ueberschreiber->wiederherstellen();
        }
    }
}
