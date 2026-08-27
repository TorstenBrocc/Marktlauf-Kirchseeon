<?php
/**
 * Social-Insights (Auswertung): der make.com-Rücklauf (Reichweite/Likes) je gesendetem
 * Post, sichtbar gemacht. Reine Lese-/Aggregat-Sicht für den Auswertungs-Reiter der
 * Social-Pipeline (orga/social_fahrplan.php?ansicht=auswertung).
 *
 * Quelle: post_race_contents — Insights-Spalten (Migrationen 083/085/088) + anlass_key
 * (068). anlass_key hängt AM Post und überlebt das Wiederkehr-Vorrücken (das den Post vom
 * Fahrplan-Eintrag löst) → die Themen-Zuordnung bleibt auch bei iterativen Themen stabil.
 *
 * FB-Reichweite wird bewusst nicht erhoben (kein nativer make-Weg, Inhaber „einfach
 * halten") — der Callback setzt sie nie, deshalb taucht sie hier gar nicht als Spalte auf.
 *
 * Gruppierung nach Thema (anlass_key) ist damit schema-frei nachrüstbar (reiner View) —
 * bewusst noch nicht gebaut, solange zu wenige Datenpunkte je Thema vorliegen.
 */

declare(strict_types=1);

/**
 * Alle gesendeten Posts mit ihren Insights, neueste zuerst — für die sortierbare Liste.
 * Robust gegen (noch) fehlende Spalten/Tabelle: liefert dann eine leere Liste.
 *
 * @return array<int, array<string, mixed>>
 */
function socialInsightsPosts(PDO $pdo): array
{
    try {
        return $pdo->query(
            "SELECT id, anlass_key, gesendet_am,
                    ig_reichweite, ig_likes, fb_likes,
                    ig_permalink, fb_permalink, versand_insights_am
               FROM post_race_contents
              WHERE status = 'gesendet'
           ORDER BY gesendet_am DESC, id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Schlanke Kennzahlen für den Kopf (B): Gesamt-Reichweite (nur IG existiert),
 * Ø Likes/Post (IG+FB summiert je Post) und der beste Post (nach Reichweite, sonst Likes).
 *
 * @param  array<int, array<string, mixed>> $posts Ergebnis von socialInsightsPosts()
 * @return array{gesamt_reichweite:int, schnitt_likes:?float, posts_gesamt:int, bester:?array<string,mixed>}
 */
function socialInsightsKennzahlen(array $posts): array
{
    $sumReach = 0;
    $sumLikes = 0;
    $nLikes   = 0;
    $bester   = null;
    $bestScore = -1;

    foreach ($posts as $p) {
        if ($p['ig_reichweite'] !== null) {
            $sumReach += (int) $p['ig_reichweite'];
        }
        $hatLikes = ($p['ig_likes'] !== null || $p['fb_likes'] !== null);
        $likes    = (int) ($p['ig_likes'] ?? 0) + (int) ($p['fb_likes'] ?? 0);
        if ($hatLikes) {
            $sumLikes += $likes;
            $nLikes++;
        }
        // Bester Post: nach IG-Reichweite, fällt auf die Likes-Summe zurück, wenn keine
        // Reichweite vorliegt (z. B. reine FB-Rückmeldung).
        $score = $p['ig_reichweite'] !== null ? (int) $p['ig_reichweite'] : $likes;
        if ($score > $bestScore) {
            $bestScore = $score;
            $bester    = $p;
        }
    }

    return [
        'gesamt_reichweite' => $sumReach,
        'schnitt_likes'     => $nLikes > 0 ? round($sumLikes / $nLikes, 1) : null,
        'posts_gesamt'      => count($posts),
        'bester'            => $bester,
    ];
}
