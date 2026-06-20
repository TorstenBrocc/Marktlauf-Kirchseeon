ATSV Kirchseeon Marktlauf Homepage

Ein performantes, modernes Webprojekt für den Marktlauf der Gemeinde Kirchseeon. Das Projekt zeichnet sich durch sauberen Code, barrierefreies Design und eine professionelle Deployment-Pipeline aus.

🚀 Features & Seiten
- Mehrsprachigkeit (i18n): Dynamische Sprachumschaltung (DE/EN) basierend auf JSON-Dateien (lang/de.json, lang/en.json).
- SEO & Social Media: Integrierte Open Graph (OG) Meta-Tags für optimales Teilen in sozialen Netzwerken.
- Rechtliche Seiten: Vollständige Einbindung von Impressum (impressum.html) und Datenschutz (datenschutz.html).
- Responsives Design: Vollständig optimiert für mobile und Desktop-Ansichten.

🎨 Design
Das visuelle Design wurde grundlegend modernisiert, um einen klaren, sportlichen und zugänglichen Auftritt zu gewährleisten. Im Vergleich zum alten Design liegt der Fokus auf besserer Lesbarkeit, semantischem HTML und klarer visueller Hierarchie.

Markenfarben:

ATSV Green: #009640 (Primärfarbe für Wiedererkennung und Vertrauen)
Accent Orange: #ff6b35 (Akzentfarbe für Call-to-Actions und wichtige Hervorhebungen)
Layout-Prinzipien:

Klares, modulares CSS (Base, Layout, Components).
Semantisches HTML5 für bessere Barrierefreiheit und SEO.

🛠️ Tech Stack
- Frontend: Vanilla HTML5, CSS3, JavaScript (ES6+). Es werden keine externen Frameworks oder Bibliotheken (wie React, Vue oder Bootstrap) verwendet, um maximale Performance und Kontrolle zu gewährleisten.
- Datenhaltung: JSON (für i18n-Übersetzungen).
- Deployment: GitHub Actions CI/CD mit automatisiertem SSH-Deploy zu Strato.
- Version Control: Git

⚙️ DevOps & Deployment
Dieses Projekt implementiert eine professionelle CI/CD (Continuous Integration / Continuous Deployment) Pipeline.

Workflow: GitHub Actions → Strato per SSH
Jeder Push auf den main-Branch löst eine GitHub Actions Workflow aus, der:

Den Zustand des Repositories validiert.
Nicht benötigte Dateien herausfiltert (z.B. .git, Konfigurationsdateien).
Die aktualisierten Dateien via SSH-Deploy sicher auf den Strato-Produktionsserver überträgt.

📁 Projektstruktur

.
├── assets/ # Bilder, Logos, GPX-Routen
├── css/ # Modulares Styling (base.css, layout.css, components.css)
├── js/ # Vanilla JS Logik (main.js)
├── lang/ # i18n Sprachdateien (de.json, en.json)
├── index.html # Startseite
├── impressum.html # Impressum
├── datenschutz.html # Datenschutzerklärung
└── README.md # Projekt-Dokumentation
