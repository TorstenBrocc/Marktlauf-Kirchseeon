# ATSV Kirchseeon Marktlauf

Web-Auftritt und Organisations-Zentrale für den **Marktlauf** des ATSV Kirchseeon.
Öffentliche Event-Website plus ein login-geschütztes Orga-Dashboard für das
Vorbereitungsteam. Fokus auf sauberen Code, Barrierefreiheit und eine
automatisierte Deployment-Pipeline.

- **Live:** https://www.atsv-kirchseeon-marktlauf.de/
- **Renntag:** Sonntag, 20.09.2026

---

## 🚀 Öffentliche Website

- **Mehrsprachigkeit (i18n):** Dynamische DE/EN-Umschaltung auf Basis von JSON
  (`lang/de.json`, `lang/en.json`) — keine hartcodierten Texte im JS.
- **Interaktive Streckenkarten:** Leaflet + leaflet-gpx für die GPX-Routen
  (1 / 2 / 5 / 10 km) inkl. Distanz, Höhenmetern und dynamischem Höhenprofil.
- **Zeitplan & Distanzen:** Bambini (500m), Schüler (1 & 2 km), Jugend &
  Erwachsene (5 & 10 km) mit Timeline und Siegerehrungen.
- **Newsletter-Anmeldung:** Double-Opt-in-Seiten (`newsletter-bestaetigung.html`,
  `newsletter-erfolg.html`, `danke-newsletter.html`), Versand über Brevo.
- **Helfer-Anmeldung:** `helfer-anmeldung.php` als öffentlicher Einstieg ins
  Helfer-System.
- **Kontaktformular:** `contact.php` (serverseitiger Mail-Versand).
- **SEO & Social:** Open-Graph-Tags, `sitemap.xml`, `robots.txt`, `humans.txt`,
  Kalender-Datei `marktlauf2026.ics`.
- **Rechtliches:** `impressum.html`, `datenschutz.html`.
- **Responsives Design:** Mobile-First, semantisches HTML5, WCAG-orientiert.

## 🔐 Orga-Dashboard (`orga/`, login-geschützt)

Interne Organisations-Zentrale hinter Session-Login — nicht öffentlich beworben,
nur für das Orga-Team:

- **Helfer-Verwaltung** (`helfer.php`, `schichten.php`) — Registrierung,
  Bestätigung, Schichtplanung/-zuteilung.
- **Sponsor-Mini-CRM** (`sponsoren.php`, `sponsor_form.php`) — Kontakte, Notizen,
  Import/Export. Anschreiben liegen daneben als eigene Seite je Briefvorlage
  (`erstanschreiben.php`, `folgeanschreiben.php`, `bestaetigungen.php`,
  `freier_brief.php`, `bedingungen.php`, `anschreiben_einstellungen.php`).
- **Aufgaben** (`_sidebar.php`-Widget, Aufgaben-CRUD) mit Erinnerungen.
- **Dateiablage** (`dateien.php`) — getrennte Bereiche für intern und Helfer
  (`helfer/zugang.php`).
- **Social-Media-Orchestrator** (`social_orchestrator.php`) — LLM-gestützte
  Content-Erstellung, provider-agnostisch.
- **Benutzerverwaltung** (`benutzer.php`, Einladungen, Aktivierung).

## 🛠️ Tech Stack

| Schicht | Technologie |
|---|---|
| Frontend | Vanilla HTML5, CSS3 (modular: `base`/`layout`/`components`), JavaScript ES6+ |
| Karten | Leaflet.js + leaflet-gpx |
| i18n | JSON (`lang/de.json`, `lang/en.json`) |
| Backend | PHP 8.4 (nativ, kein Composer) + PDO/MySQL |
| Externe Dienste | Brevo (Newsletter/Mail), Race Result (Anmeldung/Ergebnisse) |
| Hosting | Strato Shared Hosting |
| Deployment | GitHub Actions → SSH-Deploy auf Strato |

Es kommen bewusst keine Frontend-Frameworks (React/Vue/Bootstrap) zum Einsatz —
für maximale Performance und Kontrolle.

## 📁 Projektstruktur

```text
.
├── index.html              # Startseite (One-Page)
├── impressum.html          # Impressum
├── datenschutz.html        # Datenschutzerklärung
├── contact.php             # Kontaktformular (Mail-Versand)
├── helfer-anmeldung.php    # Öffentliche Helfer-Anmeldung
├── newsletter-*.html       # Newsletter Double-Opt-in / Danke-Seiten
├── assets/                 # Bilder, Logos, GPX-Strecken (courses/), Sponsorlogos
├── css/                    # Modulares Styling (base.css, layout.css, components.css)
├── js/                     # main.js (Logik), maps.js (Leaflet/GPX)
├── lang/                   # i18n-Sprachdateien (de.json, en.json)
├── orga/                   # Login-geschütztes Orga-Dashboard (Seiten + api/)
├── helfer/                 # Helfer-Zugang (Login-Link, Datei-Download)
├── src/                    # Backend-Bausteine (auth, db, mailer, logger, channels/ …)
├── lib/                    # Vendored PHP-Libs (fpdf/ — PDF-Erzeugung)
├── bin/                    # CLI-Werkzeuge (Migrationen, Cron-Jobs, Lint)
├── migrations/             # Versionierte SQL-Migrationen (via bin/migrate.php)
├── storage/                # Konfig + Datei-/Log-Ablage (nur *.sample im Repo)
├── data/                   # Laufzeitdaten
├── tests/visual/           # Playwright Visual-Regression-Tests
└── .github/workflows/      # CI/CD: deploy, lint, uptime
```

## 🗄️ Datenbank & Migrationen

Schema-Änderungen laufen ausschließlich über versionierte Migrationen in
`migrations/NNN_beschreibung.sql`, angewendet über den Runner:

```bash
php bin/migrate.php status    # offene Migrationen anzeigen
php bin/migrate.php migrate   # alle offenen anwenden
```

Der Runner verwaltet eine `schema_migrations`-Tabelle — es ist jederzeit
nachvollziehbar, welche Migrationen bereits gefahren wurden. Migrationen niemals
manuell per MySQL-Client ausführen.

## ⚙️ Entwicklung & Deployment

- **Konfiguration:** Server-Zugangsdaten liegen in `storage/config.php`, das
  **nicht** im Repo eincheckt und **nicht** vom Deploy überschrieben wird.
  Vorlage: `storage/config.sample.php`.
- **PHP-Lint:** Geänderte `.php` vor dem Deploy linten
  (`bash bin/lint.sh`, nutzt `php:8.4-cli`); zusätzlich läuft `php -l` als
  GitHub-Action bei jedem Push.
- **CI/CD:** Jeder Push auf `main` startet den Deploy-Workflow, der das Repo
  validiert, nicht benötigte/serverseitige Dateien herausfiltert und die
  Änderungen per `rsync` über SSH auf Strato überträgt. Ein separater
  Uptime-Workflow überwacht die Erreichbarkeit.
- **Visual Tests:** Playwright unter `tests/visual/`.

## 🎨 Markenfarben

- **ATSV Green:** `#009640` (Primärfarbe) — Varianten `#007230` / `#2ecc71`
- **Accent Orange:** `#ff6b35` (Call-to-Actions)

Layout-Prinzipien: klares, modulares CSS, semantisches HTML5, saubere visuelle
Hierarchie.

## 🔒 Sicherheit („Hacker-Dichtigkeit")

Das Orga-Dashboard verarbeitet personenbezogene Daten (Helfer, Sponsoren) und ist
entsprechend gehärtet. Umgesetzte Maßnahmen (Belege im Code):

- **Passwort-Hashing:** `Argon2id` (`password_hash(..., PASSWORD_ARGON2ID)`,
  `bin/hash_password.php`), Verifikation via `password_verify()` (`src/auth.php`).
- **Gehärtete Sessions:** Cookies `HttpOnly` + `SameSite=Strict` + `Secure` (dynamisch
  bei HTTPS), `session_regenerate_id(true)` nach Login (Session-Fixation-Schutz),
  sauberer Logout (`src/auth.php`).
- **CSRF-Schutz:** Token aus `random_bytes(32)`, **timing-safe** verifiziert mit
  `hash_equals()`; alle schreibenden API-Endpunkte unter `orga/api/` prüfen das Token.
- **SQL-Injection-Schutz:** durchgängig PDO **Prepared Statements** mit
  `ATTR_EMULATE_PREPARES => false` und benannten Platzhaltern (`src/db.php`) — keine
  String-Konkatenation von Nutzereingaben in SQL.
- **XSS-Schutz:** Output-Escaping via `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`;
  Header-Injection-Schutz beim Datei-Download (`src/helpers.php`).
- **Brute-Force-/Rate-Limiting:** mehrschichtig — pro E-Mail (5) **und** pro IP (20) je
  15-Minuten-Fenster beim Login, zusätzlich Rate-Limit bei der Registrierung; IPv6 auf
  /64 normalisiert, `X-Forwarded-For` nur bei konfiguriertem `trusted_proxy` (Anti-Spoofing).
- **Input-Validierung:** `FILTER_VALIDATE_EMAIL`, Längenlimits, Whitelist-Prüfungen,
  **Honeypot**-Feld gegen Bots bei der Helfer-Anmeldung.
- **Datei-Upload-Härtung:** MIME-Prüfung per **Inhalt** (`finfo`, nicht per Endung),
  10-MB-Limit, Ablage unter zufälliger UUID (kein Original-Dateiname), Bilder werden
  re-encodiert (entfernt eingebettete Payloads), Speicher in `storage/` außerhalb der
  Web-Auslieferung.
- **Token-Gating:** öffentliche Helfer-Anmeldung nur mit gültigem `access_token`;
  Benutzer-Einladungen als Einmal-Token mit Ablaufdatum.
- **HTTP-Security-Header** (`.htaccess`): `X-Content-Type-Options: nosniff`,
  `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`;
  erzwungenes HTTPS (301) und kanonische Domain.
- **Datei-/Secret-Abschottung:** `Require all denied` für `storage/`, `src/`, `bin/`,
  `migrations/`; Root-`.htaccess` sperrt `.md/.sql/.sqlite/.log/.sample/.sh/.ini/.bak`.
  Secrets liegen **nur** in `storage/config.php` (nicht im Repo); `.gitignore` sperrt
  `config.php`, `.env*`, `*.key`, `*.pem`, `credentials.json`, Logs und Admin-Seeds.

**Bekannte Härtungs-To-dos** (bewusst dokumentiert, nicht kritisch):

- Öffentliches Kontaktformular (`contact.php`) ohne CSRF-Token/Rate-Limit/Honeypot —
  anfällig für Formular-Spam (E-Mail wird immerhin validiert).
- Keine `Content-Security-Policy` und kein `Strict-Transport-Security` (HSTS)-Header.
- Helfer-Datei-Download autorisiert allein über Kenntnis der Helfer-UUID (bewusst
  session-los, aber ohne zusätzliches Ablauf-Token).

## ⚖️ Rechtliches & Datenschutz

- **Impressum & Datenschutz:** `impressum.html`, `datenschutz.html` (öffentlich verlinkt).
- **DSGVO-Grundhaltung:** Schriften self-hosted (kein Google-Fonts-CDN), keine
  Tracking-Cookies für Marketing; Session-Cookies sind technisch notwendig.
- **Auftragsverarbeiter / Datenempfänger:** Brevo (Newsletter), RaceResult
  (Anmeldung/Ergebnisse) und Strato (Hosting/SMTP) — mit AV-Vertrag und in
  `datenschutz.html` benannt. Die LLM-Provider (Gemini/Mistral) und Google Drive
  sind rein interne Orga-Werkzeuge **ohne Personenbezug** (LLMs erzeugen nur
  Social-Media-Content; Drive speichert nur manuell hochgeladene Orga-Dateien) —
  daher kein Datenschutz-Bezug. Details in [`THIRD-PARTY-NOTICES.md`](THIRD-PARTY-NOTICES.md).
- **Einwilligungen:** Newsletter per **Double-Opt-in** (Brevo); bei der Helfer-Anmeldung
  wird die Foto-Einwilligung (`consent_photo`) explizit erfasst.
- **Kartendaten:** OpenStreetMap-Attribution (ODbL) auf den Streckenkarten sichtbar.
- **Drittanbieter-Lizenzen:** vollständige, gegen den Code geprüfte Inventur in
  [`THIRD-PARTY-NOTICES.md`](THIRD-PARTY-NOTICES.md) — inkl. Hinweis zur GPL-3.0-Lizenz
  von `leaflet-elevation` (nur zur Laufzeit vom CDN geladen, nicht mit-verteilt).

---

**Weiterführend:**

- Drittanbieter-Komponenten & Lizenzen: [`THIRD-PARTY-NOTICES.md`](THIRD-PARTY-NOTICES.md)
- Konfigurationsvorlage: `storage/config.sample.php`
