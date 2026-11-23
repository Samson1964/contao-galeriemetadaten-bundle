<?php

declare(strict_types=1);

/*
 * Galeriemetadaten-Bundle für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoGaleriemetadatenBundle\Widget;

use Contao\Config;
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
 * einen aufklappbaren Block mit Vorschaubild, den Metadatenfeldern der
 * Dateiverwaltung und einem Editor für den wichtigen Teil. Als Platzhalter
 * erscheinen die Originalwerte, beim wichtigen Teil als gestrichelter Rahmen.
 *
 * Gespeichert wird ein Array [Datei-UUID => [Feld => Wert]] in der Spalte
 * tl_content.galeriemetadaten. Weil der Schlüssel die UUID ist, überstehen die
 * Angaben jedes Umsortieren, Verschieben und Umbenennen der Bilder. Entfernte
 * Bilder fallen beim nächsten Speichern heraus, weil das Formular nur die
 * aktuell ausgewählten Bilder enthält.
 *
 * Enthält die Galerie mehr Bilder als in den Systemeinstellungen erlaubt,
 * erscheint statt der Blöcke nur ein Hinweis; vorhandene Abweichungen bleiben
 * dann gespeichert und wirken weiter.
 */
class GaleriemetadatenWidget extends Widget
{
    /**
     * Name des Formularfeldes, das beim Überschreiten der Bildgrenze anzeigt,
     * dass die gespeicherten Werte unverändert bleiben sollen.
     */
    private const BEHALTEN = '__behalten';

    /**
     * Voreinstellung für die Bildgrenze, solange in den Systemeinstellungen
     * nichts eingetragen ist.
     */
    public const GRENZE_STANDARD = 50;

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
     * Merkt sich, ob Stil und Skript in dieser Anfrage schon ausgegeben wurden.
     */
    private static bool $kopfAusgegeben = false;

    /**
     * Liefert die Höchstzahl an Bildern, für die das Widget Eingabefelder zeigt.
     *
     * Der Wert stammt aus den Systemeinstellungen (galeriemetadatenMaxBilder).
     * Ist dort nichts eingetragen, gilt GRENZE_STANDARD; 0 bedeutet „ohne
     * Grenze“.
     *
     * @return int Grenze (≥ 1) oder 0 für unbegrenzt
     */
    public static function grenze(): int
    {
        $wert = Config::get('galeriemetadatenMaxBilder');

        if (null === $wert || '' === $wert || !is_numeric($wert)) {
            return self::GRENZE_STANDARD;
        }

        return max(0, (int) $wert);
    }

    /**
     * Prüft und bereinigt die abgeschickten Werte.
     *
     * Übernommen werden nur gültige UUIDs, die Metadatenfelder der
     * Dateiverwaltung (FilesModel::getMetaFields()) und ein vollständiger,
     * gültiger wichtiger Teil. Leere Felder werden verworfen; bleibt nichts
     * übrig, wird ein Leerstring gespeichert.
     *
     * Sonderfall Bildgrenze: Zeigte das Formular nur den Hinweis, enthält es
     * keine Eingabefelder, sondern nur das Feld __behalten. Dann bleibt der
     * gespeicherte Wert unverändert – sonst würde unter Contao 4.13 jedes
     * Speichern des Elements die Abweichungen löschen.
     *
     * @param mixed $varInput Rohdaten aus dem Formular, erwartet [UUID => [Feld => Wert]]
     *
     * @return array<string, array<string, string|float>>|string Bereinigte Abweichungen oder ''
     */
    protected function validator($varInput)
    {
        if (\is_array($varInput) && isset($varInput[self::BEHALTEN])) {
            $bestehend = Ueberschreiber::lesen($this->varValue);

            return [] === $bestehend ? '' : $bestehend;
        }

        if (!\is_array($varInput)) {
            return '';
        }

        $erlaubt = array_merge(FilesModel::getMetaFields(), Ueberschreiber::WICHTIGER_TEIL);
        $ergebnis = [];

        foreach ($varInput as $uuid => $felder) {
            if (!\is_string($uuid) || !Validator::isStringUuid($uuid) || !\is_array($felder)) {
                continue;
            }

            $felder = Ueberschreiber::felderBereinigen($felder, $erlaubt);

            // Den wichtigen Teil nur als geprüften, vollständigen Satz behalten
            $wichtigerTeil = Ueberschreiber::wichtigerTeil($felder);
            $felder = array_diff_key($felder, array_flip(Ueberschreiber::WICHTIGER_TEIL));

            if (null !== $wichtigerTeil) {
                $felder += $wichtigerTeil;
            }

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

        $html = $this->kopf().'<div class="galeriemetadaten" id="ctrl_'.$this->strId.'">';

        if ([] === $bilder) {
            $html .= '<p class="tl_info">'.($sprache['galeriemetadaten_keineBilder'] ?? '').'</p>';

            return $html.'</div>';
        }

        $grenze = self::grenze();

        if ($grenze > 0 && \count($bilder) > $grenze) {
            $html .= '<p class="tl_info">'.\sprintf(
                $sprache['galeriemetadaten_zuViele'] ?? '%d / %d / %d',
                \count($bilder),
                $grenze,
                \count($werte),
            ).'</p>';
            $html .= '<input type="hidden" name="'.$this->strName.'['.self::BEHALTEN.']" value="1">';

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
            $html .= '<summary>'.$this->bild($datei, 80, 60, 'galeriemetadaten-vorschau');
            $html .= '<span class="galeriemetadaten-name">'.StringUtil::specialchars($datei->path).'</span>';

            if ([] !== $eigene) {
                $html .= ' <span class="galeriemetadaten-status">'.($sprache['galeriemetadaten_angepasst'] ?? '').'</span>';
            }

            $html .= '</summary><div class="galeriemetadaten-felder">';

            foreach ($felder as $feld) {
                $id = 'ctrl_'.$this->strId.'_'.$nummer.'_'.$feld;
                $name = $this->strName.'['.$uuid.']['.$feld.']';
                $wert = StringUtil::specialchars((string) ($eigene[$feld] ?? ''));
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

            $html .= $this->wichtigerTeilEditor($datei, $uuid, Ueberschreiber::wichtigerTeil($eigene));
            $html .= '</div></details>';
        }

        return $html.'</div>';
    }

    /**
     * Erzeugt den Editor für den wichtigen Teil eines Bildes.
     *
     * Ein Vorschaubild, auf dem sich mit Maus, Stift oder Finger ein Rahmen
     * aufziehen lässt. Der Rahmen der Dateiverwaltung erscheint gestrichelt,
     * der abweichende durchgezogen. Die vier Werte landen als Anteile (0 bis 1)
     * in versteckten Feldern; „Zurücksetzen“ leert sie, dann gilt wieder der
     * wichtige Teil der Dateiverwaltung.
     *
     * Die Anteile beziehen sich auf das Vorschaubild. Da es mit „box“ ohne
     * Beschnitt verkleinert wird, entsprechen sie denen des Originals.
     *
     * @param FilesModel                $datei  Die Bilddatei
     * @param string                    $uuid   Lesbare UUID der Datei (Schlüssel im Formular)
     * @param array<string, float>|null $eigene Gespeicherter abweichender Teil oder null
     *
     * @return string HTML des Editors, leer wenn kein Vorschaubild entsteht
     */
    private function wichtigerTeilEditor(FilesModel $datei, string $uuid, ?array $eigene): string
    {
        $bild = $this->bild($datei, 360, 270, '');

        if ('' === $bild) {
            return '';
        }

        $sprache = $GLOBALS['TL_LANG']['tl_content'];
        $html = '<div class="galeriemetadaten-wt"><span class="galeriemetadaten-wt-titel">'.($sprache['galeriemetadaten_wichtigerTeil'] ?? '').'</span>';
        $html .= '<div class="galeriemetadaten-wt-flaeche">'.$bild;

        $original = Ueberschreiber::wichtigerTeil([
            'importantPartX' => $datei->importantPartX,
            'importantPartY' => $datei->importantPartY,
            'importantPartWidth' => $datei->importantPartWidth,
            'importantPartHeight' => $datei->importantPartHeight,
        ]);

        if (null !== $original) {
            $html .= '<span class="galeriemetadaten-wt-original" style="'.self::rahmenStil($original).'"></span>';
        }

        $html .= '<span class="galeriemetadaten-wt-neu" style="'.(null !== $eigene ? self::rahmenStil($eigene) : 'display:none').'"></span></div>';

        foreach (Ueberschreiber::WICHTIGER_TEIL as $spalte) {
            $html .= '<input type="hidden" data-wt="'.$spalte.'" name="'.$this->strName.'['.$uuid.']['.$spalte.']" value="'.(null !== $eigene ? StringUtil::specialchars((string) $eigene[$spalte]) : '').'">';
        }

        $html .= '<button type="button" class="tl_submit galeriemetadaten-wt-zurueck">'.($sprache['galeriemetadaten_wtZurueck'] ?? '').'</button>';
        $html .= '<p class="tl_help tl_tip">'.($sprache['galeriemetadaten_wtHilfe'] ?? '').'</p></div>';

        return $html;
    }

    /**
     * Wandelt einen wichtigen Teil in CSS-Angaben für den Rahmen um.
     *
     * @param array<string, float> $teil Vier Anteile, Schlüssel wie Ueberschreiber::WICHTIGER_TEIL
     *
     * @return string left/top/width/height in Prozent
     */
    private static function rahmenStil(array $teil): string
    {
        return \sprintf(
            'left:%.4F%%;top:%.4F%%;width:%.4F%%;height:%.4F%%',
            $teil['importantPartX'] * 100,
            $teil['importantPartY'] * 100,
            $teil['importantPartWidth'] * 100,
            $teil['importantPartHeight'] * 100,
        );
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
     * Erzeugt ein verkleinertes Vorschaubild ohne Beschnitt.
     *
     * Läuft über das Image Studio, das in Contao 4.13 und 5.7 gleich bedient
     * wird. Kann das Bild nicht verarbeitet werden (defekte Datei, fehlende
     * Bibliothek), bleibt die Vorschau einfach leer.
     *
     * @param FilesModel $datei   Die Bilddatei
     * @param int        $breite  Höchstbreite in Pixeln
     * @param int        $hoehe   Höchsthöhe in Pixeln
     * @param string     $klasse  CSS-Klasse des <img>, leer für keine
     *
     * @return string <img>-Tag oder Leerstring
     */
    private function bild(FilesModel $datei, int $breite, int $hoehe, string $klasse): string
    {
        try {
            $figur = System::getContainer()
                ->get('contao.image.studio')
                ->createFigureBuilder()
                ->fromFilesModel($datei)
                ->setSize([$breite, $hoehe, 'box'])
                ->buildIfResourceExists();

            if (null === $figur) {
                return '';
            }

            return '<img src="'.StringUtil::specialchars($figur->getImage()->getImageSrc(true)).'" alt="" draggable="false"'.('' !== $klasse ? ' class="'.$klasse.'"' : '').'>';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Liefert Stilangaben und Skript des Widgets, einmal je Anfrage.
     *
     * Bewusst eingebettet statt als Datei unter public/: So braucht das Bundle
     * keinen assets:install-Lauf. Das Skript hängt sich per Ereignis-Delegation
     * an das Dokument und meldet sich nur einmal an (window-Merker). Dadurch
     * funktioniert es unter Contao 4.13 ebenso wie unter Contao 5, wo Turbo
     * Seiten ohne Neuladen austauscht.
     *
     * @return string <style>- und <script>-Block oder Leerstring
     */
    private function kopf(): string
    {
        if (self::$kopfAusgegeben) {
            return '';
        }

        self::$kopfAusgegeben = true;

        $stil = '<style>'
            .'.galeriemetadaten{margin-top:6px}'
            .'.galeriemetadaten-hinweis{margin:0 0 8px;color:var(--info,#666);font-size:.75rem}'
            .'.galeriemetadaten-bild{border:1px solid var(--border,#ddd);border-radius:3px;margin-bottom:6px}'
            .'.galeriemetadaten-bild>summary{display:flex;align-items:center;gap:10px;padding:4px 8px;cursor:pointer}'
            .'.galeriemetadaten-vorschau{width:80px;height:60px;object-fit:contain;background:var(--table-header,#f3f3f5)}'
            .'.galeriemetadaten-name{word-break:break-all}'
            .'.galeriemetadaten-status{font-size:.7rem;padding:1px 6px;border-radius:2px;background:#589b0e;color:#fff;white-space:nowrap}'
            .'.galeriemetadaten-felder{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:6px 15px;padding:4px 8px 10px}'
            .'.galeriemetadaten-feld label,.galeriemetadaten-wt-titel{display:block;margin-bottom:2px;font-weight:600}'
            .'.galeriemetadaten-wt{grid-column:1/-1}'
            .'.galeriemetadaten-wt-flaeche{position:relative;display:inline-block;line-height:0;cursor:crosshair;touch-action:none;user-select:none;max-width:100%}'
            .'.galeriemetadaten-wt-flaeche img{max-width:100%;height:auto}'
            .'.galeriemetadaten-wt-original,.galeriemetadaten-wt-neu{position:absolute;box-sizing:border-box;pointer-events:none}'
            .'.galeriemetadaten-wt-original{border:2px dashed #fff;outline:1px dashed #000}'
            .'.galeriemetadaten-wt-neu{border:2px solid #f47c00;background:rgba(244,124,0,.15)}'
            .'.galeriemetadaten-wt-zurueck{display:block;margin-top:6px}'
            .'</style>';

        // Rahmen aufziehen, Werte in die versteckten Felder schreiben;
        // ein bloßer Klick ohne Ziehen stellt den vorherigen Zustand wieder her.
        $skript = <<<'JS'
            <script>
            (function () {
              if (window.galeriemetadatenWt) { return; }
              window.galeriemetadatenWt = true;
              var felder = ['importantPartX', 'importantPartY', 'importantPartWidth', 'importantPartHeight'];

              function setzen(wt, teil) {
                var neu = wt.querySelector('.galeriemetadaten-wt-neu');
                felder.forEach(function (f, i) {
                  wt.querySelector('[data-wt="' + f + '"]').value = teil ? teil[i].toFixed(4) : '';
                });
                if (!teil) { neu.style.display = 'none'; return; }
                neu.style.display = 'block';
                neu.style.left = (teil[0] * 100) + '%';
                neu.style.top = (teil[1] * 100) + '%';
                neu.style.width = (teil[2] * 100) + '%';
                neu.style.height = (teil[3] * 100) + '%';
              }

              function lesen(wt) {
                var werte = felder.map(function (f) { return wt.querySelector('[data-wt="' + f + '"]').value; });
                return werte[0] === '' ? null : werte.map(parseFloat);
              }

              document.addEventListener('pointerdown', function (e) {
                var flaeche = e.target.closest ? e.target.closest('.galeriemetadaten-wt-flaeche') : null;
                if (!flaeche || e.button > 0) { return; }
                e.preventDefault();
                var wt = flaeche.closest('.galeriemetadaten-wt');
                var r = flaeche.getBoundingClientRect();
                var klemmen = function (v) { return Math.min(1, Math.max(0, v)); };
                var x0 = klemmen((e.clientX - r.left) / r.width);
                var y0 = klemmen((e.clientY - r.top) / r.height);
                var vorher = lesen(wt);
                var aktuell = null;
                if (flaeche.setPointerCapture) { flaeche.setPointerCapture(e.pointerId); }

                function bewegen(ev) {
                  var x1 = klemmen((ev.clientX - r.left) / r.width);
                  var y1 = klemmen((ev.clientY - r.top) / r.height);
                  aktuell = [Math.min(x0, x1), Math.min(y0, y1), Math.abs(x1 - x0), Math.abs(y1 - y0)];
                  setzen(wt, aktuell);
                }

                function ende() {
                  flaeche.removeEventListener('pointermove', bewegen);
                  flaeche.removeEventListener('pointerup', ende);
                  flaeche.removeEventListener('pointercancel', ende);
                  if (!aktuell || aktuell[2] < 0.02 || aktuell[3] < 0.02) { setzen(wt, vorher); }
                }

                flaeche.addEventListener('pointermove', bewegen);
                flaeche.addEventListener('pointerup', ende);
                flaeche.addEventListener('pointercancel', ende);
              });

              document.addEventListener('click', function (e) {
                var knopf = e.target.closest ? e.target.closest('.galeriemetadaten-wt-zurueck') : null;
                if (knopf) { setzen(knopf.closest('.galeriemetadaten-wt'), null); }
              });
            })();
            </script>
            JS;

        return $stil.$skript;
    }
}
