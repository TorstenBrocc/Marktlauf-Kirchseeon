-- 006_sponsors.sql
-- Sponsoren-Verwaltung für Marktlauf

CREATE TABLE sponsors (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  firma           VARCHAR(255) NOT NULL,
  ansprechpartner VARCHAR(255) NULL,
  email           VARCHAR(255) NULL,
  paket           ENUM('bronze','silber','gold') NULL,
  summe           DECIMAL(10,2) NULL DEFAULT 0,
  status          ENUM('angefragt','zugesagt','abgelehnt','bezahlt') NOT NULL DEFAULT 'angefragt',
  kein_kontakt    TINYINT(1) NOT NULL DEFAULT 0,
  notizen         TEXT NULL,
  wiedervorlage   DATE NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sponsor_aufgaben (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sponsor_id  INT UNSIGNED NOT NULL,
  titel       VARCHAR(255) NOT NULL,
  erledigt    TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_aufgabe_sponsor FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
