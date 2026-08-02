-- Live-Ticker Nachrichten
CREATE TABLE IF NOT EXISTS ticker_posts (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    nachricht   TEXT            NOT NULL,
    typ         ENUM('info','warnung','ergebnis') NOT NULL DEFAULT 'info',
    aktiv       TINYINT(1)      NOT NULL DEFAULT 1,
    erstellt_am DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erstellt_von INT UNSIGNED   NULL,
    FOREIGN KEY (erstellt_von) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
