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

/**
 * Fördergruppe: auf welchem Weg kommt die Unterstützung zustande (TT 2026-08-14).
 * Reihenfolge = Reiter-Reihenfolge im Kopf der Sponsoren-Übersicht.
 *   sponsoring             = klassisches Paket gegen Leistung (Default)
 *   foerderantrag          = Stiftung/Programm mit Antragsweg + Fristen
 *   ueber_dritte           = läuft über Verbund/Dritte, kein eigener Antragsweg
 *   oeffentlichkeitsarbeit = kein Geld möglich (z. B. gesetzl. Krankenkassen), nur Präsenz
 */
const SPONSOR_FOERDERGRUPPE = [
    'sponsoring'             => 'Sponsoring',
    'foerderantrag'          => 'Förderanträge',
    'ueber_dritte'           => 'Über Verbund/Dritte',
    'oeffentlichkeitsarbeit' => 'Öffentlichkeitsarbeit',
];

function sponsorFoerdergruppeKeys(): array {
    return array_keys(SPONSOR_FOERDERGRUPPE);
}

function sponsorFoerdergruppeLabel(string $gruppe): string {
    return SPONSOR_FOERDERGRUPPE[$gruppe] ?? ucfirst($gruppe);
}

/**
 * Kern-Hinweis je Fördergruppe: was macht die Gruppe im Kern aus (das „warum liegt der
 * Kontakt hier"). Erscheint unter den Fördergruppen-Reitern der Anschreiben-Seite, damit
 * Dashboard-Nutzer die Einordnung nachvollziehen können. Bewusst am Weg/an der Ansprache-
 * Strategie orientiert, NICHT an der Firma — dieselbe Sparkassen-/Volksbank-Familie kann in
 * mehreren Gruppen auftauchen (Bank = Sponsoring, Stiftung = Förderantrag, Verbund = Dritte).
 */
const SPONSOR_FOERDERGRUPPE_HINWEIS = [
    'sponsoring'             => 'Kommerzieller Partner: Logo/Präsenz gegen Paketpreis — wir stellen eine Rechnung.',
    'foerderantrag'          => 'Stiftung/Programm mit eigenem Antragsweg und Fristen. Geld als Zuschuss ohne Werbe-Gegenleistung — wir stellen selbst einen Antrag nach deren Förderleitlinien.',
    'ueber_dritte'           => 'Kein eigener Antragsweg: die Unterstützung läuft über einen Verbund oder Mittler (z. B. BLSV, Sparkassen-/Volksbank-Verbund, Kommunalbetreuer). Wir fragen nach dem richtigen Weg und Ansprechpartner.',
    'oeffentlichkeitsarbeit' => 'Geld-Sponsoring rechtlich ausgeschlossen (v. a. gesetzliche Krankenkassen, § 4a SGB V). Möglich ist nur Präsenz: Infostand, Beitrag im Starterbeutel, gemeinsame Botschaft.',
];

function sponsorFoerdergruppeHinweis(string $gruppe): string {
    return SPONSOR_FOERDERGRUPPE_HINWEIS[$gruppe] ?? '';
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
