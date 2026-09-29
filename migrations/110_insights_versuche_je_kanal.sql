-- make.com Stage C: failed-insights counter per channel instead of per post (migration 091).
-- With one shared counter a channel that keeps failing (e.g. a deleted Facebook post) pushed
-- the other channel out of the collector list as well. Additive; existing rows inherit the
-- current shared value on both channels, so posts already given up stay given up.
-- insights_versuche stays in place (no longer written) — dropping it is not worth the risk.
ALTER TABLE post_race_contents
    ADD COLUMN ig_insights_versuche INT NOT NULL DEFAULT 0,
    ADD COLUMN fb_insights_versuche INT NOT NULL DEFAULT 0;

UPDATE post_race_contents
   SET ig_insights_versuche = insights_versuche,
       fb_insights_versuche = insights_versuche
 WHERE insights_versuche > 0;
