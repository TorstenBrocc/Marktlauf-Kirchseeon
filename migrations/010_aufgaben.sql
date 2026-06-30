-- 010_aufgaben.sql
-- Orga-weite Aufgaben (unabhängig von Sponsor-Aufgaben)

CREATE TABLE aufgaben (
  id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titel                   VARCHAR(255) NOT NULL,
  notiz                   TEXT NULL,
  status                  ENUM('offen','in_arbeit','erledigt') NOT NULL DEFAULT 'offen',
  verantwortlich_user_id  INT UNSIGNED NULL,
  faellig_am              DATE NULL,
  erinnerung_gesendet     TINYINT(1) NOT NULL DEFAULT 0,
  kontext_typ             VARCHAR(32) NULL,
  kontext_id              INT UNSIGNED NULL,
  trello_card_id          VARCHAR(64) NULL,
  created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_aufgabe_user FOREIGN KEY (verantwortlich_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
