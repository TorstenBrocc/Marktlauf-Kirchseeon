-- 026_seed_schichten_from_katalog.sql
-- Ueberfuehrt den bisher hart im Code hinterlegten Helfer-Aufgabenkatalog
-- (src/helfer_aufgaben.php) in echte Schichten-Datensaetze. Ab jetzt ist die
-- schichten-Tabelle die einzige Quelle fuer das Anmeldeformular UND den
-- Einsatzplan. Alle Katalog-Schichten werden im Formular angeboten (in_anmeldung=1).
--
-- WICHTIG: titel/zeitfenster muessen exakt den bisherigen Katalog-Strings
-- entsprechen (inkl. Gedankenstrich U+2013), damit die Backfill-Zuordnung der
-- bereits vorhandenen Anmeldungen in 027 greift.

SET NAMES utf8mb4;

INSERT INTO `schichten` (`titel`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `bedarf`, `in_anmeldung`, `zeitfenster`) VALUES
-- Freitag 18.09.2026 (Aufbau)
('Div. Unterstützung für die Wegführung', NULL, NULL, '2026-09-18', NULL, NULL, 1, 1, 'freie Verfügbarkeit'),
('Div. Unterstützung für die Wegführung', NULL, NULL, '2026-09-18', NULL, NULL, 1, 1, 'Nachmittag nach Absprache'),
-- Samstag 19.09.2026 (Aufbau)
('Ganzer Tag', NULL, NULL, '2026-09-19', NULL, NULL, 1, 1, 'freie Verfügbarkeit'),
('Alternativer Termin / übrige Vorbereitungen für die Wegführung', NULL, NULL, '2026-09-19', NULL, NULL, 1, 1, 'nach Absprache'),
('Vorbereitungen im Vereinsheim', NULL, NULL, '2026-09-19', NULL, NULL, 1, 1, 'nach Absprache'),
-- Sonntag 20.09.2026 (Renntag)
('Ganzer Tag', NULL, NULL, '2026-09-20', NULL, NULL, 1, 1, 'freie Verfügbarkeit'),
('Aufbauarbeiten Start/Ziel', NULL, NULL, '2026-09-20', '07:00', '10:00', 1, 1, '07:00–10:00'),
('Aufbauarbeiten Start/Ziel', NULL, NULL, '2026-09-20', '08:00', '10:00', 1, 1, '08:00–10:00'),
('Startnummernausgabe / Nachmeldungen', NULL, NULL, '2026-09-20', '08:00', '10:00', 1, 1, '08:00–10:00'),
('Streckenposten Versorgungsstationen Laufstrecke', NULL, NULL, '2026-09-20', '09:00', '12:00', 1, 1, '09:00–12:00'),
('Betreuung / Aufbau Versorgungsstation Start/Ziel', NULL, NULL, '2026-09-20', '09:00', '13:00', 1, 1, '09:00–13:00'),
('Abbau Laufevent', NULL, NULL, '2026-09-20', '13:00', '15:00', 1, 1, '13:00–15:00'),
('ATSV Getränkeverkauf Hauptplatz', NULL, NULL, '2026-09-20', '09:30', '12:00', 1, 1, '09:30–12:00'),
('ATSV Getränkeverkauf Hauptplatz', NULL, NULL, '2026-09-20', '12:00', '14:00', 1, 1, '12:00–14:00'),
('ATSV Getränkeverkauf Hauptplatz', NULL, NULL, '2026-09-20', '14:00', '16:30', 1, 1, '14:00–16:30');
