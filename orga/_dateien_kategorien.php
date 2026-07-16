<?php
/**
 * Zentrale Liste der Datei-Kategorien — Single Source of Truth für
 * Upload-Formular, Validierung (api/file_upload.php) und Filter (dateien.php).
 * Reihenfolge = Anzeigereihenfolge; Schlüssel = gespeicherter DB-Wert.
 *
 * Kategorien anpassen: nur hier ändern. 'allgemein' ist der Default (siehe
 * Migration 018) und sollte erhalten bleiben, damit Altbestände ein Label haben.
 */

declare(strict_types=1);

function dateiKategorien(): array
{
    return [
        'allgemein' => 'Allgemein',
        'konzept'   => 'Konzept / Planung',
        'sponsoren' => 'Sponsoren',
        'einsatz'   => 'Einsatzplan',
        'presse'    => 'Social-Media Bilder',
        'finanzen'  => 'Finanzen',
        'sonstiges' => 'Sonstiges',
    ];
}

/** Anzeigelabel zu einem gespeicherten Schlüssel (Fallback: Allgemein). */
function dateiKategorieLabel(string $key): string
{
    return dateiKategorien()[$key] ?? 'Allgemein';
}

/** Gültigen Schlüssel sicherstellen — unbekannte/leere Werte → 'allgemein'. */
function dateiKategorieNormalisieren(?string $key): string
{
    $key = (string) $key;
    return isset(dateiKategorien()[$key]) ? $key : 'allgemein';
}

/** <option>-Liste für ein <select>. $selected markiert den aktiven Eintrag. */
function dateiKategorieOptions(string $selected = 'allgemein'): string
{
    $out = '';
    foreach (dateiKategorien() as $key => $label) {
        $sel = $key === $selected ? ' selected' : '';
        $out .= '<option value="' . htmlspecialchars($key) . '"' . $sel . '>'
              . htmlspecialchars($label) . '</option>';
    }
    return $out;
}
