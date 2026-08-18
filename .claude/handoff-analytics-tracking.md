# Handoff: Google Analytics / Conversion-Tracking (Ad Grants)

> **Zweck dieses Dokuments:** Übergabe an eine **lokale Claude-Code-Session mit Chrome-/Browser-Zugriff**.
> Diese Aufgabe wurde in einer Remote-Session (Claude Code on the web) vorbereitet, dort fehlt aber der
> Zugriff auf die (eingeloggte) Google-Analytics-Oberfläche und die Live-Seite. In einer lokalen Session
> mit Browser-Tool kann Claude die echte GA4-UI mit dem Nutzer ansehen und klicken.
>
> **Nutzer:** t.tyras@atsv-kirchseeon-marktlauf.de (ATSV Kirchseeon, Orga Marktlauf)
> **Repo:** TorstenBrocc/Marktlauf-Kirchseeon · **Live:** https://atsv-kirchseeon-marktlauf.de/
> **Arbeits-Branch:** `claude/marktlauf-repo-issue-yjlpxp` · Deploy: **Merge nach `main` → GitHub Action `deploy.yml` → SFTP auf Strato**

---

## 1. Ziel

Google **Ad Grants** (kostenlose Google-Ads-Förderung) verlangt **Conversion-Tracking mit mindestens
1 Conversion pro Monat**, sonst wird der Grant nach Freischaltung wieder deaktiviert. Aktuell hat die
Seite **kein Tracking**. Es soll **DSGVO-konform** (Deutschland!) Conversion-Tracking eingerichtet werden.

## 2. Rechtlicher Rahmen (wichtig!)

- Die Seite setzt **aktuell keine Cookies** und hat **kein Consent-Banner** — sie ist datenschutzrechtlich „sauber".
- Google Analytics / Google Ads setzen Cookies und übertragen Daten an Google (USA) → in DE nach
  **TTDSG §25 + DSGVO nur mit aktiver Einwilligung (Opt-in)** zulässig.
- **Daher zwingend zusammen umsetzen:** Consent-Banner + **Google Consent Mode v2** (Default `denied`,
  erst nach Zustimmung `granted`) + Ergänzung der Datenschutzerklärung.

## 3. Was der Nutzer bei Google anlegen muss (mit Chrome gemeinsam durchgehen)

Die GA4-Oberfläche weicht je nach Konto ab — deshalb **im Browser gemeinsam navigieren** statt blind
Klickpfade vorgeben. Ziel des Google-Teils:

1. **GA4-Property** anlegen unter https://analytics.google.com (Vereins-Google-Konto, dasselbe wie
   Google for Nonprofits). Zeitzone Deutschland, Währung EUR.
2. **Web-Datenstream** für `https://atsv-kirchseeon-marktlauf.de` erstellen.
3. **Mess-ID `G-XXXXXXXXXX`** notieren → wird im Code gebraucht.
4. **(Empfohlen)** GA4 mit dem **Google-Ads-(Grants-)Konto verknüpfen** (GA4 → Verwaltung →
   Produktverknüpfungen → Google Ads).
5. Nach dem Code-Einbau: die Conversion-Events in GA4 als **Schlüsselereignis** markieren und in
   **Google Ads → Ziele → Conversions** importieren.

> **Alternative** ohne GA4: reines **Google-Ads-Conversion-Tag** (`AW-XXXXXXXXX` + Conversion-Label).
> Dann diese Werte statt der `G-…`-ID verwenden.

## 3a. STATUS: Infrastruktur ist bereits gebaut ✅

Die komplette Code-Seite wurde bereits vorbereitet (Remote-Session). **Aktiv wird alles erst, wenn in
`js/consent.js` die echte GA4-ID eingetragen ist** — bis dahin kein Banner, kein Tracking (Seite unverändert).

Bereits vorhanden:
- **`js/consent.js`** — Consent-Banner (self-contained, DE/EN), Google **Consent Mode v2 (strikt Opt-in:
  gtag lädt erst nach „Zustimmen")**, Event-API `window.mlTrack(name)` + `window.mlOpenConsent()`.
  Ganz oben die Konfig-Konstanten **`GA_MEASUREMENT_ID`** (und optional `ADS_CONVERSION_ID`).
- **Eingebunden** auf: `index.html`, `newsletter-erfolg.html`, `newsletter-bestaetigung.html`,
  `danke-newsletter.html`, `impressum.html`, `datenschutz.html` und in der Erfolgsausgabe von `contact.php`.
- **Conversion-Punkte verdrahtet:**
  - `anmeldung_start` → `data-mltrack="anmeldung_start"` an beiden „Jetzt anmelden"-CTAs in `index.html`.
  - `newsletter_confirmed` → `data-mltrack-onload="newsletter_confirmed"` am `<body>` von `newsletter-erfolg.html`.
  - `contact_sent` → `mlTrack('contact_sent')` in der Erfolgsausgabe von `contact.php`.
- **Cookie-Einstellungen-Link** im Footer von `index.html` und `datenschutz.html` (ruft `mlOpenConsent()`),
  i18n-Key `footer.legal.cookies` in `lang/de.json` + `lang/en.json`.
- **Datenschutzerklärung** um Abschnitt „9. Webanalyse mit Google Analytics (nur mit Einwilligung)" ergänzt.
- **`deploy.yml`** stempelt `js/consent.js` fürs Cache-Busting.

### Verbleibende Aufgaben der lokalen Session (mit Browser)
1. GA4-Property anlegen + **Mess-ID** holen (Teil 3) → in `js/consent.js` bei `GA_MEASUREMENT_ID` eintragen.
   *(Optional Ads: `ADS_CONVERSION_ID = "AW-…"`.)*
2. Im **echten Browser** verifizieren: Banner erscheint, „Zustimmen" lädt gtag, „Ablehnen"/kein-Consent
   unterbindet alles; in **GA4 Realtime** eine Test-Conversion sehen.
3. In GA4 die Events (`anmeldung_start`, `newsletter_confirmed`, `contact_sent`) als **Schlüsselereignis**
   markieren und in **Google Ads** importieren.
4. Commit → PR nach `main` → mergen (Deploy).

> Der Rest von Abschnitt 4 ist Hintergrund/Referenz — die Umsetzung ist wie oben beschrieben bereits erfolgt.

## 4. Referenz: ursprünglicher Umsetzungsplan

Empfohlene Standard-Umsetzung (mit dem Nutzer bestätigen):

1. **Consent-Banner** — schlank, barrierefrei, zweisprachig (DE/EN über bestehende i18n in `lang/de.json`
   & `lang/en.json`), passend zum Seiten-Design (Grün `#009640`, Fonts Fredoka/Poppins/Inter).
   „Zustimmen" / „Ablehnen" **gleichwertig**, Auswahl speichern (localStorage), jederzeit widerrufbar
   (z. B. Link in Datenschutz/Footer „Cookie-Einstellungen").
2. **gtag.js + Consent Mode v2** mit der Mess-ID. Default alle Consent-Typen `denied`
   (`ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`); nach Zustimmung `granted`
   via `gtag('consent', 'update', …)`. Ohne Zustimmung **kein** Cookie/kein Netzwerk-Hit.
3. **Conversion-Events** (mit Nutzer final abstimmen, Vorschlag):
   - `anmeldung_start` — Klick auf „Jetzt anmelden" bzw. Wechsel auf den RaceResult-Einzel-Tab.
     *Wichtigstes Signal, da die eigentliche Anmeldung extern auf RaceResult läuft.*
   - `newsletter_confirmed` — Aufruf der Double-Opt-in-Erfolgsseite `newsletter-erfolg.html`.
   - `contact_sent` — erfolgreicher Versand in `contact.php` (dort wird bei Erfolg HTML ausgegeben;
     gtag-Event dort einsetzen oder auf eine Danke-Seite umleiten).
   - *(optional)* `calendar_add` — Klick auf „Zum Kalender hinzufügen" (`marktlauf2026.ics`).
4. **Datenschutzerklärung** (`datenschutz.html`) um einen **Google-Analytics-Abschnitt** ergänzen:
   Zweck, Rechtsgrundlage Art. 6 Abs. 1 lit. a DSGVO (Einwilligung), Empfänger Google, Drittlandtransfer
   USA, Speicherdauer, Widerruf (Cookie-Einstellungen), Opt-out.

### Relevante Dateien / Fundstellen
| Zweck | Datei | Hinweis |
|---|---|---|
| Head (gtag + Consent Default einsetzen) | `index.html` (`<head>`, ~Zeile 8–14) | vor anderen Skripten |
| i18n Texte Banner | `lang/de.json`, `lang/en.json` | flache Keys, `data-i18n`-Attribute; Apply via `js/main.js` (`applyTranslations`, nutzt `innerHTML`) |
| „Jetzt anmelden"-CTA | `index.html` Nav (`href="#anmeldung"`, Klasse `nav-cta`) + Hero-CTA + Anmeldung-Tabs (`#tab-einzel`) | RaceResult Event-ID **412617**, Server `events2.raceresult.com` |
| Newsletter-Erfolg | `newsletter-erfolg.html` | Event beim Laden feuern |
| Kontakt-Erfolg | `contact.php` (Erfolg gibt HTML aus, ~Zeile 45) | gtag-Snippet in Erfolgs-Ausgabe |
| Datenschutz | `datenschutz.html` | neuen Abschnitt ergänzen |
| Design-Tokens | `css/base.css` (`--color-primary` etc.), `css/components.css` | Banner-Styling |

### Technische Randbedingungen
- **Keine externen JS/CSS ohne Not** — gtag von `googletagmanager.com` ist ok (nur nach Consent laden
  bzw. Consent-Default vorab). Consent-Banner selbst **ohne** Fremd-Library bauen (kein Cookiebot etc.,
  außer der Nutzer wünscht es).
- i18n: `applyTranslations()` in `js/main.js` setzt `innerHTML` für `[data-i18n]`; neue Keys in **beide**
  JSON-Dateien, sonst greift der Fallback-Text im HTML.
- `.htaccess` hat bereits Caching-Header; neue JS-Datei würde vom Deploy per `?v=<sha>` gebustet
  (siehe `deploy.yml` Stamp-Step — ggf. neue JS dort in die `sed`-Liste aufnehmen).
- Nach Fertigstellung: commit auf `claude/marktlauf-repo-issue-yjlpxp`, PR nach `main`, Nutzer mergt →
  Auto-Deploy. **Merge löst Live-Deployment aus.**

## 5. Bereits erledigt (Kontext, nicht nochmal machen)

Diese Ad-Grants-Punkte sind schon live (PRs #3–#5 gemergt):
- FAQ-Bereich (`#faq`) inkl. Header-Nav-Link; toter `#faq`-Link repariert.
- Performance: Leaflet lädt on-demand (`js/maps.js`), `.htaccess`-Caching, Flaticon → lokales
  `assets/images/icon-gpx.svg`, Hero-LCP (H1 ohne Opacity-Fade + Fredoka-Font-Preload).
- ~41 MB ungenutzte Marken-Kit-Assets unter `assets/images/social-media/` gelöscht
  (nur `social-media/mail/` bleibt — genutzt von `src/sponsor_brief.php`).
- PageSpeed zuletzt: **Mobil 80 / Desktop 89** (Ad-Grants-Schwelle ~50 klar erfüllt).

**Noch offen (nicht Teil dieser Tracking-Aufgabe):** Sponsoren-Platzhalter entfernen (separater Track);
Strecken-Karten ersetzen, sobald Genehmigung vorliegt.

## 6. Definition of Done

- [ ] Mess-ID `G-…` (oder Ads `AW-…`+Label) vom Nutzer erhalten.
- [ ] Consent-Banner (DE/EN) + Consent Mode v2 eingebaut, Default `denied`.
- [ ] gtag lädt/trackt erst nach Zustimmung; „Ablehnen" unterbindet alles.
- [ ] Conversion-Events feuern (mind. `anmeldung_start`, `newsletter_confirmed`, `contact_sent`).
- [ ] Datenschutzerklärung ergänzt; Widerruf/Cookie-Einstellungen erreichbar.
- [ ] In GA4 **Realtime** eine Test-Conversion sichtbar; Events als Schlüsselereignis markiert und
      in Google Ads importiert.
- [ ] PR nach `main`, gemergt, deployt; auf der Live-Seite verifiziert.
