CREATE TABLE post_race_contents (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    race_id          VARCHAR(64)  NULL,
    titel            VARCHAR(255) NULL,
    llm_provider     VARCHAR(16)  NULL,
    llm_text_article MEDIUMTEXT   NULL,
    llm_text_social  MEDIUMTEXT   NULL,
    status           ENUM('draft','approved') NOT NULL DEFAULT 'draft',
    erstellt_von     INT UNSIGNED NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
