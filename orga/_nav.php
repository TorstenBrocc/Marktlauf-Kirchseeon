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
 *   section ?string  Sidebar-Abschnitts-Überschrift; aufeinanderfolgende Items mit
 *                    gleichem Wert werden unter EINER Überschrift gruppiert. Ohne
 *                    section erscheint das Item ohne Überschrift (z. B. Dashboard).
 *   group  ?string   Optionale DRITTE Ebene ÜBER der section — für Blöcke, die selbst
 *                    schon in Abschnitte zerfallen (aktuell nur SPONSOREN mit DATEN +
 *                    SPONSOREN-ANSCHREIBEN). Ohne group bleibt ein Block zweistufig;
 *                    die Ebene ist rein optisch, sie ändert nichts an den Kacheln.
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
        'label' => 'Cockpit',
        'href'  => 'index.php',
        'tile'  => false, // ist die aktuelle Seite — keine Selbst-Kachel
    ],
    [
        'key'     => 'helfer',
        'label'   => 'Helfer-Übersicht',
        'section' => 'HELFER-ORGA',
        'href'    => 'helfer.php',
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
        'key'     => 'schichten',
        'label'   => 'Einsatzplan',
        'section' => 'HELFER-ORGA',
        'href'    => 'schichten.php',
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
        'key'     => 'beitraege',
        'label'   => 'Kuchen & Sonstiges',
        'section' => 'HELFER-ORGA',
        'href'    => 'beitraege.php',
        'kpi'   => static function (PDO $pdo): array {
            $kuchen    = (int) $pdo->query("SELECT COUNT(*) FROM helfer_beitrag WHERE typ = 'kuchen'")->fetchColumn();
            $sonstiges = (int) $pdo->query("SELECT COUNT(*) FROM helfer_beitrag WHERE typ = 'sonstiges'")->fetchColumn();
            return [
                'value'  => (string) $kuchen,
                'label'  => 'Kuchen-Zusagen' . ($sonstiges > 0 ? " · {$sonstiges}× sonstiges" : ''),
                'signal' => 'neutral',
            ];
        },
    ],
    [
        'key'     => 'helfer_draht',
        'label'   => 'Helfer-Draht',
        'section' => 'HELFER-ORGA',
        'href'    => 'helfer_verzeichnis.php',
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
    // --- SPONSOREN · DATEN ------------------------------------------------
    [
        'key'     => 'sponsoren',
        'label'   => 'Stammdaten',
        'group'   => 'SPONSOREN',
        'section' => 'DATEN',
        'href'    => 'sponsoren.php',
        'kpi'   => static function (PDO $pdo): array {
            $anzahl = (int) $pdo->query('SELECT COUNT(*) FROM sponsors')->fetchColumn();
            $summe  = (float) $pdo->query("SELECT COALESCE(SUM(summe), 0) FROM sponsors WHERE status IN ('zugesagt', 'bestaetigt', 'abgerechnet', 'bezahlt')")->fetchColumn();
            return [
                'value'  => (string) $anzahl,
                'label'  => 'Sponsoren' . ($summe > 0 ? ' · ' . number_format($summe, 0, ',', '.') . ' € zugesagt' : ''),
                'signal' => 'neutral',
            ];
        },
    ],
    [
        'key'     => 'leistungen',
        'label'   => 'Paketleistungen',
        'group'   => 'SPONSOREN',
        'section' => 'DATEN',
        'href'    => 'leistungen.php',
    ],
    [
        'key'     => 'rechnungen',
        'label'   => '(Ab-)Rechnungen',
        'group'   => 'SPONSOREN',
        'section' => 'DATEN',
        'href'    => 'rechnungen.php',
        'kpi'   => static function (PDO $pdo): array {
            $entwuerfe = (int) $pdo->query("SELECT COUNT(*) FROM sponsor_rechnungen WHERE status = 'entwurf'")->fetchColumn();
            return [
                'value'  => (string) $entwuerfe,
                'label'  => 'Entwürfe ohne Nummer',
                'signal' => $entwuerfe > 0 ? 'attention' : 'ok',
            ];
        },
    ],

    // --- SPONSOREN · ANSCHREIBEN -------------------------------------------
    // Eine Seite je Briefvorlage (Empfänger + Brief + Anhänge + Versand an einem Ort).
    // Spec: intern/sponsoren-anschreiben-seiten-spec.md
    [
        'key'     => 'erstanschreiben',
        'label'   => 'Erstanschreiben',
        'group'   => 'SPONSOREN',
        'section' => 'SPONSOREN-ANSCHREIBEN',
        'href'    => 'erstanschreiben.php',
        'kpi'   => static function (PDO $pdo): array {
            $offen = (int) $pdo->query("SELECT COUNT(*) FROM sponsors WHERE status = 'neu' AND kein_kontakt = 0")->fetchColumn();
            // Die Versand-Queue ist typübergreifend; ihre Fehler hingen bisher an der
            // Kachel „Sponsorenbriefe". Sie hängen jetzt hier, wo der Bulk-Versand läuft.
            $fehler = 0;
            try {
                $fehler = (int) $pdo->query("SELECT COUNT(*) FROM sponsor_versand_queue WHERE status = 'fehler'")->fetchColumn();
            } catch (PDOException $e) { /* Queue evtl. noch nicht da */ }
            return [
                'value'  => (string) $offen,
                'label'  => 'noch nicht angeschrieben' . ($fehler > 0 ? " · {$fehler} Versand-Fehler" : ''),
                'signal' => ($offen > 0 || $fehler > 0) ? 'attention' : 'ok',
            ];
        },
    ],
    [
        'key'     => 'folgeanschreiben',
        'label'   => 'Folgeanschreiben',
        'group'   => 'SPONSOREN',
        'section' => 'SPONSOREN-ANSCHREIBEN',
        'href'    => 'folgeanschreiben.php',
        'kpi'   => static function (PDO $pdo): array {
            // Bestandssponsor = jeder ohne ausdrückliches „Kein Kontakt" (Definition TT
            // 2026-08-10); eine Absage schließt ein erneutes Anschreiben nicht aus.
            $anzahl = (int) $pdo->query(
                'SELECT COUNT(*) FROM sponsors WHERE kein_kontakt = 0'
            )->fetchColumn();
            return [
                'value'  => (string) $anzahl,
                'label'  => 'ansprechbare Sponsoren',
                'signal' => 'neutral',
            ];
        },
    ],
    [
        'key'     => 'bestaetigungen',
        'label'   => 'Bestätigung Sponsoring',
        'group'   => 'SPONSOREN',
        'section' => 'SPONSOREN-ANSCHREIBEN',
        'href'    => 'bestaetigungen.php',
        'kpi'   => static function (PDO $pdo): array {
            $offen = (int) $pdo->query("SELECT COUNT(*) FROM sponsors WHERE status = 'zugesagt'")->fetchColumn();
            return [
                'value'  => (string) $offen,
                'label'  => 'Zusagen ohne Bestätigung',
                'signal' => $offen > 0 ? 'attention' : 'ok',
            ];
        },
    ],
    [
        'key'     => 'freier_brief',
        'label'   => 'Freier Brief',
        'group'   => 'SPONSOREN',
        'section' => 'SPONSOREN-ANSCHREIBEN',
        'href'    => 'freier_brief.php',
        'tile'    => false, // Ad-hoc-Werkzeug ohne Kennzahl — flutet sonst das Cockpit
    ],
    [
        'key'     => 'bedingungen',
        'label'   => 'Bedingungen nachreichen',
        'group'   => 'SPONSOREN',
        'section' => 'SPONSOREN-ANSCHREIBEN',
        'href'    => 'bedingungen.php',
        'tile'    => false, // Sonderfall Altfälle — Sidebar genügt
    ],
    [
        'key'     => 'rechnungsmail',
        'label'   => 'Rechnungs-Begleitmail',
        'group'   => 'SPONSOREN',
        'section' => 'SPONSOREN-ANSCHREIBEN',
        'href'    => 'rechnungsmail.php',
        'tile'    => false, // reine Vorlagenpflege, kein Arbeitsvorrat — Versand läuft über (Ab-)Rechnungen
    ],
    [
        'key'     => 'anschreiben_einstellungen',
        'label'   => 'Einstellungen (Anschreiben)',
        'group'   => 'SPONSOREN',
        'section' => 'SPONSOREN-ANSCHREIBEN',
        'href'    => 'anschreiben_einstellungen.php',
        'tile'    => false, // Einstellungen sind kein Arbeitsvorrat
    ],
    [
        'key'     => 'vereine',
        'label'   => 'Vereine & Laufevents',
        'section' => 'VEREINE & LAUFEVENTS',
        'href'    => 'vereine.php',
        'kpi'   => static function (PDO $pdo): array {
            $anzahl = (int) $pdo->query('SELECT COUNT(*) FROM vereine')->fetchColumn();
            $events = (int) $pdo->query("SELECT COUNT(*) FROM vereine WHERE kategorie = 'laufevent'")->fetchColumn();
            return [
                'value'  => (string) $anzahl,
                'label'  => 'Kontakte' . ($events > 0 ? " · {$events} Laufevents" : ''),
                'signal' => 'neutral',
            ];
        },
    ],
    [
        'key'     => 'vereine_briefe',
        'label'   => 'Vereins-Anschreiben',
        'section' => 'VEREINE & LAUFEVENTS',
        // Sidebar führt direkt in den Editor, die Cockpit-Kachel bewusst auf die
        // Vereinsliste — dort werden zuerst die Empfänger gewählt.
        'href'       => 'vereine_briefe.php',
        'href_tile'  => 'vereine.php',
        'kpi'   => static function (PDO $pdo): array {
            $offen  = (int) $pdo->query("SELECT COUNT(*) FROM verein_versand_queue WHERE status = 'offen'")->fetchColumn();
            $fehler = (int) $pdo->query("SELECT COUNT(*) FROM verein_versand_queue WHERE status = 'fehler'")->fetchColumn();
            $label = 'offen in Versand-Queue';
            if ($fehler > 0) { $label .= " · {$fehler} Fehler"; }
            return [
                'value'  => (string) $offen,
                'label'  => $label,
                'signal' => ($offen > 0 || $fehler > 0) ? 'attention' : 'ok',
            ];
        },
    ],
    [
        'key'     => 'social_media',
        'label'   => 'Social-Media',
        'section' => 'KOMMUNIKATION',
        'href'    => 'social_orchestrator.php',
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
        'key'     => 'newsletter',
        'label'   => 'Newsletter',
        'section' => 'KOMMUNIKATION',
        'href'    => 'newsletter.php',
        // Fakten → KI-Newsletter → Brevo-Entwurf. Kein KPI → reiner Absprung-Link.
    ],
    [
        'key'     => 'vorlagen',
        'label'   => 'Grafik-Vorlagen',
        'section' => 'KOMMUNIKATION',
        'href'    => 'vorlagen.php',
    ],
    [
        'key'     => 'live_ticker',
        'label'   => 'Live-Ticker',
        'section' => 'KOMMUNIKATION',
        'href'    => 'ticker.php',
        'kpi'     => static function (PDO $pdo): array {
            $aktiv = (int) $pdo->query(
                "SELECT COUNT(*) FROM ticker_posts WHERE aktiv = 1"
            )->fetchColumn();
            return [
                'value'  => (string) $aktiv,
                'label'  => $aktiv === 1 ? 'aktive Meldung' : 'aktive Meldungen',
                'signal' => $aktiv > 0 ? 'ok' : 'neutral',
            ];
        },
    ],
    [
        'key'     => 'dateien',
        'label'   => 'Dateien',
        'section' => 'ABLAGE',
        'href'    => 'dateien.php',
        'kpi'   => static function (PDO $pdo): array {
            // Dateien liegen im geteilten Google-Laufwerk (kein DB-Zähler mehr; kein
            // API-Call pro Seitenaufruf). Kachel verweist nur noch auf den Browser.
            return [
                'value'  => '↗',
                'label'  => 'im Google-Laufwerk',
                'signal' => 'neutral',
            ];
        },
    ],
    [
        'key'     => 'prompt_bibliothek',
        'label'   => 'Prompt-Bibliothek',
        'section' => 'ABLAGE',
        'href'    => 'prompt_bibliothek.php',
        'kpi'   => static function (PDO $pdo): array {
            $anzahl = (int) $pdo->query('SELECT COUNT(*) FROM prompts')->fetchColumn();
            return [
                'value'  => (string) $anzahl,
                'label'  => 'Prompts gespeichert',
                'signal' => 'neutral',
            ];
        },
    ],
    [
        'key'     => 'benutzer',
        'label'   => 'Benutzerverwaltung',
        'section' => 'ADMIN',
        'href'    => 'benutzer.php',
        'admin' => true,
        'tile'  => false, // Admin-Werkzeug — bleibt Sidebar-only, keine Kachel
    ],
    [
        'key'     => 'ci',
        'label'   => 'CI & Design',
        'section' => 'ADMIN',
        'href'    => 'ci.php',
        // Für alle Orga sichtbar (kein admin-Flag), bleibt Dashboard-Kachel; steht in
        // der Sidebar aber optisch im ADMIN-Abschnitt. Kein KPI → Absprung-Link.
    ],
    [
        'key'     => 'design_system',
        'label'   => 'Design-System',
        'section' => 'ADMIN',
        'href'    => 'design_system.php',
        // Navigierbarer DS-Browser (Marke/Farben/Typo/…). Wie ci.php für alle Orga
        // sichtbar, ohne KPI. Spec: intern/design-system-integration-spec.md
    ],
    [
        'key'     => 'einstellungen',
        'label'   => 'Einstellungen',
        'section' => 'ADMIN',
        'href'    => 'einstellungen.php',
        'admin' => true,
        'tile'  => false,
    ],
];
