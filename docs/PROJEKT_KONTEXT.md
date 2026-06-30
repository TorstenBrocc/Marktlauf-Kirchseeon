# Projekt-Kontext: ATSV Kirchseeon Marktlauf

## Technische Umgebung

- **Hosting**: Strato Shared Hosting
- **PHP**: 8.4 FastCGI
- **Datenbank**: MySQL via PDO
- **Architektur**: Dependency-free (kein Composer), UUID-basierte Auth-Tokens

## .user.ini Konfiguration

PHP-Einstellungen für den Web-Kontext werden via `.user.ini` gesteuert (nicht versioniert, siehe `.gitignore`). Template: `storage/.user.ini.sample`.

Wichtig für künftiges Debugging: .user.ini gilt nur im FastCGI/Web-Kontext, nicht im CLI-Kontext. `php -i` per SSH zeigt absichtlich 'no value' bei error_log — kein Bug, sondern CLI-SAPI-Verhalten. Test im Web-Kontext erfolgt via Trigger-Skript + curl, nicht via CLI.

## Gelöste Probleme

### GELÖST: User-Invite-Mail-Bug (30.06.2026)

- **Symptom**: Einladungsmail an torsten.tyras@outlook.com kam nicht an (weder Posteingang noch Spam)
- **Ursache**: kein Code-Bug — sendUserInvite() lief korrekt durch, SMTP-Mailer meldete erfolgreichen Versand (verifiziert via temporäres Debug-Log: "sendUserInvite returned=true")
- **Vermuteter Auslöser**: einmaliger Zustellungs-Hänger bei Outlook, kein reproduzierbares Muster. Re-Test mit neu angelegtem User kam zuverlässig an, Login mit Passwortsetzung funktioniert.
- **Nebenbefund (eigentlicher struktureller Fehler dieser Session)**: error_log()-Calls in orga/api/user_invite.php zeigten auf nicht-existentes storage/logs/error.log statt korrektem storage/logs/php_errors.log. Dadurch fälschliches Vertrauen in "kein Fehler geloggt = Code läuft fehlerfrei", obwohl die Datei schlicht nie existierte.
- **Strukturfix (Commit 8e9e945)**: zentrale logError()-Funktion in src/logger.php eingeführt. 12 Dateien refactored, alle hartcodierten error_log(..., 3, __DIR__ . '/...')-Aufrufe ersetzt durch logError($message). Single Source of Truth für Log-Pfad: storage/logs/php_errors.log, einheitlich mit Timestamp-Präfix.
  
  Betroffene Dateien: orga/api/user_invite.php, file_download.php, file_upload.php, file_delete.php, user_deactivate.php, user_update.php, sponsor_crud.php, aufgabe_crud.php, helfer_bestaetigen.php, helfer_register.php, helfer/file_download.php, src/channels/mail.php

- **Status**: kein offenes Tech-Debt-Item mehr zu diesem Thema.
