-- 074_foerderantrag_bsj_und_strategie.sql
-- Förderanträge schärfen (TT-Freigabe 2026-08-14, gemeinsame Recherche):
--   1) Neuer Antrags-Kontakt: Bayerische Sportjugend (BSJ) im BLSV — direkter Jugendsport-
--      Förderweg (Kinder- und Jugendprogramm der Bayer. Staatsregierung). Bisher steckte der
--      BLSV nur indirekt drin (74er-Block „ARAG via BLSV", ueber_dritte). Als BLSV-Verein
--      kann der ATSV dort SELBST ansetzen -> eigener foerderantrag-Eintrag.
--   2) Recherche-/Strategie-Notizen an die bestehenden Antrags-Töpfe (75/78/104/112)
--      ANHÄNGEN (CONCAT, nicht überschreiben) — für die Nachvollziehbarkeit im Dashboard.
--
-- Bewusst additiv/defensiv: BSJ-INSERT nur, wenn die Firma noch nicht existiert; Notizen
-- werden nur ergänzt (COALESCE + CONCAT), bestehende CRM-Notizen bleiben unangetastet.
-- IDs wie in Migration 072 dokumentiert. Kontaktdaten öffentlich recherchiert.

-- 1) BSJ/BLSV als Förderanträge-Kontakt -------------------------------------------------
INSERT INTO sponsors
  (firma, foerdergruppe, status, ansprache, branche, prioritaet, ort,
   website, quellenurl, foerderprogramm, kontaktweg, notizen)
SELECT
  'Bayerische Sportjugend im BLSV (Jugendförderung)', 'foerderantrag', 'neu', 'sie', 'Sonstige', 1, 'München',
  'https://bsj.org', 'https://bsj.org/startseite/foerderung/',
  'Kinder- und Jugendprogramm der Bayerischen Staatsregierung (Zuschüsse für sportliche Jugendarbeit, Bildungsmaßnahmen)',
  'E-Mail (jugendfoerderung@blsv.de); BLSV-Kreis 17 Ebersberg als regionaler Weg',
  '[Recherche 2026-08-14] Direkter Jugendsport-Förderweg, da der ATSV BLSV-Mitglied ist. Über die BSJ/den BLSV-Kreis 17 Ebersberg laufen Zuschüsse für sportliche Jugendarbeit. Antragsweg/Fristen beim Erstkontakt klären.'
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM (
    SELECT id FROM sponsors WHERE firma = 'Bayerische Sportjugend im BLSV (Jugendförderung)' LIMIT 1
  ) AS vorhanden
);

SET @bsj_id = (
  SELECT id FROM sponsors
  WHERE firma = 'Bayerische Sportjugend im BLSV (Jugendförderung)'
  ORDER BY id LIMIT 1
);

INSERT INTO sponsor_ansprechpartner
  (sponsor_id, anrede, vorname, nachname, funktion, email, telefon, im_anschreiben)
SELECT @bsj_id, '', '', '', 'Jugendförderung', 'jugendfoerderung@blsv.de', '', 1
FROM DUAL
WHERE @bsj_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM (
      SELECT id FROM sponsor_ansprechpartner
      WHERE sponsor_id = @bsj_id AND email = 'jugendfoerderung@blsv.de' LIMIT 1
    ) AS vorhanden_ap
  );

-- 2) Strategie-Notizen anhängen (nicht überschreiben) -----------------------------------

-- 75 VR-Förderpreis: hohe Wahrscheinlichkeit „Sterne des Sports" (Volksbanken/Raiffeisen +
-- DOSB) — direkte Online-Bewerbung über die lokale Volksbank. Bleibt foerderantrag.
UPDATE sponsors
SET notizen = CONCAT(COALESCE(notizen, ''),
  '\n\n[Recherche 2026-08-14] Vermutlich „Sterne des Sports" (Volksbanken/Raiffeisenbanken + DOSB): direkte Online-Bewerbung über die lokale Volksbank, Bewerbungsphase ab 01.04., Frist ~30.06.; lokal bis 1.500 EUR, Bundes-Gold 10.000 EUR. Passt (soziales/Jugend-Engagement). Bitte Bank-Identität am Eintrag bestätigen.')
WHERE id = 75;

-- 78 VK Stiftung (Versicherungskammer Stiftung): fördert Ehrenamt/Lebensrettung/Kriminal-
-- prävention — KEINE Sport-Projektförderung. Hebel = Ehrenamtspreis.
UPDATE sponsors
SET notizen = CONCAT(COALESCE(notizen, ''),
  '\n\n[Recherche 2026-08-14] Versicherungskammer Stiftung fördert Ehrenamt/Lebensrettung/Kriminalprävention, NICHT Sport-Projekte. Der Marktlauf ist hier nicht projekt-antragsfähig. Realistischer Weg: Ehrenamtspreis der VK-Stiftung — Bewerbung mit dem ehrenamtlichen Orga-Engagement rund um den Lauf.')
WHERE id = 78;

-- 104 KSK-Stiftung(en): Stiftung der Kreissparkasse Ebersberg — lokal, beste Passung.
UPDATE sponsors
SET notizen = CONCAT(COALESCE(notizen, ''),
  '\n\n[Recherche 2026-08-14] Stiftung der Kreissparkasse Ebersberg fördert Sport/Jugend/Soziales/Kultur im Altlandkreis Ebersberg (lokal) — beste Passung. Kontakt stiftungen@kskmse.de, 089/23801-2644. NICHT die KSK als Gold-Sponsor (eigener Datensatz, Fördergruppe Sponsoring). 2027-Hebel: frühzeitig lokalen Antrag stellen.')
WHERE id = 104;

-- 112 Sportjugendstiftung d. bayer. Sparkassen: bayernweit, aber nur überregional bedeutsame
-- Breitensport-Jugendprojekte.
UPDATE sponsors
SET notizen = CONCAT(COALESCE(notizen, ''),
  '\n\n[Recherche 2026-08-14] Sportjugendstiftung der bayer. Sparkassen fördert Jugend-Breitensport bayernweit, aber nur Projekte mit ÜBERREGIONALER Bedeutung (Schwerpunkte: Freude an Bewegung, Prävention, Integration; Beispiele: „Vereint in Bewegung", Schulsportförderung, „Sport nach 1"). Für die Eignung den jährlich-wiederkehrenden, regionsübergreifenden Charakter betonen. Tel. 089/2173-1502.')
WHERE id = 112;
