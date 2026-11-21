<?php

declare(strict_types=1);

/*
 * Galeriemetadaten-Bundle für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoGaleriemetadatenBundle\Galerie;

use Contao\FilesModel;
use Contao\StringUtil;
use Contao\System;

/**
 * Löst die Bildauswahl eines Galerie-Elements in einzelne Bilddateien auf.
 */
final class Bildliste
{
    /**
     * Liefert alle Bilder einer Auswahl in der Reihenfolge der Auswahl.
     *
     * Ausgewählte Ordner werden wie in der Galerie des Cores aufgelöst: nur die
     * Dateien direkt im Ordner (keine Unterordner), alphabetisch. Jede Datei
     * erscheint höchstens einmal, auch wenn sie einzeln und über ihren Ordner
     * ausgewählt wurde. Nicht-Bilder fallen anhand der Dateiendung heraus
     * (Parameter contao.image.valid_extensions, in 4.13 und 5.7 vorhanden).
     *
     * Die Reihenfolge entspricht nicht zwingend der Ausgabe im Frontend
     * (Sortierung nach Datum, Zufall usw.); für die Pflege genügt sie.
     *
     * @param mixed $auswahl Inhalt von tl_content.multiSRC (serialisierte Binär-UUIDs)
     *
     * @return list<FilesModel> Bilddateien, leer wenn nichts ausgewählt ist
     */
    public static function ausAuswahl($auswahl): array
    {
        $uuids = StringUtil::deserialize($auswahl, true);

        if ([] === $uuids) {
            return [];
        }

        $endungen = array_map('strtolower', (array) System::getContainer()->getParameter('contao.image.valid_extensions'));
        $bilder = [];

        foreach ($uuids as $uuid) {
            if (!\is_string($uuid) || '' === $uuid) {
                continue;
            }

            $eintrag = FilesModel::findByUuid($uuid);

            if (null === $eintrag) {
                continue;
            }

            $kandidaten = [$eintrag];

            if ('folder' === $eintrag->type) {
                $kandidaten = [];
                $inhalt = FilesModel::findByPid($eintrag->uuid, ['order' => 'name']);

                if (null !== $inhalt) {
                    foreach ($inhalt as $datei) {
                        $kandidaten[] = $datei;
                    }
                }
            }

            foreach ($kandidaten as $datei) {
                if ('file' !== $datei->type || !\in_array(strtolower((string) $datei->extension), $endungen, true)) {
                    continue;
                }

                $bilder[StringUtil::binToUuid($datei->uuid)] = $datei;
            }
        }

        return array_values($bilder);
    }
}
