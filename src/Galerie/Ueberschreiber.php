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
     * Gesicherte Originalwerte als Paare aus Modell und ursprünglicher
     * meta-Spalte, in der Reihenfolge der Änderung.
     *
     * @var list<array{0: FilesModel, 1: mixed}>
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

            $original = $model->meta;
            $meta = self::zusammenfuehren(StringUtil::deserialize($original, true), $felder, $sprachen);

            $this->sicherung[] = [$model, $original];
            $model->meta = serialize($meta);
        }
    }

    /**
     * Setzt alle durch anwenden() veränderten Modelle auf ihre ursprüngliche
     * meta-Spalte zurück.
     *
     * Die Rückgabe erfolgt in umgekehrter Reihenfolge, damit auch eine doppelt
     * veränderte Datei sicher beim Ausgangswert landet. Das Feld bleibt im
     * Modell als „modifiziert“ markiert; ein späteres save() würde aber nur den
     * Originalwert erneut schreiben und ist damit unschädlich.
     */
    public function wiederherstellen(): void
    {
        foreach (array_reverse($this->sicherung) as [$model, $original]) {
            $model->meta = $original;
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
        $ueberschreibung = array_map(
            static fn (string $wert): string => self::LEEREN === $wert ? '' : $wert,
            self::felderBereinigen($ueberschreibung),
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
