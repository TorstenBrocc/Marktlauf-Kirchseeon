-- 043_rechnung_versand_log.sql
-- Fortlaufendes Protokoll des Rechnungs-Versands an Sponsoren (eine Zeile je Sendung).
-- Bewusst als Log (nicht als Einzelfelder auf sponsor_rechnungen): erneutes Senden
-- erzeugt eine weitere Zeile; die Historie bleibt lückenlos nachvollziehbar.

CREATE TABLE rechnung_versand_log (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rechnung_id    INT UNSIGNED NOT NULL,
  versendet_am   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  empfaenger     VARCHAR(255) NOT NULL,
  versendet_von  INT UNSIGNED NULL,
  drive_datei_id VARCHAR(255) NULL,
  ergebnis       ENUM('ok','fehler') NOT NULL DEFAULT 'ok',
  hinweis        VARCHAR(500) NULL,

  KEY idx_rvl_rechnung (rechnung_id),
  CONSTRAINT fk_rvl_rechnung FOREIGN KEY (rechnung_id) REFERENCES sponsor_rechnungen(id) ON DELETE CASCADE,
  CONSTRAINT fk_rvl_user     FOREIGN KEY (versendet_von) REFERENCES users(id)            ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
