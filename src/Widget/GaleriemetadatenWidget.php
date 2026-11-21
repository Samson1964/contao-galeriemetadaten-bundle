<?php

declare(strict_types=1);

/*
 * Galeriemetadaten-Bundle für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoGaleriemetadatenBundle\Widget;

use Contao\Database;
use Contao\FilesModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Validator;
use Contao\Widget;
use Schachbulle\ContaoGaleriemetadatenBundle\Galerie\Bildliste;
use Schachbulle\ContaoGaleriemetadatenBundle\Galerie\Ueberschreiber;

/**
 * Backend-Widget für abweichende Metadaten der Bilder einer Galerie.
 *
 * Zeigt für jedes ausgewählte Bild (auch für Bilder aus ausgewählten Ordnern)
 * einen aufklappbaren Block mit Vorschaubild und den Metadatenfeldern der
 * Dateiverwaltung. Als Platzhalter erscheinen die Originalwerte, damit sofort
 * zu sehen ist, was ohne Abweichung ausgegeben würde.
 *
 * Gespeichert wird ein Array [Datei-UUID => [Feld => Wert]] in der Spalte
 * tl_content.galeriemetadaten. Weil der Schlüssel die UUID ist, überstehen die
 * Angaben jedes Umsortieren, Verschieben und Umbenennen der Bilder. Entfernte
 * Bilder fallen beim nächsten Speichern heraus, weil das Formular nur die
 * aktuell ausgewählten Bilder enthält.
 */
class GaleriemetadatenWidget extends Widget
{
    /**
     * Das Widget liefert Formulardaten.
     *
     * @var bool
     */
    protected $blnSubmitInput = true;

    /**
     * Kein for-Attribut am Label, weil das Widget aus vielen Feldern besteht.
     *
     * @var bool
     */
    protected $blnForAttribute = false;

    /**
     * Standard-Rahmen der Backend-Widgets (Label, Fehler, Hilfetext).
     *
     * @var string
     */
    protected $strTemplate = 'be_widget';

    /**
     * Merkt sich, ob die Stilangaben in dieser Anfrage schon ausgegeben wurden.
     */
    private static bool $stilAusgegeben = false;

    /**
     * Prüft und bereinigt die abgeschickten Werte.
     *
     * Übernommen werden nur gültige UUIDs und die Metadatenfelder, die die
     * Dateiverwaltung kennt (FilesModel::getMetaFields(), in Contao 5 z. B.
     * zusätzlich „license“). Leere Felder werden verworfen; bleibt nichts
     * übrig, wird ein Leerstring gespeichert.
     *
     * @param mixed $varInput Rohdaten aus dem Formular, erwartet [UUID => [Feld => Wert]]
     *
     * @return array<string, array<string, string>>|string Bereinigte Abweichungen oder ''
     */
    protected function validator($varInput)
    {
        if (!\is_array($varInput)) {
            return '';
        }

        $erlaubt = FilesModel::getMetaFields();
        $ergebnis = [];

        foreach ($varInput as $uuid => $felder) {
            if (!\is_string($uuid) || !Validator::isStringUuid($uuid) || !\is_array($felder)) {
                continue;
            }

            $felder = Ueberschreiber::felderBereinigen($felder, $erlaubt);

            if ([] !== $felder) {
                $ergebnis[$uuid] = $felder;
            }
        }

        return [] === $ergebnis ? '' : $ergebnis;
    }

    /**
     * Erzeugt das HTML des Widgets.
     *
     * Die Bildliste stammt aus der gespeicherten Bildauswahl des Elements.
     * Wurde die Auswahl gerade erst geändert, erscheinen neue Bilder deshalb
     * erst nach dem Speichern – darauf weist der Hinweistext hin.
     *
     * @return string HTML für das Bearbeitungsformular
     */
    public function generate(): string
    {
        $this->loadLanguageFile('tl_content');
        $sprache = &$GLOBALS['TL_LANG']['tl_content'];

        $element = Database::getInstance()
            ->prepare('SELECT multiSRC, ptable, pid FROM tl_content WHERE id=?')
            ->limit(1)
            ->execute((int) $this->currentRecord);

        $bilder = $element->numRows ? Bildliste::ausAuswahl($element->multiSRC) : [];
        $werte = Ueberschreiber::lesen($this->varValue);

        $html = $this->stil().'<div class="galeriemetadaten" id="ctrl_'.$this->strId.'">';

        if ([] === $bilder) {
            $html .= '<p class="tl_info">'.($sprache['galeriemetadaten_keineBilder'] ?? '').'</p>';

            return $html.'</div>';
        }

        $html .= '<p class="galeriemetadaten-hinweis">'.($sprache['galeriemetadaten_hinweis'] ?? '').'</p>';

        $sprachen = $element->numRows ? $this->sprachenDesElements((string) $element->ptable, (int) $element->pid) : [];
        $felder = FilesModel::getMetaFields();

        foreach ($bilder as $nummer => $datei) {
            $uuid = StringUtil::binToUuid($datei->uuid);
            $eigene = $werte[$uuid] ?? [];
            $original = self::originalwerte($datei, $sprachen);

            $html .= '<details class="galeriemetadaten-bild"'.([] !== $eigene ? ' open' : '').'>';
            $html .= '<summary>'.$this->vorschau($datei);
            $html .= '<span class="galeriemetadaten-name">'.StringUtil::specialchars($datei->path).'</span>';

            if ([] !== $eigene) {
                $html .= ' <span class="galeriemetadaten-status">'.($sprache['galeriemetadaten_angepasst'] ?? '').'</span>';
            }

            $html .= '</summary><div class="galeriemetadaten-felder">';

            foreach ($felder as $feld) {
                $id = 'ctrl_'.$this->strId.'_'.$nummer.'_'.$feld;
                $name = $this->strName.'['.$uuid.']['.$feld.']';
                $wert = StringUtil::specialchars($eigene[$feld] ?? '');
                $platzhalter = StringUtil::specialchars($original[$feld] ?? '');
                $label = $GLOBALS['TL_LANG']['MSC']['aw_'.$feld] ?? $feld;

                if (\is_array($label)) {
                    $label = $label[0];
                }

                $html .= '<div class="galeriemetadaten-feld"><label for="'.$id.'">'.StringUtil::specialchars((string) $label).'</label><div>';

                if ('caption' === $feld) {
                    $html .= '<textarea name="'.$name.'" id="'.$id.'" class="tl_textarea" rows="2" placeholder="'.$platzhalter.'">'.$wert.'</textarea>';
                } else {
                    $html .= '<input type="text" name="'.$name.'" id="'.$id.'" class="tl_text" value="'.$wert.'" placeholder="'.$platzhalter.'">';
                }

                $html .= '</div></div>';
            }

            $html .= '</div></details>';
        }

        return $html.'</div>';
    }

    /**
     * Liefert die Metadaten einer Datei, die ohne Abweichung ausgegeben würden.
     *
     * Gewählt wird der Eintrag der ersten Sprache aus $sprachen, für die einer
     * existiert; sonst der erste vorhandene Eintrag überhaupt. So passt der
     * Platzhalter im Regelfall zur Sprache der Seite, auf der die Galerie steht.
     *
     * @param FilesModel   $datei    Die Bilddatei
     * @param list<string> $sprachen Sprachkette der Seite, ggf. leer
     *
     * @return array<string, string> Feldname => Originalwert, leer ohne Metadaten
     */
    private static function originalwerte(FilesModel $datei, array $sprachen): array
    {
        $meta = StringUtil::deserialize($datei->meta, true);

        foreach ($sprachen as $s) {
            if (\is_array($meta[$s] ?? null)) {
                return array_map('strval', array_filter($meta[$s], 'is_scalar'));
            }
        }

        foreach ($meta as $eintrag) {
            if (\is_array($eintrag)) {
                return array_map('strval', array_filter($eintrag, 'is_scalar'));
            }
        }

        return [];
    }

    /**
     * Ermittelt die Sprachkette der Seite, auf der das Element steht.
     *
     * Funktioniert für Elemente in Artikeln (ptable tl_article oder leer bei
     * sehr alten Datensätzen). Für Elemente in Nachrichten, Events usw. wäre
     * die Seite nur über Umwege bestimmbar; dort bleibt die Liste leer und die
     * Platzhalter nehmen den ersten vorhandenen Spracheintrag.
     *
     * @param string $ptable Elterntabelle des Inhaltselements
     * @param int    $pid    ID des Elterndatensatzes
     *
     * @return list<string> Sprache und Fallback-Sprache der Seite
     */
    private function sprachenDesElements(string $ptable, int $pid): array
    {
        if ('' !== $ptable && 'tl_article' !== $ptable) {
            return [];
        }

        $artikel = Database::getInstance()
            ->prepare('SELECT pid FROM tl_article WHERE id=?')
            ->limit(1)
            ->execute($pid);

        if (!$artikel->numRows) {
            return [];
        }

        $seite = PageModel::findWithDetails((int) $artikel->pid);

        if (null === $seite) {
            return [];
        }

        return array_values(array_unique(array_filter([
            (string) $seite->language,
            (string) $seite->rootFallbackLanguage,
        ])));
    }

    /**
     * Erzeugt ein kleines Vorschaubild für die Zusammenfassungszeile.
     *
     * Läuft über das Image Studio, das in Contao 4.13 und 5.7 gleich bedient
     * wird. Kann das Bild nicht verarbeitet werden (defekte Datei, fehlende
     * Bibliothek), bleibt die Vorschau einfach leer.
     *
     * @param FilesModel $datei Die Bilddatei
     *
     * @return string <img>-Tag oder Leerstring
     */
    private function vorschau(FilesModel $datei): string
    {
        try {
            $figur = System::getContainer()
                ->get('contao.image.studio')
                ->createFigureBuilder()
                ->fromFilesModel($datei)
                ->setSize([80, 60, 'box'])
                ->buildIfResourceExists();

            if (null === $figur) {
                return '';
            }

            return '<img src="'.StringUtil::specialchars($figur->getImage()->getImageSrc(true)).'" alt="" class="galeriemetadaten-vorschau">';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Liefert die Stilangaben des Widgets, einmal je Anfrage.
     *
     * Bewusst eingebettet statt als Datei unter public/: So braucht das Bundle
     * keinen assets:install-Lauf und keine öffentlichen Ressourcen.
     *
     * @return string <style>-Block oder Leerstring, wenn schon ausgegeben
     */
    private function stil(): string
    {
        if (self::$stilAusgegeben) {
            return '';
        }

        self::$stilAusgegeben = true;

        return '<style>'
            .'.galeriemetadaten{margin-top:6px}'
            .'.galeriemetadaten-hinweis{margin:0 0 8px;color:var(--info,#666);font-size:.75rem}'
            .'.galeriemetadaten-bild{border:1px solid var(--border,#ddd);border-radius:3px;margin-bottom:6px}'
            .'.galeriemetadaten-bild>summary{display:flex;align-items:center;gap:10px;padding:4px 8px;cursor:pointer}'
            .'.galeriemetadaten-vorschau{width:80px;height:60px;object-fit:contain;background:var(--table-header,#f3f3f5)}'
            .'.galeriemetadaten-name{word-break:break-all}'
            .'.galeriemetadaten-status{font-size:.7rem;padding:1px 6px;border-radius:2px;background:#589b0e;color:#fff;white-space:nowrap}'
            .'.galeriemetadaten-felder{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:6px 15px;padding:4px 8px 10px}'
            .'.galeriemetadaten-feld label{display:block;margin-bottom:2px;font-weight:600}'
            .'</style>';
    }
}
