-- 011_einstellungen.sql
-- Key/Value-Einstellungen für Event-Daten und externe Links

CREATE TABLE einstellungen (
  `key`   VARCHAR(64) NOT NULL PRIMARY KEY,
  `value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
