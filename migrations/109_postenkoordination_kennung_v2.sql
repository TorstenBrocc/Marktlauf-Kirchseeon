-- 109_postenkoordination_kennung_v2.sql
-- Die Postenkoordination Ilching traegt am Renntag die Kennung V2 statt KI.
--
-- 107 hatte der Rolle eine eigene Kennung "KI" (Koordination Ilching) gegeben,
-- damit sie sich im Board von der Verpflegung unterscheidet. Auf der
-- persoenlichen Helferseite landet die Kennung aber als Badge -- und dort liest
-- sich "KI" wie "K1", also wie eine Postennummer, die es nicht gibt
-- (Rueckmeldung TT am Lauftag, 20.09.2026). Der Standort IST die
-- Versorgungsstation V2; genau das soll die Badge sagen.
--
-- Folge, bewusst in Kauf genommen: zwei Schichten tragen dann V2 -- die
-- Verpflegung selbst und die Koordination. Auf `kennung` liegt kein
-- UNIQUE-Index (102), beide stehen am selben Punkt im selben Zeitfenster, und
-- die Aufgabe steht im Titel daneben. Fuer den Helfer ist V2 die Ortsangabe,
-- nicht die Rollenbezeichnung.
--
-- Bei einem Lauf von vorne bleibt die Reihenfolge stimmig: 107 legt die Zeile
-- mit KI an, 109 stellt sie danach auf V2. Der guarded INSERT in 107 kann
-- dadurch nicht doppelt zuschlagen, solange die Migrationen der Reihe nach
-- laufen.
--
-- Eng gefuehrt ueber Tag UND Kennung, damit nichts anderes getroffen wird.

SET NAMES utf8mb4;

UPDATE `schichten`
   SET `kennung` = 'V2'
 WHERE `tag` = '2026-09-20'
   AND `kennung` = 'KI';
