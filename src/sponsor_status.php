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

/**
 * Nach erfolgreichem Anschreiben-Versand Tracking-Felder setzen.
 * Status wird nur aus dem Vor-Versand-Zustand 'neu' auf 'angefragt'
 * (= „Angeschrieben") gehoben — ein Sponsor in Klärung oder ein
 * Bestandssponsor (zugesagt/bezahlt) wird durch ein erneutes Anschreiben
 * nicht zurückgestuft.
 */
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
