<?php
/**
 * Sponsor-Status: zentrale Definition von Label + Ampel-Farbe.
 * Wird von Übersicht, Formular, Import/Export und CLI genutzt,
 * damit der Lebenszyklus an einer Stelle gepflegt wird.
 *
 * Grundlage: intern/sponsor-crm-ausbau.md §2.1
 */

declare(strict_types=1);

/**
 * Reihenfolge = Lebenszyklus (bestimmt auch die Sortierung in Dropdowns).
 * 'ampel' ∈ grau | blau | gelb | gruen | rot
 */
const SPONSOR_STATUS = [
    'neu'         => ['label' => 'Neu',           'ampel' => 'grau'],
    'angefragt'   => ['label' => 'Angeschrieben', 'ampel' => 'blau'],
    'in_klaerung' => ['label' => 'In Klärung',    'ampel' => 'gelb'],
    'zugesagt'    => ['label' => 'Zugesagt',      'ampel' => 'gruen'],
    'bestaetigt'  => ['label' => 'Bestätigt',     'ampel' => 'gruen'],
    'abgerechnet' => ['label' => 'Abgerechnet',   'ampel' => 'gruen'],
    'bezahlt'     => ['label' => 'Bezahlt',       'ampel' => 'gruen'],
    'abgelehnt'   => ['label' => 'Abgelehnt',     'ampel' => 'rot'],
];

function sponsorStatusKeys(): array {
    return array_keys(SPONSOR_STATUS);
}

function sponsorStatusValid(string $status): bool {
    return isset(SPONSOR_STATUS[$status]);
}

function sponsorStatusLabel(string $status): string {
    return SPONSOR_STATUS[$status]['label'] ?? ucfirst($status);
}

function sponsorStatusAmpel(string $status): string {
    return SPONSOR_STATUS[$status]['ampel'] ?? 'grau';
}

/** Rückmelde-Wege für die Bestätigung der Sponsoring-Bedingungen. */
const SPONSOR_BEDINGUNGEN_WEG = [
    'email'        => 'E-Mail-Antwort',
    'unterschrift' => 'Unterschrift',
    'telefon'      => 'Telefon',
    'persoenlich'  => 'Persönlich',
    'sonstige'     => 'Sonstige',
];

function sponsorBedingungenWegKeys(): array {
    return array_keys(SPONSOR_BEDINGUNGEN_WEG);
}

function sponsorBedingungenWegLabel(string $weg): string {
    return SPONSOR_BEDINGUNGEN_WEG[$weg] ?? '';
}

/**
 * Ist eine Bedingungen-Bestätigung für diesen Status "benötigt"?
 * Erst ab verschickter Bestätigung (Status bestaetigt/abgerechnet/bezahlt) — davor neutral (grau).
 */
function sponsorBedingungenBenoetigt(string $status): bool {
    return in_array($status, ['bestaetigt', 'abgerechnet', 'bezahlt'], true);
}

/**
 * Nach erfolgreichem Anschreiben-Versand Tracking-Felder setzen.
 * Status wird nur aus dem Vor-Versand-Zustand 'neu' auf 'angefragt'
 * (= „Angeschrieben") gehoben — ein Sponsor in Klärung oder ein
 * Bestandssponsor (zugesagt/bezahlt) wird durch ein erneutes Anschreiben
 * nicht zurückgestuft.
 */
/**
 * Nach erfolgreichem Bestätigungs-Versand den Status auf 'bestaetigt' heben.
 * Semantik wie sponsorMarkGesendet: nur der Vor-Zustand 'zugesagt' wird gehoben — ein bereits
 * abgerechneter oder bezahlter Sponsor wird durch eine erneut versandte Bestätigung NICHT
 * zurückgestuft.
 */
function sponsorMarkBestaetigt(PDO $pdo, int $sponsorId): void {
    $stmt = $pdo->prepare("
        UPDATE sponsors
        SET status = 'bestaetigt'
        WHERE id = :id AND status = 'zugesagt'
    ");
    $stmt->execute(['id' => $sponsorId]);
}

function sponsorMarkGesendet(PDO $pdo, int $sponsorId, string $typ): void {
    $stmt = $pdo->prepare("
        UPDATE sponsors
        SET gesendet_am = NOW(),
            anschreiben_typ = :typ,
            status = CASE WHEN status = 'neu' THEN 'angefragt' ELSE status END
        WHERE id = :id
    ");
    $stmt->execute(['typ' => $typ, 'id' => $sponsorId]);
}
