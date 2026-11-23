# Galerie-Metadaten für Contao

Abweichende Metadaten für einzelne Bilder im Core-Inhaltselement **Galerie** – für Contao **4.13** und **5.7**.

Titel, Alternativtext, Link, Bildunterschrift und der wichtige Teil eines Bildes stammen normalerweise aus der Dateiverwaltung und gelten überall gleich. Mit diesem Bundle lassen sie sich in einer bestimmten Galerie überschreiben, ohne die Datei selbst anzufassen. Alle anderen Galerien, Bildelemente und Module sehen weiterhin die Originalwerte.

## Installation

```bash
composer require schachbulle/contao-galeriemetadaten-bundle
```

Danach die Datenbank aktualisieren (Contao Manager oder `vendor/bin/contao-console contao:migrate`). Es kommt genau eine Spalte hinzu: `tl_content.galeriemetadaten`.

## Bedienung

1. Galerie-Element wie gewohnt anlegen, Bilder oder Ordner auswählen, **speichern**.
2. Unter der Bildauswahl erscheint der Abschnitt **„Metadaten nur für diese Galerie“** mit einem aufklappbaren Block je Bild (Vorschaubild und Dateipfad).
3. Bild aufklappen und nur die Felder ausfüllen, die abweichen sollen. Die Originalwerte aus der Dateiverwaltung stehen grau als Platzhalter im Feld.
4. Speichern. Bilder mit Abweichungen sind mit „angepasst“ markiert und bleiben aufgeklappt.

| Eingabe | Wirkung |
| --- | --- |
| Feld leer | Wert aus der Dateiverwaltung wird verwendet |
| Text | ersetzt den Wert nur in dieser Galerie |
| `{{empty}}` | leert den Wert in dieser Galerie (z. B. Bildunterschrift ausblenden) |

Welche Felder angeboten werden, richtet sich nach der Dateiverwaltung der jeweiligen Contao-Fassung (`FilesModel::getMetaFields()`), z. B. zusätzlich die Lizenz-URL.

### Wichtiger Teil

Unter den Feldern steht eine größere Vorschau des Bildes. Mit gedrückter Maustaste (oder mit Stift und Finger) lässt sich darauf ein Rahmen um den Bereich ziehen, der beim Beschneiden sichtbar bleiben soll.

* **Gestrichelter Rahmen:** wichtiger Teil aus der Dateiverwaltung (falls gesetzt).
* **Orangefarbener Rahmen:** abweichender wichtiger Teil für diese Galerie.
* **„Wichtigen Teil zurücksetzen“** entfernt die Abweichung; ein bloßer Klick ohne Ziehen ändert nichts.

Der wichtige Teil wirkt nur, wenn die Galerie eine Bildgröße **mit Beschnitt** verwendet (z. B. „Exaktes Format“ oder eine Bildgröße mit Modus „crop“). Bei proportional verkleinerten Bildern gibt es nichts zu beschneiden.

### Grenze für große Galerien

Jedes Bild erzeugt im Backend zwei Vorschaubilder und mehrere Eingabefelder. Damit Galerien mit Hunderten Bildern die Bearbeitungsmaske nicht lahmlegen, gibt es eine Obergrenze:

* **System → Einstellungen → Galerie-Metadaten → „Bearbeitbar bis Bilderzahl“**
* Leer: 50 Bilder (Voreinstellung). `0`: keine Grenze.

Enthält eine Galerie mehr Bilder, zeigt das Element statt der Eingabefelder einen Hinweis mit Bilderzahl und Grenze. Bereits gespeicherte Abweichungen bleiben dabei erhalten – auch wenn das Element erneut gespeichert wird – und werden im Frontend weiter ausgegeben.

### Ein Bild in mehreren Galerien

Ein Bild kann in beliebig vielen Galerien stecken, jede mit eigenen Abweichungen. Diese gehören zum jeweiligen Galerie-Element, nicht zur Datei. Auch mehrere Galerien mit demselben Bild auf **einer** Seite zeigen jeweils nur ihre eigenen Werte: Nach jeder Galerie werden die Abweichungen zurückgenommen, bevor die nächste ausgegeben wird. Galerien ohne Abweichungen, Bildelemente und alle übrigen Ausgaben sehen die Werte der Dateiverwaltung.

### Was passiert beim Umsortieren, Austauschen, Verschieben?

Die Abweichungen werden nach der **UUID der Datei** gespeichert, nicht nach der Position in der Galerie.

* **Umsortieren** der Galerie (manuell, nach Name, Datum, Zufall): ohne Einfluss.
* **Verschieben oder Umbenennen** der Datei in der Dateiverwaltung: ohne Einfluss, die UUID bleibt.
* **Bild aus der Auswahl entfernen**: Seine Abweichung wird nicht mehr ausgegeben und verschwindet beim nächsten Speichern des Elements.
* **Neues Bild hinzufügen**: Es erscheint im Abschnitt, sobald das Element einmal gespeichert wurde.
* **Element kopieren**: Die Abweichungen werden mitkopiert, ebenso erfassen Versionen und Wiederherstellen sie, weil sie Teil des Inhaltselements sind.

### Sprachen

Eine Abweichung gilt für die Sprache der Seite, auf der die Galerie steht, einschließlich der Fallback-Sprache der Wurzelseite. Gibt es für die Seitensprache in der Dateiverwaltung keinen Eintrag, werden die übrigen Felder aus dem Eintrag übernommen, den Contao sonst verwendet hätte – nur die ausgefüllten Felder ändern sich.

## Rechte

Das Feld ist wie die übrigen Felder von `tl_content` ein *ausgeschlossenes* Feld. Redakteure, die keine Administratoren sind, brauchen in ihrer Benutzergruppe unter **Erlaubte Felder** den Eintrag *Inhaltselemente → Abweichende Metadaten*.

## Technik

Contao hält je Anfrage genau eine Instanz eines Modells pro Datensatz (Modell-Register). Vor der Ausgabe einer Galerie werden die Spalten `meta` und `importantPartX/Y/Width/Height` der betroffenen `FilesModel`-Instanzen im Speicher angepasst – ohne zu speichern –, nach der Ausgabe wieder zurückgesetzt. Der Bildbaukasten des Cores liest die Metadaten über `FilesModel::getMetadata()`, die Bildfabrik den wichtigen Teil über `FilesModel::findByPath()`; beide sehen dabei die angepassten Werte. Weil der wichtige Teil in die Berechnung des Ausschnitts eingeht, entsteht für die Galerie eine eigene Bilddatei in `assets/images`. Sortierung, Seitenumbruch, Lightbox und Templates bleiben vollständig die des Cores.

| | Contao 4.13 | Contao 5.7 |
| --- | --- | --- |
| Galerie des Cores | `Contao\ContentGallery` | `ImagesController` (Typen `image` und `gallery`) |
| Ersatz durch das Bundle | `Element\ContentGalerie` über `$GLOBALS['TL_CTE']` | `Controller\GalerieController`, nur Typ `gallery`, Priorität 10 |
| Templates | `ce_gallery` / `gallery_default` unverändert | `content_element/gallery` unverändert |

Hat ein anderes Bundle unter 4.13 die Galerie bereits ersetzt, lässt dieses Bundle dessen Eintrag stehen; die Abweichungen wirken dann nicht.

## Entwicklung

```bash
vendor/bin/phpunit
```

Die Tests prüfen die Zusammenführung der Metadaten und die Prüfung des wichtigen Teils (`tests/Galerie/UeberschreiberTest.php`). In den lokalen Testinstallationen legt `galeriemetadaten-pruefstand.php` eine Prüfseite mit drei Galerien über dieselben Bilder an – zwei mit unterschiedlichen Abweichungen für dasselbe Bild, eine ohne. Bild 1 hat vier farbige Viertel; in Galerie A verschiebt ein abweichender wichtiger Teil den Ausschnitt, was sich an der Farbe des beschnittenen Bildes ablesen lässt.

## Lizenz

LGPL-3.0-or-later
