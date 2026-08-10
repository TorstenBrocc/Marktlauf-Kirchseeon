<?php
/**
 * Rechnungs-Begleitmail — Vorlagenpflege.
 * Dünner Wrapper um die gemeinsame Anschreiben-Seite, aber ohne Empfänger, Anhänge und
 * Versandknopf: diese Mail geht je Rechnung von „(Ab-)Rechnungen" raus (ein Empfänger aus den
 * Sponsor-Adressen, Anhänge fest). Hier wird nur der Text gepflegt.
 * Spec: intern/sponsoren-anschreiben-seiten-spec.md · intern/sponsoring-rechnung-spec.md
 */

declare(strict_types=1);

$slug   = 'rechnung';
$titel  = 'Rechnungs-Begleitmail';
$navKey = 'rechnungsmail';

$mitEmpfaenger = false;
$mitAnhaenge   = false;
$mitVersand    = false;
$hinweisHtml   = '<p style="margin:0;font-size:0.9rem;line-height:1.6">'
    . '<strong>Hier wird nur der Text gepflegt — gesendet wird nichts.</strong> Die Begleitmail geht'
    . ' je Rechnung über <a href="rechnungen.php">(Ab-)Rechnungen</a> raus, mit „An Sponsor senden".'
    . ' Empfänger ist dort eine hinterlegte Adresse des Sponsors, <strong>kassier@ steht in Kopie</strong>,'
    . ' und angehängt sind automatisch das Rechnungs-PDF sowie die Sponsoring-Bedingungen.'
    . ' Die Vorschau rechts rechnet mit Beispielwerten (Muster GmbH, 05-2026) —'
    . ' beim Versand stehen dort die echten Zahlen der jeweiligen Rechnung.'
    . '</p>';

require __DIR__ . '/_anschreiben_seite.php';
