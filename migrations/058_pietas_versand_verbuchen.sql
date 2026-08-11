-- 058_pietas_versand_verbuchen.sql
-- Einmalige Datenkorrektur (TT, 2026-08-11): Die Rechnung 03-2026 an Bestattungsdienst PIETAS
-- wurde außerhalb des Systems per Mailprogramm an eine andere Adresse verschickt. Im Protokoll
-- fehlte der Vorgang deshalb, und die Spalte „Versand" stand auf „—".
--
-- Bewusst nur die Protokollzeile (so entschieden): kein Mailversand, und damit auch keine
-- Google-Drive-Ablage — die entsteht im Code nur beim regulären Versand. Das Rechnungs-PDF fehlt
-- also im Ordner Orga/2026/Finanzen/Sponsoren-Abrechnungen; der Hinweis in der Zeile sagt das,
-- damit später niemand eine vollständige Ablage annimmt.
--
-- `versendet_am` ist der Zeitpunkt dieser Verbuchung, nicht der des tatsächlichen Versands (der
-- ist nicht erfasst). Die Zeile hängt an der Rechnung, nicht an einer Nummer: id 3 = 03-2026,
-- über die Rechnungsnummer eingegrenzt, damit die Migration nichts Falsches trifft.

INSERT INTO rechnung_versand_log (rechnung_id, versendet_am, empfaenger, versendet_von, drive_datei_id, ergebnis, hinweis)
SELECT r.id, NOW(), 'manuell versendet (Adresse nicht erfasst)', NULL, NULL, 'ok',
       'Außerhalb des Systems per Mailprogramm versendet; nachträglich verbucht. Keine Drive-Ablage, kein Kassier-CC.'
FROM sponsor_rechnungen r
WHERE r.rechnungsnummer = '03-2026'
  AND NOT EXISTS (SELECT 1 FROM rechnung_versand_log l WHERE l.rechnung_id = r.id);
