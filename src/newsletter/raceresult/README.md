# RaceResult-14-Mailvorlagen — Quelltexte

Die Bestätigungs- und Info-Mails, die RR14 an Teilnehmer verschickt, liegen produktiv
**nur in RR14** (Emails/SMS → [Vorlage]). RR14 führt dort **keine Versionshistorie**.
Dieser Ordner ist deshalb die versionierte Quelle: Der Inhalt hier ist das, was in die
CKEditor-Felder eingefügt gehört.

Basis ist derselbe Marken-Rahmen wie `../03_html_master_template.md` (Design-System-Browser
→ Snippets → Newsletter-Master), nur an die Grenzen von RR14 angepasst.

## Dateien

| Datei | RR14-Vorlage | Feld |
|---|---|---|
| `letzte-infos.html` | „letzte Infos" (Typ *EMail (Einzel)*) | Text |
| `sammel-anmeldung-1-kopf.html` | „Sammel-Anmeldung" (Typ *Email (Sammel): Eine Email pro Sammelanmeldung*) | Email-Kopf |
| `sammel-anmeldung-2-text.html` | dieselbe | Text (**wird je Teilnehmer wiederholt**) |
| `sammel-anmeldung-3-fuss.html` | dieselbe | Email-Fuß |

**Nicht enthalten:** die Vorlage „Einzel-Anmeldung". Sie wurde 2026 nur an einzelnen Stellen
bearbeitet (Jahrgang → Alter, Startzeiten-Zeile, Wortlaut) und existiert als Ganzes nur in RR14.
Vor dem nächsten Einsatz dort über den „Source"-Knopf herausholen und hier ablegen.

## Einsetzen

1. Format der Vorlage auf **HTML** stellen.
2. Im jeweiligen Feld auf **„Source"** klicken und den Dateiinhalt **vollständig ersetzen**.
3. Zurück in die WYSIWYG-Ansicht wechseln, damit CKEditor den Code übernimmt, dann speichern.
4. Kontrolle im Reiter **Senden**: Startnummer eines echten Teilnehmers eintragen und die
   Vorschau lesen — dort lösen die Platzhalter auf.

## Was RR14 kann und was nicht (2026 am Live-System geprüft)

- **Nur blanke Platzhalter.** `[Vorname]`, `[Nachname]`, `[Alter]`, `[Startnr]`,
  `[Wettbewerb.Name]`, `[Wettbewerb.Start]`, `[Veranstaltung.Name]` werden ersetzt.
  **Gerechnet oder formatiert wird nicht:** `[Alter*2]` ergibt `0`,
  `[format(...)]` ergibt `0,00`, Format-Suffixe wie `[Wettbewerb.Start:h:mm]` bleiben leer.
- **Startzeit nur mit Sekunden.** `[Wettbewerb.Start]` liefert `11:00:00`. Auch der Umweg über
  *Grundeinstellungen → Teilnehmerdaten → Benutzerdef. Felder/Fkten* scheitert:
  `[Wettbewerb.*]` ist im Formel-Kontext nicht lesbar (weder als Zahl noch als Text).
  Wer `11:00 Uhr` will, braucht ein **gespeichertes** Zusatzfeld, das je Wettbewerb per
  Massenänderung befüllt wird.
- **`<style>` überlebt.** Media Queries und der `<!--[if mso]>`-Block bleiben erhalten, wenn sie
  im ersten Feld stehen. Die Blöcke nutzen dafür die Klassen `container`, `pad`, `h1`.
- **Jedes Feld muss für sich geschlossen sein.** Ein Tabellenrahmen, der im Kopf geöffnet und
  im Fuß geschlossen wird, zerreißt — genau das war 2026 der Grund für die kaputte
  Sammel-Bestätigung (Karte endete mitten im Text, Footer hell auf weiß).
  Deshalb: Radius und Rand oben nur im Kopf, links/rechts im Textteil, unten im Fuß.
- **Bilder nur von der eigenen Domain.** Die RR-Bibliothek liefert 401. Wortmarke, Vereinswappen
  und QR-Code liegen unter `https://atsv-kirchseeon-marktlauf.de/assets/images/`.
  SVG rendern viele Mailclients nicht — der QR liegt deshalb zusätzlich als PNG
  (`assets/images/qr-nachmeldung.png`, Ziel `https://my.raceresult.com/412617/registration`,
  Fehlerkorrektur Q, modulgleich mit `qr-nachmeldung.svg`).
- **Absender prüfen.** Neue Vorlagen kommen mit leeren Absenderfeldern; „letzte Infos" stand bis
  2026 auf `noreply@raceresult.com`. Richtig ist Name **Marktlauf ORGA Team**, Absender und
  Antworten-An **info@atsv-kirchseeon-marktlauf.de**.
- **Testversand:** Empfänger-Feld → **„<Ausdruck (Formel)>"** mit
  `"info@atsv-kirchseeon-marktlauf.de"` schickt alles ins Vereinspostfach.
  ⚠️ Danach **zwingend zurück auf „Mail"**, sonst geht der Gesamtversand an diese eine Adresse.
