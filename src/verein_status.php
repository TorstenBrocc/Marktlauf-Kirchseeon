<?php
/**
 * Vereine/Laufevents-Status: Label + Ampel-Farbe an einer Stelle.
 * Analog zu src/sponsor_status.php, eigener Lebenszyklus:
 *   neu(grau) → angeschrieben(blau) → in_kontakt(gelb)
 *   → partner(grün) · kein_interesse(rot)
 */

declare(strict_types=1);

const VEREIN_STATUS = [
    'neu'            => ['label' => 'Neu',            'ampel' => 'grau'],
    'angeschrieben'  => ['label' => 'Angeschrieben',  'ampel' => 'blau'],
    'in_kontakt'     => ['label' => 'In Kontakt',     'ampel' => 'gelb'],
    'partner'        => ['label' => 'Partner',         'ampel' => 'gruen'],
    'kein_interesse' => ['label' => 'Kein Interesse',  'ampel' => 'rot'],
];

function vereinStatusKeys(): array {
    return array_keys(VEREIN_STATUS);
}

function vereinStatusValid(string $status): bool {
    return isset(VEREIN_STATUS[$status]);
}

function vereinStatusLabel(string $status): string {
    return VEREIN_STATUS[$status]['label'] ?? ucfirst($status);
}

function vereinStatusAmpel(string $status): string {
    return VEREIN_STATUS[$status]['ampel'] ?? 'grau';
}

/**
 * Nach erfolgreichem Anschreiben-Versand Tracking-Felder setzen. Der Status
 * wird nur aus 'neu' auf 'angeschrieben' gehoben — ein bereits weiter
 * fortgeschrittener Kontakt wird durch ein erneutes Anschreiben nicht zurückgestuft.
 */
function vereinMarkGesendet(PDO $pdo, int $vereinId, string $typ): void {
    $stmt = $pdo->prepare("
        UPDATE vereine
        SET gesendet_am = NOW(),
            anschreiben_typ = :typ,
            status = CASE WHEN status = 'neu' THEN 'angeschrieben' ELSE status END
        WHERE id = :id
    ");
    $stmt->execute(['typ' => $typ, 'id' => $vereinId]);
}
