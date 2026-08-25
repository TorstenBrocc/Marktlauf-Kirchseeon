<?php
/**
 * Sponsor-Schreiblogik — die EINE Wahrheit für validierte Feld-Updates.
 *
 * Herausgelöst aus dem Autosave-Endpoint orga/api/sponsor_crud.php (field_update), damit
 * derselbe Whitelist-/Validierungspfad auch von einem CLI-Werkzeug (bin/admin_write.php) genutzt
 * werden kann, ohne die Regeln zu duplizieren. Der Endpoint ruft jetzt sponsorSetField() auf;
 * die Spaltennamen stammen ausschließlich aus den Whitelists hier — nie aus Nutzer-Input in die SQL.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/sponsor_status.php';

/** Priorität aus Eingabe: leer|1|2|3 → int oder NULL. */
function sponsorPrioritaetFromPost(mixed $raw): ?int {
    $v = trim((string) $raw);
    if ($v === '' || !ctype_digit($v)) {
        return null;
    }
    $n = (int) $v;
    return ($n >= 1 && $n <= 3) ? $n : null;
}

/**
 * Konzern/Gruppe per Freitext: bestehende Gruppe wiederverwenden (exakter Namensvergleich)
 * statt bei jeder Eingabe eine neue anzulegen. Leere Eingabe = keine Gruppe (gruppe_id NULL).
 */
function sponsorGruppeIdFromPost(PDO $pdo, string $rawName): ?int {
    $name = trim($rawName);
    if ($name === '') {
        return null;
    }
    $select = $pdo->prepare('SELECT id FROM sponsor_gruppen WHERE name = :name');
    $select->execute(['name' => $name]);
    $id = $select->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $insert = $pdo->prepare('INSERT INTO sponsor_gruppen (name) VALUES (:name)');
    $insert->execute(['name' => $name]);
    return (int) $pdo->lastInsertId();
}

/**
 * Ein einzelnes Sponsor-Feld validiert setzen. Rückgabe: ['ok'=>bool, 'message'=>string].
 * $value ist skalar; nur für 'branche' ein Array (Mehrfachauswahl → JSON).
 *
 * Bewusst identisch zur bisherigen field_update-Logik — nur $_POST durch $value ersetzt, damit
 * Endpoint und CLI denselben Pfad teilen. Kein DELETE, keine dynamischen Spaltennamen aus Input.
 */
function sponsorSetField(PDO $pdo, int $sponsorId, string $field, mixed $value): array
{
    if ($sponsorId <= 0) {
        return ['ok' => false, 'message' => 'Ungültige Sponsor-ID.'];
    }

    $plainText = [
        'ort', 'notizen', 'rechnung_firma', 'rechnung_email', 'rechnung_strasse',
        'rechnung_plz', 'rechnung_ort', 'foerderprogramm', 'kontaktweg',
        'quellenurl', 'weitere_links', 'website',
        'kernkompetenz', 'social_handle',
    ];
    $dateFields = ['wiedervorlage', 'bedingungen_bestaetigt_am'];
    $checkboxFields = ['rechnung_betrag_brutto', 'bedingungen_beleg'];

    // Sponsor muss existieren.
    $chk = $pdo->prepare('SELECT 1 FROM sponsors WHERE id = :id');
    $chk->execute(['id' => $sponsorId]);
    if (!$chk->fetchColumn()) {
        return ['ok' => false, 'message' => 'Sponsor nicht gefunden.'];
    }

    $set = static function (string $col, $v) use ($pdo, $sponsorId): void {
        // $col ausschließlich aus den Whitelists unten — nie aus Input.
        $pdo->prepare("UPDATE sponsors SET {$col} = :v WHERE id = :id")
            ->execute(['v' => $v, 'id' => $sponsorId]);
    };

    // firma: Pflichtfeld, darf nicht geleert werden.
    if ($field === 'firma') {
        $firma = trim((string) $value);
        if ($firma === '') {
            return ['ok' => false, 'message' => 'Firma darf nicht leer sein.'];
        }
        $set('firma', $firma);
        return ['ok' => true];
    }
    if ($field === 'status') {
        if (!sponsorStatusValid((string) $value)) {
            return ['ok' => false, 'message' => 'Ungültiger Status.'];
        }
        $set('status', (string) $value);
        return ['ok' => true];
    }
    if ($field === 'paket') {
        $paket = in_array($value, ['hauptsponsor', 'gold', 'silber', 'bronze', 'sachsponsor'], true) ? (string) $value : null;
        $set('paket', $paket);
        return ['ok' => true];
    }
    if ($field === 'foerdergruppe') {
        if (!isset(SPONSOR_FOERDERGRUPPE[(string) $value])) {
            return ['ok' => false, 'message' => 'Ungültige Fördergruppe.'];
        }
        $set('foerdergruppe', (string) $value);
        return ['ok' => true];
    }
    if ($field === 'summe') {
        $summe = (float) $value ?: null;
        $set('summe', $summe);
        return ['ok' => true];
    }
    if ($field === 'prioritaet') {
        $set('prioritaet', sponsorPrioritaetFromPost($value));
        return ['ok' => true];
    }
    if ($field === 'gruppe_name') {
        $set('gruppe_id', sponsorGruppeIdFromPost($pdo, (string) $value));
        return ['ok' => true];
    }
    if ($field === 'ansprache') {
        $set('ansprache', ((string) $value === 'du') ? 'du' : 'sie');
        return ['ok' => true];
    }
    if ($field === 'bedingungen_weg') {
        $weg = in_array((string) $value, sponsorBedingungenWegKeys(), true) ? (string) $value : null;
        $set('bedingungen_weg', $weg);
        return ['ok' => true];
    }
    if ($field === 'branche') {
        $arr = array_values(array_filter(array_map('trim', (array) $value)));
        $set('branche', !empty($arr) ? json_encode($arr) : null);
        return ['ok' => true];
    }
    if (in_array($field, $dateFields, true)) {
        $set($field, trim((string) $value) ?: null);
        return ['ok' => true];
    }
    if (in_array($field, $checkboxFields, true)) {
        $set($field, ((string) $value === '1') ? 1 : 0);
        return ['ok' => true];
    }
    if (in_array($field, $plainText, true)) {
        $set($field, trim((string) $value) ?: null);
        return ['ok' => true];
    }

    return ['ok' => false, 'message' => 'Ungültiges Feld.'];
}

/** Alle per sponsorSetField() beschreibbaren Feldnamen (für CLI-Hilfe/Whitelist-Anzeige). */
function sponsorSetFieldKeys(): array
{
    return [
        'firma', 'status', 'paket', 'foerdergruppe', 'summe', 'prioritaet', 'gruppe_name',
        'ansprache', 'bedingungen_weg', 'branche',
        'wiedervorlage', 'bedingungen_bestaetigt_am',
        'rechnung_betrag_brutto', 'bedingungen_beleg',
        'ort', 'notizen', 'rechnung_firma', 'rechnung_email', 'rechnung_strasse',
        'rechnung_plz', 'rechnung_ort', 'foerderprogramm', 'kontaktweg',
        'quellenurl', 'weitere_links', 'website',
        'kernkompetenz', 'social_handle',
    ];
}
