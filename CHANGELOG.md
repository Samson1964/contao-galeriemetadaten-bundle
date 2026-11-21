# Changelog

## Version 1.0.0 (2026-11-21)

* Add: Abweichende Metadaten (Titel, Alternativtext, Link, Bildunterschrift; unter Contao 5 auch Lizenz) je Bild im Galerie-Element, nur für diese eine Galerie
* Add: Backend-Widget mit aufklappbarem Block je Bild, Vorschaubild und den Originalwerten als Platzhalter
* Add: Speicherung nach Datei-UUID – Umsortieren, Verschieben und Umbenennen der Bilder ändern nichts an den Zuordnungen
* Add: `{{empty}}` leert einen Wert ausdrücklich
* Add: Dasselbe Bild kann in beliebig vielen Galerien – auch auf derselben Seite – jeweils eigene Metadaten tragen
* Add: Unterstützung für Contao 4.13 (eigene Klasse anstelle von `ContentGallery`) und Contao 5.7 (eigener Controller auf Basis von `ImagesController`)
