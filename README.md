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
- **Sponsor-Mini-CRM** (`sponsoren.php`, `sponsor_form.php`,
  `sponsor_briefe.php`) — Kontakte, Notizen, Import/Export, Anschreiben.
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

---

Drittanbieter-Lizenzen: siehe [`THIRD-PARTY-NOTICES.md`](THIRD-PARTY-NOTICES.md).
