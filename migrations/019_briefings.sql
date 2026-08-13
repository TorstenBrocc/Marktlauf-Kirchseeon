-- 019_briefings.sql
-- Interne Helfer-Briefings/Infos für den "Helfer-Draht".
-- Erscheinen (sichtbar=1) in der Helfer-Zugangsansicht (helfer/zugang.php).
CREATE TABLE IF NOT EXISTS briefings (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  text         TEXT NOT NULL,
  prioritaet   ENUM('normal','wichtig','notfall') NOT NULL DEFAULT 'normal',
  sichtbar     TINYINT(1) NOT NULL DEFAULT 1,
  erstellt_von INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
