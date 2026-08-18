-- Ansprechpartner: persistente Sortier-Reihenfolge je Sponsor.
-- Bisher war die Reihenfolge implizit (ORDER BY id ASC). Diese Migration ergaenzt
-- eine explizite Spalte `sortierung`, die per Drag-and-Drop in der Sponsor-Einzelmaske
-- umgeschrieben wird.
--
-- Backfill: sortierung = id. Nur die *relative* Ordnung zaehlt (ORDER BY sortierung ASC,
-- id ASC), und innerhalb eines Sponsors ist hoehere id = spaeter eingefuegt -- damit
-- bleibt die heutige Anzeige-Reihenfolge unveraendert. Bewusst ohne Window-Funktion
-- (ROW_NUMBER, erst ab MySQL 8.0 / MariaDB 10.2), damit die Migration nicht von der
-- Server-Version abhaengt -- ADD COLUMN + einfaches UPDATE sind Basis-SQL. Beim ersten
-- Reorder eines Sponsors normalisiert der Endpoint dessen Werte auf 1..n.

ALTER TABLE sponsor_ansprechpartner
    ADD COLUMN sortierung INT NOT NULL DEFAULT 0;

UPDATE sponsor_ansprechpartner SET sortierung = id WHERE sortierung = 0;
