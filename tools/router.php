<?php

declare(strict_types=1);

/*
 * Router für den eingebauten PHP-Webserver beim Prüfen in den lokalen
 * Testinstallationen (nicht Teil des ausgelieferten Bundles).
 *
 * Vorhandene Dateien (Bilder, Skripte, assets) liefert der Server selbst aus,
 * alles andere geht an Contaos index.php – auch Adressen mit Punkt wie
 * „galeriemetadaten.html“, die der eingebaute Server sonst als Datei sucht.
 *
 * Aufruf: php -S 127.0.0.1:8191 -t <contao>/public tools/router.php
 */

$wurzel = $_SERVER['DOCUMENT_ROOT'] ?? '';
$pfad = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ('' !== $wurzel && '/' !== $pfad && is_file($wurzel.$pfad)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $wurzel.'/index.php';

require $wurzel.'/index.php';
