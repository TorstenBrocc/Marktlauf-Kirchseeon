<?php
/**
 * Anhang-Plan je Briefvorlage — EINE Quelle für Anzeige und Versand.
 *
 * Vorher stand die Wahrheit an drei Stellen: die hartcodierten in_array-Listen in
 * `sendSponsorAnschreiben()`, ein Hinweis-Banner über dem Brief und eine Datei-Kachel
 * darunter (plus ein dritter Ausschnitt in der Versandleiste der Sponsorenliste). Wer eine
 * Stelle änderte, hatte die anderen gegen sich.
 *
 * Ab hier gilt: `sponsorAnhangPlan()` sagt, WAS an eine Vorlage gehängt wird —
 * `orga/_anhang_kachel.php` zeigt genau das an, `sendSponsorAnschreiben()` hängt genau das
 * an. Die Kachel kann damit nicht mehr etwas anderes behaupten als der Versand tut.
 *
 * Spec: intern/sponsoren-anschreiben-seiten-spec.md §3.3
 */

declare(strict_types=1);

require_once __DIR__ . '/google_drive.php';
require_once __DIR__ . '/logger.php';

/**
 * Welche Anhang-Gruppen hängen an welcher Vorlage?
 *
 * 'fest'  = nicht abwählbar (Haus-Konvention), wird mit 🔒 angezeigt
 * 'quelle' = 'bedingungen' | 'plakate' | 'bestaetigung_assets'
 *
 * @return array<int, array{id:string, titel:string, quelle:string, fest:bool}>
 */
function sponsorAnhangPlan(string $slug): array {
    $bedingungen = [
        'id'     => 'bedingungen',
        'titel'  => 'Sponsoring-Bedingungen',
        'quelle' => 'bedingungen',
        'fest'   => true,
    ];
    $plakate = [
        'id'     => 'plakat',
        'titel'  => 'Plakate',
        'quelle' => 'plakate',
        'fest'   => false,
    ];
    $assets = [
        'id'     => 'asset',
        'titel'  => 'Bestätigungs-Anhänge',
        'quelle' => 'bestaetigung_assets',
        'fest'   => false,
    ];

    return match ($slug) {
        'erstanschreiben', 'folgejahr', 'bedingungen' => [$bedingungen],
        // Der freie Brief bekommt die Bedingungen seit 2026-08-10 ebenfalls (Beschluss TT):
        // auch ein frei formulierter Brief ist eine Sponsoring-Ansprache.
        'frei'         => [$plakate, $bedingungen],
        'bestaetigung' => [$plakate, $assets, $bedingungen],
        default        => [],
    };
}

/** Hängt an dieser Vorlage überhaupt etwas? */
function sponsorHatAnhaenge(string $slug): bool {
    return sponsorAnhangPlan($slug) !== [];
}

/**
 * Anhang-Gruppen inkl. Dateiliste für die Anzeige — nur Metadaten aus dem Drive,
 * kein Download. Jede Gruppe bekommt zusätzlich einen Klartext-Hinweis, wenn der
 * Ordner fehlt oder leer ist: Ein stiller leerer Block wäre schlimmer als die Warnung,
 * weil dann eine Mail ohne erwarteten Anhang rausginge.
 *
 * @return array<int, array{id:string, titel:string, fest:bool, files:array<int,array<string,mixed>>, hinweis:string}>
 */
function sponsorAnhangGruppenAnzeige(PDO $pdo, string $slug): array {
    $gruppen = [];
    foreach (sponsorAnhangPlan($slug) as $g) {
        [$files, $hinweis] = sponsorAnhangDateien($pdo, $g['quelle']);
        $gruppen[] = [
            'id'      => $g['id'],
            'titel'   => $g['titel'],
            'fest'    => $g['fest'],
            'files'   => $files,
            'hinweis' => $hinweis,
        ];
    }
    return $gruppen;
}

/**
 * Dateien einer Anhang-Quelle listen (Metadaten).
 * @return array{0: array<int,array<string,mixed>>, 1: string} [Dateien, Hinweis bei Problem]
 */
function sponsorAnhangDateien(PDO $pdo, string $quelle): array {
    if (!driveConfigured()) {
        return [[], 'Google-Drive ist nicht verbunden — es können keine Anhänge geladen werden.'];
    }

    $folderId = null;
    $leerMsg  = '';
    $fehltMsg = '';

    switch ($quelle) {
        case 'plakate':
            $folderId = drivePlakatFolderId($pdo, driveRennJahr($pdo));
            $leerMsg  = 'Der festgelegte Plakate-Ordner ist leer — es werden keine Plakate angehängt.';
            $fehltMsg = 'Kein Plakate-Ordner festgelegt. Öffne unter „Dateien" den gewünschten Ordner '
                      . 'und klicke „📌 Als Plakate-Ordner".';
            break;
        case 'bestaetigung_assets':
            $folderId = driveBestaetigungAssetsFolderId($pdo);
            $leerMsg  = 'Der Bestätigungs-Anhang-Ordner ist leer.';
            $fehltMsg = 'Kein Bestätigungs-Anhang-Ordner festgelegt. Öffne unter „Dateien" den Ordner '
                      . '„Sponsoren-Bestätigung" und klicke „📎 Als Bestätigungs-Anhang-Ordner".';
            break;
        case 'bedingungen':
            require_once __DIR__ . '/sponsor_rotation.php';
            $folderId = driveFindFolder('_assets Sponsoren', sponsorDriveRootId($pdo));
            $leerMsg  = 'Im Ordner „_assets Sponsoren" liegt keine Datei mit „Bedingung" im Namen — '
                      . 'diese Mail geht ohne Bedingungen raus.';
            $fehltMsg = 'Der Drive-Ordner „_assets Sponsoren" wurde nicht gefunden — '
                      . 'diese Mail geht ohne Bedingungen raus.';
            break;
    }

    if ($folderId === null) {
        return [[], $fehltMsg];
    }

    try {
        $alle = driveListChildren($folderId);
    } catch (Throwable $e) {
        logError('sponsorAnhangDateien(' . $quelle . '): ' . $e->getMessage());
        return [[], 'Der Ordner konnte gerade nicht gelesen werden — bitte Seite neu laden.'];
    }

    $files = [];
    foreach ($alle as $f) {
        if (!empty($f['isFolder']) || ($f['id'] ?? '') === '') {
            continue;
        }
        // Bei den Bedingungen greift dieselbe Namensregel wie beim Versand
        // (sponsorBedingungenAnhang): nur Dateien mit „Bedingung" im Namen.
        if ($quelle === 'bedingungen' && stripos((string) $f['name'], 'bedingung') === false) {
            continue;
        }
        $files[] = $f;
    }

    return [$files, $files === [] ? $leerMsg : ''];
}
