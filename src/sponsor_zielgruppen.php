<?php
/**
 * Zielgruppen je Anschreiben-Seite — wer steht auf welcher Seite zur Auswahl?
 *
 * Der Empfänger-Kopf (orga/_empfaenger_kopf.php) baut daraus sein Dropdown bzw. seine
 * Suchliste. Die Definition liegt hier zentral, damit „wen schreibe ich mit dieser Vorlage
 * an" eine beantwortbare Frage bleibt und nicht in fünf Seiten verstreut lebt.
 *
 * Lebenszyklus (src/sponsor_status.php):
 *   neu → angefragt → in_klaerung → zugesagt → bestaetigt → abgerechnet → bezahlt · abgelehnt
 *
 * Spec: intern/sponsoren-anschreiben-seiten-spec.md §4
 */

declare(strict_types=1);

require_once __DIR__ . '/sponsor_status.php';

/**
 * @return array<string, array{label:string, where:string, hinweis?:string}>
 *         Erster Eintrag = Vorauswahl der Seite.
 */
function sponsorZielgruppen(string $slug): array {
    return match ($slug) {
        // Erstkontakt läuft je Fördertopf anders (Sponsoring-Paket vs. Förderantrag vs. über
        // Dritte vs. reine Öffentlichkeitsarbeit). Erste Umschalt-Stufe der Anschreiben-
        // Oberflächen ist deshalb die Fördergruppe — dieselben vier Töpfe wie die Reiter in
        // den Stammdaten (SPONSOR_FOERDERGRUPPE, src/sponsor_status.php). Bewusst nur der
        // Empfänger-Filter: der passende Vorlagentext je Topf ist der nächste Schritt und
        // hier noch nicht verdrahtet. Die alten status-basierten Gruppen bleiben darunter
        // erhalten (Re-Kontakt „In Klärung"/„Wiedervorlage", topfübergreifend „Alle neuen").
        'erstanschreiben' => (static function (): array {
            $gruppen = [];
            foreach (SPONSOR_FOERDERGRUPPE as $key => $label) {
                $gruppen['fg_' . $key] = [
                    'label' => $label . ' — noch nicht angeschrieben',
                    // $key stammt aus einer Code-Konstante (Enum-Werte), kein Nutzereingabe.
                    'where' => "s.status = 'neu' AND s.foerdergruppe = '" . $key . "'",
                ];
            }
            if (isset($gruppen['fg_foerderantrag'])) {
                $gruppen['fg_foerderantrag']['hinweis'] =
                    'Fördermittelgeber/Kooperationspartner — kein Sponsoring-Paket. '
                    . 'Standardtext bitte entsprechend anpassen (eigene Vorlage je Fördergruppe folgt).';
            }
            $gruppen['neu'] = [
                'label' => 'Alle neuen — Fördergruppe egal',
                'where' => "s.status = 'neu'",
            ];
            $gruppen['in_klaerung'] = [
                'label' => 'In Klärung',
                'where' => "s.status = 'in_klaerung'",
            ];
            $gruppen['wiedervorlage'] = [
                'label' => 'Wiedervorlage fällig',
                'where' => "s.wiedervorlage IS NOT NULL AND s.wiedervorlage <= CURDATE()",
            ];
            return $gruppen;
        })(),
        'folgejahr' => [
            // Definition TT (2026-08-10): Bestandssponsor ist prinzipiell jeder Sponsor, der
            // nicht generell gesagt hat, dass er keinen Kontakt mehr will. Eine Absage in
            // einem Jahr schließt also nicht aus, im nächsten wieder gefragt zu werden —
            // nur das ausdrückliche „kein Kontakt" tut das.
            'bestand' => [
                'label' => 'Bestandssponsoren — alle ohne „Kein Kontakt"',
                'where' => 's.kein_kontakt = 0',
            ],
            'zugesagt_frueher' => [
                'label' => 'Nur frühere Zusagen',
                'where' => "s.status IN ('zugesagt','bestaetigt','abgerechnet','bezahlt')",
            ],
        ],
        'bestaetigung' => [
            'zugesagt' => [
                'label' => 'Zugesagt — Bestätigung offen',
                'where' => "s.status = 'zugesagt'",
            ],
        ],
        'bedingungen' => [
            'altfaelle' => [
                'label'   => 'Altfälle — angeschrieben ohne Bedingungen',
                'where'   => "s.gesendet_am IS NOT NULL",
                'hinweis' => 'Wer die Bedingungen schon bekommen hat, bitte unten abwählen — '
                           . 'das System kann das nachträglich nicht unterscheiden.',
            ],
        ],
        'frei' => [
            'alle' => [
                'label' => 'Alle Sponsoren',
                'where' => '1 = 1',
            ],
            'zugesagt' => [
                'label' => 'Nur Zugesagte',
                'where' => "s.status IN ('zugesagt','bestaetigt','abgerechnet','bezahlt')",
            ],
        ],
        default => [],
    };
}

/** Schlüssel der Vorauswahl (erste Zielgruppe der Seite). */
function sponsorZielgruppeDefault(string $slug): string {
    $zg = sponsorZielgruppen($slug);
    return $zg === [] ? '' : (string) array_key_first($zg);
}

/**
 * Kandidaten einer Zielgruppe laden — inklusive Zahl der versandfähigen Ansprechpartner.
 *
 * `kein_kontakt` wird NICHT weggefiltert: Wer gesperrt ist, soll sichtbar bleiben (mit
 * Stopp-Markierung), sonst sucht man später vergeblich nach einer Firma, die man erwartet
 * hätte. Der Versand selbst überspringt sie ohnehin (api/sponsor_versand.php).
 *
 * @return array<int, array<string, mixed>>
 */
function sponsorZielgruppeKandidaten(PDO $pdo, string $slug, string $zielgruppe): array {
    $zg = sponsorZielgruppen($slug);
    if ($zg === []) {
        return [];
    }
    $where = $zg[$zielgruppe]['where'] ?? $zg[array_key_first($zg)]['where'];

    try {
        $stmt = $pdo->query("
            SELECT s.id, s.firma, s.paket, s.status, s.kein_kontakt, s.gesendet_am,
                   (SELECT COUNT(*) FROM sponsor_ansprechpartner a
                     WHERE a.sponsor_id = s.id AND a.email <> '' AND a.im_anschreiben = 1) AS empfaenger
            FROM sponsors s
            WHERE {$where}
            ORDER BY s.firma
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        // Tabelle evtl. noch nicht da — Seite bleibt bedienbar.
        return [];
    }
}

/** Kann dieser Kandidat angeschrieben werden? */
function sponsorKandidatVersandfaehig(array $k): bool {
    return (int) ($k['kein_kontakt'] ?? 0) === 0 && (int) ($k['empfaenger'] ?? 0) > 0;
}

/** Kurzer Klartext, warum ein Kandidat gesperrt ist ('' = versandfähig). */
function sponsorKandidatSperrgrund(array $k): string {
    if ((int) ($k['kein_kontakt'] ?? 0) === 1) {
        return 'Kein Kontakt';
    }
    if ((int) ($k['empfaenger'] ?? 0) === 0) {
        return 'Kein Empfänger';
    }
    return '';
}
