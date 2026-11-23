<?php

declare(strict_types=1);

/*
 * Galeriemetadaten-Bundle für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoGaleriemetadatenBundle\Galerie;

use Contao\CoreBundle\Util\LocaleUtil;
use Contao\FilesModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Validator;

/**
 * Spielt abweichende Metadaten für die Dauer einer Galerie-Ausgabe ein.
 *
 * Neben den Metadaten lässt sich auch der wichtige Teil eines Bildes (die vier
 * importantPart-Spalten) je Galerie ändern; er wirkt nur bei Bildgrößen mit
 * Beschnitt.
 *
 * Funktionsweise: Contao hält je Anfrage genau eine Instanz eines Modells pro
 * Datensatz im Modell-Register. Wird die Spalte „meta“ eines FilesModel
 * verändert (ohne zu speichern), gilt das Feld als modifiziert. Spätere
 * Abfragen derselben Datei – auch die des Galerie-Elements selbst – liefern
 * diese Instanz zurück, und Model::mergeRow() lässt modifizierte Felder
 * unangetastet. Der FigureBuilder liest die Metadaten über
 * FilesModel::getMetadata() und sieht damit die geänderten Werte. Das gilt
 * für Contao 4.13 (ContentGallery, fromId) ebenso wie für Contao 5.7
 * (ImagesController, fromStorage → fromPath → findByPath).
 *
 * Nach der Ausgabe stellt wiederherstellen() die Originalwerte zurück, damit
 * andere Elemente derselben Seite wieder die Angaben der Dateiverwaltung sehen.
 * In die Datenbank wird zu keinem Zeitpunkt geschrieben.
 */
final class Ueberschreiber
{
    /**
     * Eingabe, mit der ein Wert bewusst geleert wird (statt die Angabe der
     * Dateiverwaltung zu übernehmen). Entspricht der Schreibweise, die Contao 5
     * beim Einzelbild für „Metadaten überschreiben“ verwendet.
     */
    public const LEEREN = '{{empty}}';

    /**
     * Spalten von tl_files, die den wichtigen Teil eines Bildes beschreiben.
     * Werte sind Anteile von 0 bis 1 an Breite bzw. Höhe des Originals (Format
     * seit Contao 4.8, in 4.13 und 5.7 gleich). Gelesen werden sie beim
     * Skalieren in ImageFactory::createImportantPart() über findByPath() –
     * also ebenfalls aus dem Modell-Register.
     */
    public const WICHTIGER_TEIL = ['importantPartX', 'importantPartY', 'importantPartWidth', 'importantPartHeight'];

    /**
     * Gesicherte Originalwerte als Tripel aus Modell, Spaltenname und
     * ursprünglichem Wert, in der Reihenfolge der Änderung.
     *
     * @var list<array{0: FilesModel, 1: string, 2: mixed}>
     */
    private array $sicherung = [];

    /**
     * Überträgt die gespeicherten Abweichungen eines Galerie-Elements auf die
     * betroffenen Dateimodelle im Modell-Register.
     *
     * Dateien, die nicht mehr existieren oder keine gültige UUID haben, werden
     * übersprungen. Abweichungen für Bilder, die gar nicht (mehr) in der Galerie
     * stehen, schaden nicht: Sie verändern nur ein Modell, das diese Galerie nie
     * ausgibt, und werden anschließend zurückgesetzt.
     *
     * @param mixed             $gespeichert Inhalt der Spalte tl_content.galeriemetadaten
     *                                       (serialisiertes Array, Array oder leer)
     * @param list<string>|null $sprachen    Sprachkette der Ausgabe; null ermittelt
     *                                       sie aus der aktuellen Seite
     */
    public function anwenden($gespeichert, ?array $sprachen = null): void
    {
        $ueberschreibungen = self::lesen($gespeichert);

        if ([] === $ueberschreibungen) {
            return;
        }

        $sprachen ??= self::aktuelleSprachen();

        foreach ($ueberschreibungen as $uuid => $felder) {
            if (!Validator::isUuid($uuid)) {
                continue;
            }

            $model = FilesModel::findByUuid($uuid);

            if (null === $model) {
                continue;
            }

            $wichtigerTeil = self::wichtigerTeil($felder);
            $metafelder = array_diff_key($felder, array_flip(self::WICHTIGER_TEIL));

            if ([] !== $metafelder) {
                $meta = self::zusammenfuehren(StringUtil::deserialize($model->meta, true), $metafelder, $sprachen);
                $this->setzen($model, 'meta', serialize($meta));
            }

            // Der wichtige Teil gilt sprachunabhängig und wird nur als
            // vollständiger Satz aus vier Werten übernommen.
            if (null !== $wichtigerTeil) {
                foreach ($wichtigerTeil as $spalte => $wert) {
                    $this->setzen($model, $spalte, $wert);
                }
            }
        }
    }

    /**
     * Ändert eine Spalte eines Dateimodells und merkt sich den Ausgangswert.
     *
     * Das Zuweisen über __set markiert die Spalte als modifiziert; nur dadurch
     * überlebt der Wert, wenn der Core dieselbe Datei später erneut abfragt
     * (Model::mergeRow() überschreibt nur unveränderte Spalten).
     *
     * @param FilesModel $model  Das Dateimodell aus dem Register
     * @param string     $spalte Spaltenname in tl_files
     * @param mixed      $wert   Neuer Wert (nur im Speicher)
     */
    private function setzen(FilesModel $model, string $spalte, $wert): void
    {
        $this->sicherung[] = [$model, $spalte, $model->$spalte];
        $model->$spalte = $wert;
    }

    /**
     * Prüft und liefert den abweichenden wichtigen Teil eines Bildes.
     *
     * Übernommen wird er nur, wenn alle vier Werte vorhanden und zahlig sind,
     * Breite und Höhe größer als null sind und das Rechteck innerhalb des Bildes
     * liegt (kleine Rundungsüberhänge bis 0,001 werden geduldet und gekappt).
     * Ein halber oder kaputter Satz wird verworfen, damit nie ein unsinniger
     * Ausschnitt entsteht.
     *
     * @param array<string, mixed> $felder Abweichungen eines Bildes
     *
     * @return array<string, float>|null Die vier Werte (Schlüssel wie WICHTIGER_TEIL) oder null
     */
    public static function wichtigerTeil(array $felder): ?array
    {
        $werte = [];

        foreach (self::WICHTIGER_TEIL as $spalte) {
            $wert = $felder[$spalte] ?? null;

            if (!\is_scalar($wert) || '' === trim((string) $wert) || !is_numeric(str_replace(',', '.', (string) $wert))) {
                return null;
            }

            $werte[$spalte] = (float) str_replace(',', '.', (string) $wert);
        }

        [$x, $y, $breite, $hoehe] = array_values($werte);

        if ($x < 0 || $y < 0 || $breite <= 0 || $hoehe <= 0 || $x + $breite > 1.001 || $y + $hoehe > 1.001) {
            return null;
        }

        return [
            'importantPartX' => round($x, 6),
            'importantPartY' => round($y, 6),
            'importantPartWidth' => round(min($breite, 1 - $x), 6),
            'importantPartHeight' => round(min($hoehe, 1 - $y), 6),
        ];
    }

    /**
     * Setzt alle durch anwenden() veränderten Spalten (Metadaten und wichtiger
     * Teil) auf ihre ursprünglichen Werte zurück.
     *
     * Die Rückgabe erfolgt in umgekehrter Reihenfolge, damit auch eine doppelt
     * veränderte Spalte sicher beim Ausgangswert landet. Die Spalten bleiben im
     * Modell als „modifiziert“ markiert; ein späteres save() würde aber nur die
     * Originalwerte erneut schreiben und ist damit unschädlich.
     */
    public function wiederherstellen(): void
    {
        foreach (array_reverse($this->sicherung) as [$model, $spalte, $original]) {
            $model->$spalte = $original;
        }

        $this->sicherung = [];
    }

    /**
     * Liest den gespeicherten Feldinhalt und bereinigt ihn.
     *
     * Erwartet wird ein Array der Form [UUID => [Feld => Wert]]. Nicht-Arrays,
     * leere Werte und nicht-skalare Einträge fallen heraus; übrig bleiben nur
     * Bilder mit mindestens einer tatsächlichen Abweichung.
     *
     * @param mixed $gespeichert Serialisierter String, Array oder null
     *
     * @return array<string, array<string, string>> Bereinigte Abweichungen, leer wenn keine
     */
    public static function lesen($gespeichert): array
    {
        $daten = \is_array($gespeichert) ? $gespeichert : StringUtil::deserialize($gespeichert, true);
        $ergebnis = [];

        foreach ($daten as $uuid => $felder) {
            if (!\is_string($uuid) || !\is_array($felder)) {
                continue;
            }

            $felder = self::felderBereinigen($felder);

            if ([] !== $felder) {
                $ergebnis[$uuid] = $felder;
            }
        }

        return $ergebnis;
    }

    /**
     * Bereinigt die Felder eines einzelnen Bildes.
     *
     * Werte werden getrimmt, leere Werte entfernt (leer heißt: Angabe aus der
     * Dateiverwaltung übernehmen). Ist $erlaubt gesetzt, bleiben nur diese
     * Feldnamen übrig – so landen keine untergeschobenen Schlüssel aus dem
     * Formular in der Datenbank.
     *
     * @param array<mixed>      $felder  Rohwerte, Feldname => Wert
     * @param list<string>|null $erlaubt Zulässige Feldnamen oder null für alle
     *
     * @return array<string, string> Nur nicht-leere Zeichenketten
     */
    public static function felderBereinigen(array $felder, ?array $erlaubt = null): array
    {
        $ergebnis = [];

        foreach ($felder as $feld => $wert) {
            if (!\is_string($feld) || !\is_scalar($wert)) {
                continue;
            }

            if (null !== $erlaubt && !\in_array($feld, $erlaubt, true)) {
                continue;
            }

            $wert = trim((string) $wert);

            if ('' !== $wert) {
                $ergebnis[$feld] = $wert;
            }
        }

        return $ergebnis;
    }

    /**
     * Führt die Metadaten einer Datei mit den Abweichungen zusammen.
     *
     * Die meta-Spalte ist nach Sprachen gegliedert ([Sprache => [Feld => Wert]]).
     * Der FigureBuilder nimmt den Eintrag der ersten Sprache aus der Sprachkette,
     * für die einer existiert. Damit die Abweichung sicher ankommt, wird sie
     *
     *  - in jeden vorhandenen Spracheintrag geschrieben und
     *  - für jede Sprache der Kette ohne eigenen Eintrag ein neuer angelegt.
     *
     * Ein neu angelegter Eintrag übernimmt als Grundlage den Eintrag, den Contao
     * ohne Abweichung gewählt hätte (erste vorhandene Sprache der Kette). Sonst
     * würde z. B. eine englische Fallback-Beschreibung auf einer deutschen Seite
     * verschwinden, nur weil ein einziges Feld überschrieben wurde.
     *
     * Der Wert {{empty}} leert ein Feld ausdrücklich.
     *
     * @param array<mixed>          $meta            Deserialisierte meta-Spalte
     * @param array<string, string> $ueberschreibung Abweichende Felder eines Bildes
     * @param list<string>          $sprachen        Sprachkette der Ausgabe, beste zuerst
     *
     * @return array<mixed> Neue meta-Spalte (unverändert, wenn nichts zu tun ist)
     */
    public static function zusammenfuehren(array $meta, array $ueberschreibung, array $sprachen): array
    {
        // Der wichtige Teil hat eigene Spalten und gehört nicht in „meta“
        $ueberschreibung = array_map(
            static fn (string $wert): string => self::LEEREN === $wert ? '' : $wert,
            self::felderBereinigen(array_diff_key($ueberschreibung, array_flip(self::WICHTIGER_TEIL))),
        );

        if ([] === $ueberschreibung) {
            return $meta;
        }

        $sprachen = array_values(array_unique(array_filter($sprachen, static fn ($s): bool => \is_string($s) && '' !== $s)));

        // Grundlage für Sprachen ohne eigenen Eintrag
        $grundlage = [];

        foreach ($sprachen as $sprache) {
            if (\is_array($meta[$sprache] ?? null)) {
                $grundlage = $meta[$sprache];
                break;
            }
        }

        $vorhandene = array_keys(array_filter($meta, 'is_array'));
        $ziele = array_unique(array_merge(array_map('strval', $vorhandene), $sprachen));

        foreach ($ziele as $sprache) {
            $eintrag = \is_array($meta[$sprache] ?? null) ? $meta[$sprache] : $grundlage;
            $meta[$sprache] = array_merge($eintrag, $ueberschreibung);
        }

        return $meta;
    }

    /**
     * Ermittelt die Sprachkette der laufenden Ausgabe so, wie es der
     * FigureBuilder tut: Sprache der Seite, danach die Fallback-Sprache der
     * Wurzelseite.
     *
     * Contao 4.13 kennt nur $GLOBALS['objPage']; unter Contao 5 gibt es dazu den
     * PageFinder, der auch bei Fragment-Anfragen die Seite liefert.
     *
     * @return list<string> Sprachen im Locale-Format (z. B. „de“, „en_GB“); leer
     *                      im Backend oder ohne Seitenkontext
     */
    public static function aktuelleSprachen(): array
    {
        $seite = $GLOBALS['objPage'] ?? null;

        if (!$seite instanceof PageModel) {
            $container = System::getContainer();

            if ($container->has('contao.routing.page_finder')) {
                $seite = $container->get('contao.routing.page_finder')->getCurrentPage();
            }
        }

        if (!$seite instanceof PageModel) {
            return [];
        }

        $sprachen = [LocaleUtil::formatAsLocale((string) $seite->language)];

        if (null !== $seite->rootFallbackLanguage) {
            $sprachen[] = LocaleUtil::formatAsLocale((string) $seite->rootFallbackLanguage);
        }

        return array_values(array_unique(array_filter($sprachen)));
    }
}
