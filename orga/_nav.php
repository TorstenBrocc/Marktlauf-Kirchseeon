<?php
/**
 * Modul-Registry — Single Source of Truth für Orga-Navigation UND Dashboard-Kacheln.
 *
 * Jedes Modul wird hier GENAU EINMAL eingetragen und erscheint dadurch automatisch
 *   - in der Seitenleiste (`_sidebar.php`) und
 *   - als Status-Kachel auf dem Dashboard (`index.php`).
 * So können Sidebar und Dashboard nicht mehr auseinanderdriften; ein neues Werkzeug
 * kostet einen Array-Eintrag, nicht zwei gepflegte Stellen.
 *
 * Felder je Eintrag:
 *   key    string    aktiver Menüpunkt-Schlüssel (= $activeNav)
 *   label  string    Anzeigename
 *   href   ?string   Ziel-Datei; null = deaktiviert (kein Link, nur Hinweis)
 *   admin  bool      nur für Admins sichtbar (default false)
 *   badge  ?string   kleines Label rechts (z. B. Phase-Kennzeichnung)
 *   tile   bool      als Dashboard-Kachel zeigen (default true; false = nur Sidebar)
 *   kpi    ?callable fn(PDO $pdo): array{value:string, label:string, signal:string}
 *                    Optional. `signal` ∈ {'ok','attention','neutral'}. Fehlt die
 *                    Closure, ist die Kachel ein reiner Absprung-Link. Die Closure
 *                    wird ausschließlich vom Dashboard aufgerufen (die Sidebar bleibt
 *                    DB-frei) und dort in try/catch gekapselt — eine fehlende Tabelle
 *                    liefert dann einfach keine Kennzahl statt eines Fehlers.
 *
 * @return array<int, array<string, mixed>>
 */

declare(strict_types=1);

return [
    [
        'key'   => 'dashboard',
        'label' => 'Dashboard',
        'href'  => 'index.php',
        'tile'  => false, // ist die aktuelle Seite — keine Selbst-Kachel
    ],
    [
        'key'   => 'helfer',
        'label' => 'Helfer',
        'href'  => 'helfer.php',
        'kpi'   => static function (PDO $pdo): array {
            $anzahl = (int) $pdo->query('SELECT COUNT(*) FROM helfer')->fetchColumn();
            $neu    = (int) $pdo->query("SELECT COUNT(*) FROM helfer WHERE status = 'neu'")->fetchColumn();
            return [
                'value'  => (string) $anzahl,
                'label'  => 'Anmeldungen' . ($neu > 0 ? " · {$neu} neu" : ''),
                'signal' => $neu > 0 ? 'attention' : 'ok',
            ];
        },
    ],
    [
        'key'   => 'schichten',
        'label' => 'Einsatzplan',
        'href'  => 'schichten.php',
        'kpi'   => static function (PDO $pdo): array {
            $bedarf    = (int) $pdo->query('SELECT COALESCE(SUM(bedarf), 0) FROM schichten')->fetchColumn();
            $zugeteilt = (int) $pdo->query('SELECT COUNT(*) FROM schicht_zuteilung')->fetchColumn();
            if ($bedarf === 0) {
                return ['value' => '0', 'label' => 'Schichten angelegt', 'signal' => 'neutral'];
            }
            $offen = max(0, $bedarf - $zugeteilt);
            return [
                'value'  => "{$zugeteilt}/{$bedarf}",
                'label'  => 'Plätze besetzt' . ($offen > 0 ? " · {$offen} offen" : ''),
                'signal' => $offen > 0 ? 'attention' : 'ok',
            ];
        },
    ],
    [
        'key'   => 'helfer_draht',
        'label' => 'Helfer-Draht',
        'href'  => 'helfer_verzeichnis.php',
        'kpi'   => static function (PDO $pdo): array {
            $best = (int) $pdo->query("SELECT COUNT(*) FROM helfer WHERE status = 'bestaetigt'")->fetchColumn();
            $notfall = 0;
            try {
                $notfall = (int) $pdo->query("SELECT COUNT(*) FROM briefings WHERE sichtbar = 1 AND prioritaet = 'notfall'")->fetchColumn();
            } catch (PDOException $e) { /* Tabelle evtl. noch nicht da */ }
            return [
                'value'  => (string) $best,
                'label'  => 'erreichbare Helfer' . ($notfall > 0 ? " · {$notfall} Notfall-Info" : ''),
                'signal' => $notfall > 0 ? 'attention' : 'neutral',
            ];
        },
    ],
    [
        'key'   => 'sponsoren',
        'label' => 'Sponsoren',
        'href'  => 'sponsoren.php',
        'kpi'   => static function (PDO $pdo): array {
            $anzahl = (int) $pdo->query('SELECT COUNT(*) FROM sponsors')->fetchColumn();
            $summe  = (float) $pdo->query("SELECT COALESCE(SUM(summe), 0) FROM sponsors WHERE status IN ('zugesagt', 'bezahlt')")->fetchColumn();
            return [
                'value'  => (string) $anzahl,
                'label'  => 'Sponsoren' . ($summe > 0 ? ' · ' . number_format($summe, 0, ',', '.') . ' € zugesagt' : ''),
                'signal' => 'neutral',
            ];
        },
    ],
    [
        'key'   => 'sponsor_briefe',
        'label' => 'Sponsorenbriefe',
        'href'  => 'sponsor_briefe.php',
        'kpi'   => static function (PDO $pdo): array {
            $offen = (int) $pdo->query("SELECT COUNT(*) FROM sponsor_versand_queue WHERE status = 'offen'")->fetchColumn();
            return [
                'value'  => (string) $offen,
                'label'  => 'offen in Versand-Queue',
                'signal' => $offen > 0 ? 'attention' : 'ok',
            ];
        },
    ],
    [
        'key'   => 'social_media',
        'label' => 'Social Media',
        'href'  => 'social_orchestrator.php',
        'kpi'   => static function (PDO $pdo): array {
            $entwuerfe = (int) $pdo->query("SELECT COUNT(*) FROM post_race_contents WHERE status = 'draft'")->fetchColumn();
            return [
                'value'  => (string) $entwuerfe,
                'label'  => 'Entwürfe',
                'signal' => $entwuerfe > 0 ? 'attention' : 'ok',
            ];
        },
    ],
    [
        'key'   => 'dateien',
        'label' => 'Dateien',
        'href'  => 'dateien.php',
        'kpi'   => static function (PDO $pdo): array {
            $anzahl = (int) $pdo->query('SELECT COUNT(*) FROM dateien')->fetchColumn();
            return [
                'value'  => (string) $anzahl,
                'label'  => 'Dateien abgelegt',
                'signal' => 'neutral',
            ];
        },
    ],
    [
        'key'   => 'ci',
        'label' => 'CI & Design',
        'href'  => 'ci.php',
        // Kein KPI: reine Referenz-Seite → Kachel ist ein Absprung-Link.
    ],
    [
        'key'   => 'live_ticker',
        'label' => 'Live-Ticker',
        'href'  => null, // noch nicht gebaut, nicht klickbar
    ],
    [
        'key'   => 'benutzer',
        'label' => 'Benutzerverwaltung',
        'href'  => 'benutzer.php',
        'admin' => true,
        'tile'  => false, // Admin-Werkzeug — bleibt Sidebar-only, keine Kachel
    ],
    [
        'key'   => 'einstellungen',
        'label' => 'Einstellungen',
        'href'  => 'einstellungen.php',
        'admin' => true,
        'tile'  => false,
    ],
];
