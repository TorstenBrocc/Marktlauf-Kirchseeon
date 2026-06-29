-- 008_dateien.sql
-- Dateiverwaltung für Orga und Helfer

CREATE TABLE dateien (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bereich         ENUM('orga','helfer') NOT NULL,
  dateiname       VARCHAR(255) NOT NULL,
  originalname    VARCHAR(255) NOT NULL,
  mimetype        VARCHAR(128) NOT NULL,
  groesse         INT UNSIGNED NOT NULL,
  hochgeladen_von INT UNSIGNED NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_datei_user FOREIGN KEY (hochgeladen_von) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
