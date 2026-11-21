<?php

declare(strict_types=1);

/*
 * Galeriemetadaten-Bundle für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoGaleriemetadatenBundle\Tests\Galerie;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoGaleriemetadatenBundle\Galerie\Ueberschreiber;

/**
 * Prüft die reine Zusammenführungslogik, ohne Datenbank und Container.
 */
class UeberschreiberTest extends TestCase
{
    /**
     * Eine Abweichung ersetzt nur das betroffene Feld des vorhandenen
     * Spracheintrags; die übrigen Felder bleiben erhalten.
     */
    public function testUeberschreibtNurAngegebeneFelder(): void
    {
        $meta = ['de' => ['title' => 'Alt', 'alt' => 'Bild', 'caption' => 'Unterschrift']];

        $neu = Ueberschreiber::zusammenfuehren($meta, ['caption' => 'Neu'], ['de']);

        $this->assertSame(['title' => 'Alt', 'alt' => 'Bild', 'caption' => 'Neu'], $neu['de']);
    }

    /**
     * Leere Abweichungen lassen die Metadaten unverändert.
     */
    public function testLeereAbweichungAendertNichts(): void
    {
        $meta = ['de' => ['title' => 'Alt']];

        $this->assertSame($meta, Ueberschreiber::zusammenfuehren($meta, ['title' => '  ', 'alt' => ''], ['de']));
    }

    /**
     * {{empty}} leert einen Wert ausdrücklich.
     */
    public function testEmptyLeertWert(): void
    {
        $meta = ['de' => ['title' => 'Alt', 'caption' => 'Weg damit']];

        $neu = Ueberschreiber::zusammenfuehren($meta, ['caption' => '{{empty}}'], ['de']);

        $this->assertSame('', $neu['de']['caption']);
        $this->assertSame('Alt', $neu['de']['title']);
    }

    /**
     * Fehlt der Eintrag der Seitensprache, wird er angelegt – und zwar auf
     * Grundlage des Fallback-Eintrags, den Contao sonst verwendet hätte.
     */
    public function testNeuerSpracheintragUebernimmtFallback(): void
    {
        $meta = ['en' => ['title' => 'Title', 'alt' => 'Picture']];

        $neu = Ueberschreiber::zusammenfuehren($meta, ['title' => 'Titel'], ['de', 'en']);

        $this->assertSame(['title' => 'Titel', 'alt' => 'Picture'], $neu['de']);
        $this->assertSame(['title' => 'Titel', 'alt' => 'Picture'], $neu['en']);
    }

    /**
     * Ganz ohne Metadaten entsteht ein Eintrag für jede Sprache der Kette.
     */
    public function testOhneMetadatenEntstehtEintrag(): void
    {
        $neu = Ueberschreiber::zusammenfuehren([], ['alt' => 'Beschreibung'], ['de']);

        $this->assertSame(['de' => ['alt' => 'Beschreibung']], $neu);
    }

    /**
     * Ohne Sprachkette (z. B. Backend-Vorschau) werden nur vorhandene
     * Einträge angepasst.
     */
    public function testOhneSprachkettePasstVorhandeneAn(): void
    {
        $neu = Ueberschreiber::zusammenfuehren(['de' => ['title' => 'A'], 'en' => ['title' => 'B']], ['title' => 'C'], []);

        $this->assertSame('C', $neu['de']['title']);
        $this->assertSame('C', $neu['en']['title']);
    }

    /**
     * Nur erlaubte Felder und nur Zeichenketten bleiben übrig.
     */
    public function testFelderBereinigenFiltert(): void
    {
        $felder = ['title' => ' T ', 'alt' => '', 'boese' => 'x', 'link' => ['a'], 'caption' => 'C'];

        $this->assertSame(
            ['title' => 'T', 'caption' => 'C'],
            Ueberschreiber::felderBereinigen($felder, ['title', 'alt', 'link', 'caption']),
        );
    }

    /**
     * lesen() akzeptiert serialisierte Werte und verwirft Bilder ohne Abweichung.
     */
    public function testLesenVerwirftLeereBilder(): void
    {
        $gespeichert = serialize([
            '9e474b7e-c4b0-11ef-a3e4-c4cb54b1c1a3' => ['title' => 'Titel'],
            'a0000000-0000-0000-0000-000000000000' => ['title' => ''],
            7 => ['title' => 'falscher Schlüssel'],
        ]);

        $this->assertSame(
            ['9e474b7e-c4b0-11ef-a3e4-c4cb54b1c1a3' => ['title' => 'Titel']],
            Ueberschreiber::lesen($gespeichert),
        );
        $this->assertSame([], Ueberschreiber::lesen(null));
        $this->assertSame([], Ueberschreiber::lesen(''));
    }
}
