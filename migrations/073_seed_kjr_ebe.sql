-- 073_seed_kjr_ebe.sql
-- Seed: Kreisjugendring Ebersberg (KJR EBE) als Förderpartner anlegen (TT-Auftrag 2026-08-14).
--
-- Warum als Seed-Migration statt Hand-Eintrag: Der Datensatz soll reproduzierbar und
-- nachvollziehbar in die DB kommen (kein stiller Klick in der Maske). Erste committete
-- Sponsor-INSERT-Migration — alle bisherigen Sponsor-Migrationen waren ALTER/UPDATE, die
-- Bestandsdaten kamen über Import/Maske. Deshalb bewusst defensiv:
--
--   * Keine feste id — Auto-Increment, damit keine Kollision mit dem Prod-Bestand.
--   * INSERT nur, wenn die Firma noch nicht existiert (NOT EXISTS über Derived-Table,
--     umgeht MySQL-Fehler 1093 "target table … in FROM clause"). So bleibt der Lauf
--     harmlos, falls KJR zwischenzeitlich manuell angelegt wurde.
--   * Ansprechpartner analog nur, wenn für den Sponsor noch nicht vorhanden.
--
-- Einordnung: KJR ist kein klassischer Geld-Sponsor, sondern Untergliederung des
-- Bayerischen Jugendrings (KdöR) und Fördermittelgeber/Kooperationspartner —
-- deshalb foerdergruppe = 'foerderantrag' (Reiter „Förderanträge"), Status 'neu'
-- (steht damit auf der Erstanschreiben-Liste), Paket leer.
--
-- Quelle Kontaktdaten: https://www.kjr-ebe.de/ueber-uns/geschaeftsstelle/ (öffentlich).

INSERT INTO sponsors
  (firma, foerdergruppe, status, ansprache, branche, prioritaet, ort,
   website, quellenurl, foerderprogramm, kontaktweg,
   rechnung_firma, rechnung_strasse, rechnung_plz, rechnung_ort, rechnung_email,
   notizen)
SELECT
  'Kreisjugendring Ebersberg (KJR EBE)', 'foerderantrag', 'neu', 'sie', 'Sonstige', 2, 'Ebersberg',
  'https://www.kjr-ebe.de', 'https://www.kjr-ebe.de/ueber-uns/geschaeftsstelle/',
  'Zuschüsse für Jugend-/Ferienarbeit, Kooperation Jugendbeteiligung (Programm/Fristen bei Erstkontakt klären)',
  'E-Mail / Telefon; Geschäftsstelle Mo–Do 9–15 Uhr; Fax 08092 24615',
  'Kreisjugendring Ebersberg', 'Bahnhofstraße 12', '85560', 'Ebersberg', 'mail@kjr-ebe.de',
  'Untergliederung des Bayerischen Jugendrings (KdöR); Arbeitsgemeinschaft und Interessenvertretung der Jugendverbände im Landkreis Ebersberg. Geschäftsführung seit 01.12. Philipp Spiegelsberger. Geschäftsstelle Mo–Do 9–15 Uhr, Termine außerhalb nach Vereinbarung. Kein klassisches Geld-Sponsoring — Ansatz: Förderung/Zuschuss bzw. Kooperation für die Jugend-/Beteiligungsseite des Marktlaufs. Konkretes Förderprogramm, Antragsweg und Fristen beim Erstkontakt klären.'
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM (
    SELECT id FROM sponsors WHERE firma = 'Kreisjugendring Ebersberg (KJR EBE)' LIMIT 1
  ) AS vorhandener_sponsor
);

-- Sponsor-ID einsammeln (frisch angelegt oder bereits vorhanden).
SET @kjr_id = (
  SELECT id FROM sponsors
  WHERE firma = 'Kreisjugendring Ebersberg (KJR EBE)'
  ORDER BY id LIMIT 1
);

-- Ansprechpartner: Geschäftsführer. mail@kjr-ebe.de ist die zentrale
-- Geschäftsstellen-Adresse (persönliche Adresse ggf. beim Erstkontakt erfragen).
INSERT INTO sponsor_ansprechpartner
  (sponsor_id, anrede, vorname, nachname, funktion, email, telefon, im_anschreiben)
SELECT @kjr_id, 'Herr', 'Philipp', 'Spiegelsberger', 'Geschäftsführer',
       'mail@kjr-ebe.de', '08092 21038', 1
FROM DUAL
WHERE @kjr_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM (
      SELECT id FROM sponsor_ansprechpartner
      WHERE sponsor_id = @kjr_id AND nachname = 'Spiegelsberger' LIMIT 1
    ) AS vorhandener_ap
  );
