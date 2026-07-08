<?php
require_once __DIR__ . '/src/logger.php';

// 1. Einstellungen (nur hier, nicht im HTML)
$empfaenger = "info@atsv-kirchseeon-marktlauf.de";          // deine Zieladresse
$zeichenkodierung = "UTF-8";
$sprache = "de";

// 2. Daten aus dem Formular holen
$vorname   = htmlspecialchars($_POST["vorname"] ?? "", ENT_QUOTES, $zeichenkodierung);
$nachname  = htmlspecialchars($_POST["nachname"] ?? "", ENT_QUOTES, $zeichenkodierung);
$email     = filter_var($_POST["email"] ?? "", FILTER_VALIDATE_EMAIL);
$betreff   = htmlspecialchars($_POST["betreff"] ?? "", ENT_QUOTES, $zeichenkodierung);
$nachricht = htmlspecialchars($_POST["nachricht"] ?? "", ENT_QUOTES, $zeichenkodierung);

// 3. Basis‑Header vorbereiten
$header = "";
if ($email) {
    $header .= "Reply-To: $email\r\n";
}

// 4. Absender: möglichst eine Strato‑Domain‑Adresse (empfohlen)
$absender = "formmail@atsv-kirchseeon-marktlauf.de";   // anpassen
$header  .= "From: $absender\r\n";
$header  .= "Content-Type: text/plain; charset=$zeichenkodierung\r\n";
$header  .= "MIME-Version: 1.0\r\n";

// 5. Betreff schöner machen
if (!$betreff) {
    $betreff = "Kontaktanfrage von $vorname $nachname";
} else {
    $betreff = "Kontaktanfrage: $betreff";
}

// 6. Mailtext zusammensetzen
$mailText = "Vorname: $vorname\n";
$mailText .= "Nachname: $nachname\n";
$mailText .= "E-Mail: $email\n";
$mailText .= "Datum: " . date("Y-m-d H:i:s") . "\n\n";
$mailText .= "Nachricht:\n$nachricht\n";

// 7. Mail senden
if ($email && $nachricht) {
    if (mail($empfaenger, $betreff, $mailText, $header)) {
        echo "<h1 style='font-family: sans-serif; text-align: center; margin-top: 50px;'>Nachricht erfolgreich gesendet!</h1>";
        echo "<p style='font-family: sans-serif; text-align: center;'>Vielen Dank für Ihre Nachricht.</p>";
        echo "<p style='font-family: sans-serif; text-align: center;'><a href='index.html'>Zurück zur Webseite</a></p>";
    } else {
        logError('contact.php: mail() fehlgeschlagen (Empfaenger ' . $empfaenger . ')');
        echo "<h1 style='font-family: sans-serif; text-align: center; margin-top: 50px;'>Fehler beim Senden</h1>";
        echo "<p style='font-family: sans-serif; text-align: center;'>Die Nachricht konnte nicht versandt werden. Bitte versuchen Sie es später erneut oder kontaktieren Sie uns direkt per E-Mail.</p>";
        echo "<p style='font-family: sans-serif; text-align: center;'><a href='index.html'>Zurück zur Webseite</a></p>";
    }
} else {
    echo "<h1 style='font-family: sans-serif; text-align: center; margin-top: 50px;'>Ungültige Eingabe</h1>";
    echo "<p style='font-family: sans-serif; text-align: center;'>Bitte alle Felder korrekt ausfüllen.</p>";
    echo "<p style='font-family: sans-serif; text-align: center;'><a href='index.html'>Zurück zur Webseite</a></p>";
}
?>