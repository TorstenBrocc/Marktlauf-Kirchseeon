-- Bestätigungs-Standardentwurf (Master) auf die überarbeitete Fassung setzen.
-- Inhalt 1:1 aus sponsorBriefDefaults()['bestaetigung'] (EINE Quelle), damit Master,
-- Code-Default und Themenfelder denselben Standard zeigen. Betreff unverändert.
UPDATE sponsor_briefvorlagen SET koerper_md = 'Liebe/r {{nachname}},

meinen herzlichsten Dank, dass Sie den Marktlauf Kirchseeon als **{{paket_text}}** unterstützen werden. Wir freuen uns sehr über Ihre Zusage und die Zusammenarbeit!
Wie telefonisch besprochen, erreicht Sie auf diesem Wege unsere Bestätigung bzw. Statusüberblick, damit wir Ihren Markenauftritt optimal vorbereiten können:

**1. Logo & Platzierungen**

- Bitte senden Sie uns Ihr Logo in allen Auflösungen für Web (bevorzugt SVG) und Druck.
- Für die Logo-Verlinkung auf unserer Website benötigen wir den gewünschten Ziel-Link: [Marktlauf-Website-Sponsoren](https://atsv-kirchseeon-marktlauf.de/#sponsoren) – gern nach Übersendung dann mal nachschauen und klicken.
- konkrete Platzierungen Ihres Logos sind Plakat A4 & A3 (anbei - gern Ausdrucken/Weiterleiten) sowie auf Startnummern und Urkunden.
- Haben Sie Flyer oder Give-aways, die wir auslegen oder bei Startnummernabholung überreichen dürfen?

**2. Banner / Hussen**

Für unsere Absperrgitter empfehlen wir **Hussen** statt klassischer Banner – geringerer Aufwand, kein Kabelbinder-Abfall nach dem Event. Die Bemaßungen finden Sie im Anhang.

Entweder gern direkt mitbringen oder Lieferadresse:

ATSV Kirchseeon
c/o ORGA Marktlauf, z. Hd. Frau Jenny Fischer
Sportplatzweg 1
85614 Kirchseeon

**3. Digitale Vernetzung**

Wie möchten Sie digital vernetzt werden? Gibt es Kanäle oder Links, die wir besonders hervorheben sollen?
Unsere Social-Media-Auftritte sind auf [atsv-kirchseeon-marktlauf.de](https://atsv-kirchseeon-marktlauf.de) im Footer und auch hier in der Signatur verlinkt. Ggf. Followen Sie uns zurück?
Zu Kooperationsposts, insofern Sie daran interessiert sind, arbeiten wir noch und kommen wieder zurück, wenn wir so weit sind. Wenn Ihnen diesbezüglich schon etwas vorschweben sollte, dann lassen Sie es mich gern wissen.

**4. Ablauf am Renntag**

Wie wollen wir uns am Renntag connecten? Wie stellen Sie sich den Ablauf für Ihren Stand am Renntag vor (nur für Gold-Sponsoren)
Möchten Sie am Renntag mit einer speziellen Repräsentation vor Ort sein? Haben Sie hier schon Details, wenn dem so ist?
Promoten Sie uns gern ''in house'' und lassen Sie die Kollegen von Ihnen gern mit einheitlichem Trikot erscheinen - was meinen Sie?

**5. Nachlauf & Social Media**

Gibt es spezielle Vorstellungen von Ihnen für den Nachlauf - Bilder von Bannerplatzierung, Startnummer, Urkunde?
Benötigen Sie von uns Fotos, Logos oder Ergebnis-Highlights für Ihre Social-Media-Kanäle?
Wie kommen mögliche Werbematerialien zu Ihnen zurück?

**6. Freie Startplätze**

Gutschein laut Paket {{startplaetze}}x frei verwendbar: {{gutscheincode}}

Bitte bei der Registrierung gern bei Verein `{{firma}}` oder eindeutiges Kürzel mit angeben, dann können wir sogar eine Gruppenauswertung am Ende machen, wenn gewünscht.

**7. Plakate**

wie oben schon geschrieben zum Aushängen/Weiterleiten anbei

**8. Rechnungsanschrift**

Damit wir Ihnen die Rechnung korrekt ausstellen können, benötigen wir Ihre:

- vollständige Rechnungsadresse
- alle für die Buchhaltung notwendigen Informationen (z. B. Ansprechpartner Buchhaltung) und
- E-Mail-Adresse, wohin wir die Rechnung schicken dürfen.

**9. Sponsoring-Bedingungen**

Grundlage unserer Zusammenarbeit sind die beiliegenden Sponsoring-Bedingungen. Bitte geben Sie uns dazu eine kurze positive Rückmeldung – damit gelten sie als vereinbart.

Sollte Ihnen etwas fehlen oder Sie noch Fragen haben, kommen Sie jederzeit gerne auf mich zu.

Vielen Dank für Ihre Unterstützung und Ihr Vertrauen – gemeinsam machen wir den Marktlauf Kirchseeon zu einem unvergesslichen Erlebnis!

Mit sportlichen Grüßen

{{signatur}}' WHERE slug = 'bestaetigung';
