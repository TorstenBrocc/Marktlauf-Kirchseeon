<?php
/**
 * Liefert ein realistisches Ergebnis-Array als Platzhalter für die Raceresult-API.
 *
 * Interface für den späteren echten Client:
 *   // src/raceresult_client.php
 *   function raceResultFetch(int $eventId, int $listId): array
 *   // gibt dasselbe Array-Shape zurück wie raceResultMock()
 *
 * Felder entsprechen dem, was die Raceresult Simple-API als JSON ausgibt,
 * bereinigt auf die für LLM-Prompts relevanten Größen.
 */
function raceResultMock(): array
{
    return [
        'event' => [
            'name'  => 'Marktlauf Kirchseeon 2026',
            'datum' => '2026-09-20',
            'ort'   => 'Kirchseeon, Westring 6',
        ],
        'gesamt' => [
            'teilnehmer'        => 412,
            'finisher'          => 398,
            'laeufernationen'   => 3,
            'wetter'            => 'sonnig, 22 °C, leichte Brise',
        ],
        'rennen' => [
            [
                'kategorie'   => 'Bambini 500 m',
                'startzeit'   => '09:00',
                'teilnehmer'  => 64,
                'sieger'      => ['name' => 'Lukas Brenner',     'zeit' => '2:14', 'verein' => 'ASV Kirchseeon'],
                'siegerin'    => ['name' => 'Emma Huber',        'zeit' => '2:21', 'verein' => 'TSV Ebersberg'],
            ],
            [
                'kategorie'   => 'Schüler 1000 m',
                'startzeit'   => '09:30',
                'teilnehmer'  => 89,
                'sieger'      => ['name' => 'Noah Maier',        'zeit' => '3:42', 'verein' => 'LG Markt Schwaben'],
                'siegerin'    => ['name' => 'Lena Gruber',       'zeit' => '3:58', 'verein' => 'ASV Kirchseeon'],
            ],
            [
                'kategorie'   => 'Schüler 2000 m',
                'startzeit'   => '09:30',
                'teilnehmer'  => 73,
                'sieger'      => ['name' => 'Felix Schmid',      'zeit' => '7:05', 'verein' => 'TSV Grafing'],
                'siegerin'    => ['name' => 'Sophie Wagner',     'zeit' => '7:44', 'verein' => 'TV Rosenheim'],
            ],
            [
                'kategorie'   => 'Elite 5 km',
                'startzeit'   => '10:00',
                'teilnehmer'  => 118,
                'sieger'      => ['name' => 'Jonas Berger',      'zeit' => '17:23', 'verein' => 'LC Penzberg'],
                'siegerin'    => ['name' => 'Anna-Lena Roth',    'zeit' => '19:41', 'verein' => 'TSV Erding'],
                'ak_sieger'   => [
                    ['ak' => 'M30', 'name' => 'Markus Bauer',   'zeit' => '17:58'],
                    ['ak' => 'M40', 'name' => 'Stefan Huber',   'zeit' => '18:34'],
                    ['ak' => 'M50', 'name' => 'Werner Klein',   'zeit' => '19:12'],
                    ['ak' => 'W30', 'name' => 'Julia Fischer',  'zeit' => '20:05'],
                    ['ak' => 'W40', 'name' => 'Sabine Müller',  'zeit' => '20:48'],
                ],
            ],
            [
                'kategorie'   => 'Elite 10 km',
                'startzeit'   => '10:00',
                'teilnehmer'  => 68,
                'sieger'      => ['name' => 'Tobias Keller',     'zeit' => '36:12', 'verein' => 'LG Stadtwerke München'],
                'siegerin'    => ['name' => 'Marie Hoffmann',    'zeit' => '40:07', 'verein' => 'VfL Waldkraiburg'],
                'ak_sieger'   => [
                    ['ak' => 'M40', 'name' => 'Christian Wolf',  'zeit' => '37:45'],
                    ['ak' => 'M50', 'name' => 'Harald Schuster', 'zeit' => '39:21'],
                    ['ak' => 'W30', 'name' => 'Kathrin Becker',  'zeit' => '41:30'],
                ],
            ],
        ],
        'highlight' => 'Neuer Streckenrekord 10 km: Tobias Keller unterbot den alten Rekord um 23 Sekunden.',
    ];
}
