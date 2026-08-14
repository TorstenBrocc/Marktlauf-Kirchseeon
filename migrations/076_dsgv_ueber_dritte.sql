-- 076_dsgv_ueber_dritte.sql
-- Fördergruppe von id 76 (Sparkassen-Finanzgruppe / DSGV – Sportförderung) auf
-- 'ueber_dritte' zurücksetzen (TT-Freigabe 2026-08-14: Einordnung an Claude delegiert,
-- Entscheidung per Grounding gegen die Gruppen-Vorgaben).
--
-- Grounding: SPONSOR_FOERDERGRUPPE_HINWEIS (src/sponsor_status.php) definiert
--   ueber_dritte  = "Kein eigener Antragsweg: läuft über einen Verbund oder Mittler
--                    (z. B. BLSV, Sparkassen-/Volksbank-Verbund, Kommunalbetreuer)"
--   foerderantrag = "Stiftung/Programm mit eigenem Antragsweg ... wir stellen selbst
--                    einen Antrag nach deren Förderleitlinien".
-- DSGV/Sparkassen-Sportförderung hat für ein lokales Event KEINEN eigenen Antragsweg
-- (Recherche + Auskunft Hr. Baier, KSK MSE: nur überregional zentral; lokal ausschließlich
-- über die örtliche Sparkasse = die KSK, bereits Gold-Sponsor) -> textbook ueber_dritte,
-- so hatte es auch Migration 072 bewusst klassifiziert. Der Ist-Wert 'foerderantrag' kam
-- aus einer manuellen Bearbeitung (keine Migration setzt ihn) und widerspricht der Vorgabe.
--
-- Additiv/defensiv: nur ändern, solange der abweichende Ist-Wert 'foerderantrag' anliegt;
-- eine spätere bewusste Um-Einordnung wird dadurch nicht überschrieben.
UPDATE sponsors
SET foerdergruppe = 'ueber_dritte'
WHERE id = 76 AND foerdergruppe = 'foerderantrag';
