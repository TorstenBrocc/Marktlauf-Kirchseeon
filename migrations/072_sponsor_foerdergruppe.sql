-- 072_sponsor_foerdergruppe.sql
-- Fördergruppe am Sponsor (TT-Freigabe 2026-08-14): auf welchem Weg kommt die
-- Unterstützung zustande. Die Reiter im Kopf der Sponsoren-Übersicht filtern darauf.
-- Zentrale Definition: src/sponsor_status.php (SPONSOR_FOERDERGRUPPE).
ALTER TABLE sponsors
  ADD COLUMN foerdergruppe ENUM('sponsoring','foerderantrag','ueber_dritte','oeffentlichkeitsarbeit')
    NOT NULL DEFAULT 'sponsoring' AFTER paket;

-- Erstzuordnung (Quelle: intern/sponsoren-abarbeitung-2026-08-12.md + CRM-Notizen).
-- Alle übrigen Einträge bleiben auf dem Default 'sponsoring'.

-- Stiftungen/Programme mit eigenem Antragsweg:
--   75 VR-Förderpreis · 78 VK Stiftung · 104 KSK-Stiftungen · 112 Sportjugendstiftung
UPDATE sponsors SET foerdergruppe = 'foerderantrag' WHERE id IN (75, 78, 104, 112);

-- Läuft über Verbund/Dritte, kein eigener Weg:
--   74 VR Gewinnsparverein · 76 DSGV · 77 VK Bayern (via Stiftung) · 81 Schwäbisch Hall
--   82 LBS · 83 ARAG (via BLSV) · 110 Bayernwerk (via Kommunalbetreuer) · 111 ESB
UPDATE sponsors SET foerdergruppe = 'ueber_dritte' WHERE id IN (74, 76, 77, 81, 82, 83, 110, 111);

-- Gesetzliche Krankenkassen: Geld-Sponsoring aufsichtsrechtlich verbaut (§ 4a SGB V /
-- KKWerbeV), realistisch nur Öffentlichkeitsarbeit (Infostand, Starterbeutel):
UPDATE sponsors SET foerdergruppe = 'oeffentlichkeitsarbeit'
WHERE id IN (85, 86, 87, 88, 89, 90, 91, 92, 93, 94, 95, 96, 97);
