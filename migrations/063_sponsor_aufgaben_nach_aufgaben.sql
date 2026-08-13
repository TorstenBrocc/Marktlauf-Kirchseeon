-- 063_sponsor_aufgaben_nach_aufgaben.sql
--
-- Sponsor-Aufgaben ziehen in die vollwertige `aufgaben`-Tabelle um (TT-Entscheidung
-- 2026-08-13, Option B). Gebunden über den bis dahin ungenutzten Kontext-Hook aus
-- 010_aufgaben.sql: kontext_typ='sponsor', kontext_id=sponsors.id.
--
-- Warum: `sponsor_aufgaben` (006_sponsors.sql:19-26) kennt nur `titel` + `erledigt`.
-- Ohne Fälligkeit und Verantwortlichen taucht eine Aufgabe nie als fällig auf — genau
-- deshalb blieben zwei erledigte KSK-Aufgaben monatelang in der ToDo-Kachel stehen.
-- Diese Felder ein zweites Mal zu bauen hätte eine vierte Doppelquelle im Projekt angelegt.
--
-- ADDITIV: Die Quelltabelle `sponsor_aufgaben` bleibt vorerst unberührt. Ihr Drop ist ein
-- destruktiver Schritt und braucht eine eigene Migration nach Sichtprüfung des Bestands.

-- Kontext-Suche ist ab jetzt ein Lesepfad (ToDo-Kachel, Sponsor-Maske), nicht nur ein Hook.
CREATE INDEX idx_aufgaben_kontext ON aufgaben (kontext_typ, kontext_id);

-- Bestand übernehmen. `erledigt` (0/1) wird auf den ENUM-Status abgebildet; das
-- Anlagedatum bleibt erhalten, damit „seit wann offen" nicht auf heute zurückspringt.
-- Verantwortlicher und Fälligkeit bleiben NULL — im Altbestand gab es sie nicht,
-- und ein erfundener Termin wäre schlimmer als gar keiner.
INSERT INTO aufgaben (titel, status, kontext_typ, kontext_id, created_at)
SELECT sa.titel,
       IF(sa.erledigt = 1, 'erledigt', 'offen'),
       'sponsor',
       sa.sponsor_id,
       sa.created_at
FROM sponsor_aufgaben sa
JOIN sponsors s ON s.id = sa.sponsor_id;
