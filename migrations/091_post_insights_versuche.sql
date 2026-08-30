-- make.com-Optimierung Stage C (Robustheit): Zaehler fehlgeschlagener Insights-Abrufe je Post.
-- Ein dauerhaft nicht abrufbarer Post (geloeschte/ungueltige IG-Media-ID -> Graph-API
-- GraphMethodException 100) wurde bisher endlos wiedervorgelegt, weil versand_insights_am nur
-- bei Erfolg gesetzt wird (posts_pending_insights.php). Der Make-Error-Handler meldet den
-- Fehler jetzt via post_status_callback.php (insights_status=failed) zurueck; dieser Zaehler
-- laesst voruebergehende Fehler noch ein paar Retries zu und gibt danach auf (Post faellt aus
-- der Pending-Liste, sobald insights_versuche die Schwelle erreicht). Erfolg setzt zurueck.
-- Additiv, NOT NULL mit Default 0 (Bestandszeilen: 0 = noch kein Fehlversuch).
ALTER TABLE post_race_contents
    ADD COLUMN insights_versuche INT NOT NULL DEFAULT 0;
