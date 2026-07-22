<?php
/**
 * Zentraler Katalog der Helfer-Aufgaben (Zeitfenster + Beschreibung je Tag).
 * Einzige Quelle für das Anmeldeformular UND die serverseitige Validierung,
 * damit nur echte Katalog-Aufgaben gespeichert werden können.
 *
 * Termine bewusst identisch zum bisherigen Formular (Fr/Sa/So).
 */

declare(strict_types=1);

function helferAufgabenKatalog(): array
{
    return [
        '2026-09-18' => [
            'label' => 'Freitag · 18.09.2026 (Aufbau)',
            'aufgaben' => [
                ['key' => 'fr_wegfuehrung_frei',  'beschreibung' => 'Div. Unterstützung für die Wegführung', 'zeitfenster' => 'freie Verfügbarkeit'],
                ['key' => 'fr_wegfuehrung_nachm', 'beschreibung' => 'Div. Unterstützung für die Wegführung', 'zeitfenster' => 'Nachmittag nach Absprache'],
            ],
        ],
        '2026-09-19' => [
            'label' => 'Samstag · 19.09.2026 (Aufbau)',
            'aufgaben' => [
                ['key' => 'sa_ganztag',          'beschreibung' => 'Ganzer Tag', 'zeitfenster' => 'freie Verfügbarkeit'],
                ['key' => 'sa_alt_vorbereitung', 'beschreibung' => 'Alternativer Termin / übrige Vorbereitungen für die Wegführung', 'zeitfenster' => 'nach Absprache'],
                ['key' => 'sa_vereinsheim',      'beschreibung' => 'Vorbereitungen im Vereinsheim', 'zeitfenster' => 'nach Absprache'],
            ],
        ],
        '2026-09-20' => [
            'label' => 'Sonntag · 20.09.2026 (Renntag)',
            'aufgaben' => [
                ['key' => 'so_ganztag',            'beschreibung' => 'Ganzer Tag', 'zeitfenster' => 'freie Verfügbarkeit'],
                ['key' => 'so_aufbau_0700',        'beschreibung' => 'Aufbauarbeiten Start/Ziel', 'zeitfenster' => '07:00–10:00'],
                ['key' => 'so_aufbau_0800',        'beschreibung' => 'Aufbauarbeiten Start/Ziel', 'zeitfenster' => '08:00–10:00'],
                ['key' => 'so_startnummern',       'beschreibung' => 'Startnummernausgabe / Nachmeldungen', 'zeitfenster' => '08:00–10:00'],
                ['key' => 'so_streckenposten',     'beschreibung' => 'Streckenposten Versorgungsstationen Laufstrecke', 'zeitfenster' => '09:00–12:00'],
                ['key' => 'so_versorgung_startziel','beschreibung' => 'Betreuung / Aufbau Versorgungsstation Start/Ziel', 'zeitfenster' => '09:00–13:00'],
                ['key' => 'so_abbau',              'beschreibung' => 'Abbau Laufevent', 'zeitfenster' => '13:00–15:00'],
                ['key' => 'so_getraenke_0930',     'beschreibung' => 'ATSV Getränkeverkauf Hauptplatz', 'zeitfenster' => '09:30–12:00'],
                ['key' => 'so_getraenke_1200',     'beschreibung' => 'ATSV Getränkeverkauf Hauptplatz', 'zeitfenster' => '12:00–14:00'],
                ['key' => 'so_getraenke_1400',     'beschreibung' => 'ATSV Getränkeverkauf Hauptplatz', 'zeitfenster' => '14:00–16:30'],
            ],
        ],
    ];
}

/**
 * Katalog-Aufgabe per Key auflösen (inkl. tag). null wenn unbekannt.
 */
function helferAufgabeByKey(string $key): ?array
{
    foreach (helferAufgabenKatalog() as $tag => $day) {
        foreach ($day['aufgaben'] as $a) {
            if ($a['key'] === $key) {
                return $a + ['tag' => $tag];
            }
        }
    }
    return null;
}
