<?php
/**
 * Einsatzplan als PDF — zwei Ausgaben aus derselben Quelle:
 *
 *   einsatzplanPdfGesamt()  — der komplette Plan (Aufbau bis Abbau) fuer die
 *                             Orga-Ablage; das digitale Gegenstueck zu dem
 *                             Word-Ausdruck, der bisher per WhatsApp lief.
 *   einsatzplanPdfHelfer()  — der persoenliche Plan EINES Helfers, abrufbar
 *                             ueber seine Zugangsseite (helfer/zugang.php).
 *
 * Schriften und Farben wie beim Rechnungs-PDF (src/rechnung_pdf.php): Montserrat
 * fuer Ueberschriften, Poppins fuer Fliesstext, das Gruen nur an wenigen Stellen.
 * FPDF kann kein UTF-8 — jeder Text laeuft durch t() nach Windows-1252.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/fpdf/fpdf.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helfer_aufgaben.php';

class EinsatzplanPdf extends FPDF
{
    private array $green1 = [0, 150, 64];    // #009640
    private array $green2 = [0, 114, 48];    // #007230
    private array $ink    = [31, 42, 34];    // #1f2a22
    private array $body   = [51, 65, 85];    // #334155
    private array $label  = [100, 116, 139]; // #64748b
    private array $muted  = [148, 163, 184]; // #94a3b8
    private array $line   = [230, 232, 224]; // #e6e8e0

    private float $L = 18.0;
    private float $R = 192.0;

    private string $kopfzeile;
    private string $untertitel;
    private string $fusszusatz;

    public function __construct(string $kopfzeile, string $untertitel = '', string $fusszusatz = '')
    {
        parent::__construct('P', 'mm', 'A4');
        $this->kopfzeile  = $kopfzeile;
        $this->untertitel = $untertitel;
        $this->fusszusatz = $fusszusatz;

        $this->AddFont('pop', '', 'Poppins-Light.php');
        $this->AddFont('popmed', '', 'Poppins-Medium.php');
        $this->AddFont('popsb', '', 'Poppins-SemiBold.php');
        $this->AddFont('mont', '', 'Montserrat-SemiBold.php');
        $this->AddFont('montbd', '', 'Montserrat-Bold.php');

        $this->SetMargins($this->L, 14, 210 - $this->R);
        $this->SetAutoPageBreak(true, 20);
        $this->AliasNbPages();
    }

    /** UTF-8 -> Windows-1252 (FPDF-Kernschriften koennen kein UTF-8). */
    private function t(string $s): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $s) ?: $s;
    }

    private function hline(float $y, array $color, float $w = 0.2): void
    {
        $this->SetDrawColor(...$color);
        $this->SetLineWidth($w);
        $this->Line($this->L, $y, $this->R, $y);
    }

    public function Header(): void
    {
        $shield = __DIR__ . '/../assets/images/ATSV_Logo-750x968.png';
        if (is_readable($shield)) {
            $this->Image($shield, $this->R - 14 * 750 / 968, 11, 0, 14);
        }

        $this->SetXY($this->L, 13);
        $this->SetFont('montbd', '', 8);
        $this->SetTextColor(...$this->green1);
        $this->Cell(0, 4, $this->t('ATSV KIRCHSEEON e. V. — MARKTLAUF'), 0, 1, 'L');

        $this->SetX($this->L);
        $this->SetFont('mont', '', 15);
        $this->SetTextColor(...$this->green2);
        $this->Cell(0, 8, $this->t($this->kopfzeile), 0, 1, 'L');

        if ($this->untertitel !== '') {
            $this->SetX($this->L);
            $this->SetFont('pop', '', 9);
            $this->SetTextColor(...$this->body);
            $this->Cell(0, 5, $this->t($this->untertitel), 0, 1, 'L');
        }

        $y = $this->GetY() + 1.5;
        $this->hline($y, $this->line, 0.4);
        $this->SetY($y + 4);
    }

    public function Footer(): void
    {
        $this->SetY(-14);
        $this->hline($this->GetY() - 2, $this->line);
        $this->SetFont('pop', '', 7);
        $this->SetTextColor(...$this->muted);
        $stand = 'Stand ' . date('d.m.Y, H:i') . ' Uhr';
        if ($this->fusszusatz !== '') {
            $stand .= ' · ' . $this->fusszusatz;
        }
        $this->Cell(0, 4, $this->t($stand), 0, 0, 'L');
        $this->Cell(0, 4, $this->t('Seite ' . $this->PageNo() . '/{nb}'), 0, 0, 'R');
    }

    /** Tagesüberschrift (Samstag · 19.09.2026). */
    public function tagUeberschrift(string $label): void
    {
        $this->ensureRaum(18);
        $this->Ln(3);
        $this->SetFont('mont', '', 11);
        $this->SetTextColor(...$this->green2);
        $this->SetX($this->L);
        $this->Cell(0, 6, $this->t($label), 0, 1, 'L');
        $y = $this->GetY() + 0.5;
        $this->hline($y, $this->green1, 0.5);
        $this->SetY($y + 3);
    }

    /**
     * Ein Block: Posten/Schicht mit Nummer, Zeit, Ort, Aufgabe — und der
     * Namensliste, wo sie hingehoert.
     *
     * $opt: titel, meta, beschreibung, bedarf, posten (Nummer), fenster
     *       (Klartext "ab … bis …"), karte (Google-Maps-URL), koord (Text).
     * $namen = []   => "— noch offen —" (Gesamtplan: unbesetzte Schicht)
     * $namen = null => gar keine Namensliste (persönlicher Plan: der Helfer weiß,
     *                  dass er selbst gemeint ist; wer sonst dort eingeteilt ist,
     *                  geht ihn nichts an — und der Plan bleibt DSGVO-schlank).
     */
    public function schichtBlock(array $opt, ?array $namen): void
    {
        $titel   = (string) ($opt['titel'] ?? '');
        $meta    = (string) ($opt['meta'] ?? '');
        $besch   = (string) ($opt['beschreibung'] ?? '');
        $bedarf  = (int) ($opt['bedarf'] ?? 0);
        $marke   = (string) ($opt['marke'] ?? '');
        $fenster = (string) ($opt['fenster'] ?? '');
        $karte   = (string) ($opt['karte'] ?? '');
        $koord   = (string) ($opt['koord'] ?? '');

        $this->ensureRaum(26);

        // Postennummer als Marke vor dem Titel — am Renntag spricht die Orga
        // ueber "Posten 7", nicht ueber den Namen des Standorts.
        $x = $this->L;
        if ($marke !== '') {
            $label = mb_strtoupper($marke);
            $this->SetFont('montbd', '', 8);
            $breite = $this->GetStringWidth($this->t($label)) + 4;
            $this->SetFillColor(...$this->green2);
            $this->Rect($x, $this->GetY() + 0.6, $breite, 4.8, 'F');
            $this->SetTextColor(255, 255, 255);
            $this->SetXY($x, $this->GetY() + 0.6);
            $this->Cell($breite, 4.8, $this->t($label), 0, 0, 'C');
            $x += $breite + 2.5;
        }

        $this->SetXY($x, $this->GetY());
        $this->SetFont('popsb', '', 10);
        $this->SetTextColor(...$this->ink);
        $this->Cell(0, 5.5, $this->t($titel), 0, 1, 'L');

        if ($meta !== '') {
            $this->SetFont('pop', '', 8.5);
            $this->SetTextColor(...$this->label);
            $this->SetX($this->L);
            $this->Cell(0, 4.5, $this->t($meta), 0, 1, 'L');
        }

        // Zeitfenster im Klartext, damit niemand rechnen muss.
        if ($fenster !== '') {
            $this->SetFont('popsb', '', 9);
            $this->SetTextColor(...$this->green2);
            $this->SetX($this->L);
            $this->Cell(0, 4.8, $this->t($fenster), 0, 1, 'L');
        }

        if ($besch !== '') {
            $this->SetFont('pop', '', 8);
            $this->SetTextColor(...$this->muted);
            $this->SetX($this->L);
            $this->MultiCell($this->R - $this->L, 4, $this->t($besch), 0, 'L');
        }

        // Standort: klickbar im PDF, dazu die Koordinaten zum Abtippen/Vorlesen.
        if ($karte !== '') {
            $this->SetFont('pop', '', 8.5);
            $this->SetTextColor(...$this->green1);
            $this->SetX($this->L);
            $this->Cell(0, 4.6, $this->t('Standort in Google Maps öffnen' . ($koord !== '' ? ' (' . $koord . ')' : '')), 0, 1, 'L', false, $karte);
        } elseif ($koord !== '') {
            $this->SetFont('pop', '', 8);
            $this->SetTextColor(...$this->muted);
            $this->SetX($this->L);
            $this->Cell(0, 4.2, $this->t($koord), 0, 1, 'L');
        }

        if ($namen === null) {
            $this->Ln(2.5);
            return;
        }

        $this->Ln(1);
        $this->SetFont('pop', '', 9.5);
        $this->SetTextColor(...$this->body);

        if ($namen === []) {
            $this->SetX($this->L + 4);
            $this->SetTextColor(...$this->muted);
            $this->Cell(0, 4.8, $this->t('— noch offen —'), 0, 1, 'L');
        } else {
            foreach ($namen as $n) {
                $this->ensureRaum(8);
                $this->SetX($this->L + 4);
                $this->Cell(0, 4.8, $this->t($n), 0, 1, 'L');
            }
        }

        if ($bedarf > 0 && count($namen) < $bedarf) {
            $this->SetX($this->L + 4);
            $this->SetFont('pop', '', 8);
            $this->SetTextColor(...$this->muted);
            $this->Cell(0, 4.2, $this->t('(' . count($namen) . ' von ' . $bedarf . ' Plätzen besetzt)'), 0, 1, 'L');
        }

        $this->Ln(2.5);
    }

    /** Freitext-Absatz (Begrüßung, Hinweise). */
    public function absatz(string $text, float $groesse = 9.0): void
    {
        $this->SetFont('pop', '', $groesse);
        $this->SetTextColor(...$this->body);
        $this->SetX($this->L);
        $this->MultiCell($this->R - $this->L, 4.6, $this->t($text), 0, 'L');
        $this->Ln(2);
    }

    /** Hinweiskasten am Fuß des persönlichen Plans. */
    public function kasten(string $ueberschrift, array $zeilen): void
    {
        $this->ensureRaum(14 + count($zeilen) * 5);
        $y = $this->GetY();
        $this->SetFillColor(247, 249, 245);
        $this->SetDrawColor(...$this->line);
        $this->Rect($this->L, $y, $this->R - $this->L, 8 + count($zeilen) * 4.6, 'DF');

        $this->SetXY($this->L + 3, $y + 2.5);
        $this->SetFont('mont', '', 8);
        $this->SetTextColor(...$this->green2);
        $this->Cell(0, 4, $this->t($ueberschrift), 0, 1, 'L');

        $this->SetFont('pop', '', 8.5);
        $this->SetTextColor(...$this->body);
        foreach ($zeilen as $z) {
            $this->SetX($this->L + 3);
            $this->Cell(0, 4.4, $this->t($z), 0, 1, 'L');
        }
        $this->Ln(3);
    }

    /** Seitenumbruch anstoßen, wenn der Rest der Seite zu knapp wird. */
    private function ensureRaum(float $hoehe): void
    {
        if ($this->GetY() + $hoehe > 297 - 20) {
            $this->AddPage();
        }
    }
}

/**
 * Datenlader: kompletter Einsatzplan, gruppiert nach Tag, Schichten in der
 * Reihenfolge des Boards (Handsortierung), Helfer alphabetisch je Schicht.
 */
function einsatzplanHatPostenfelder(PDO $pdo): bool
{
    static $da = null;
    if ($da === null) {
        try {
            // `kennung` kam mit 102, `postennummer` mit 097 — beide zusammen abfragen,
            // sonst waehlt die Abfrage eine Spalte, die es auf diesem Stand nicht gibt.
            $da = $pdo->query("SHOW COLUMNS FROM schichten LIKE 'kennung'")->fetch() !== false;
        } catch (PDOException $e) {
            $da = false;
        }
    }
    return $da;
}

function einsatzplanDatenGesamt(PDO $pdo): array
{
    $extra = einsatzplanHatPostenfelder($pdo) ? ', postennummer, kennung, lat, lon' : '';
    $schichten = $pdo->query('
        SELECT id, titel, beschreibung, ort, tag, von, bis, zeitfenster, bedarf' . $extra . '
        FROM schichten
        ORDER BY ' . schichtenOrderBy($pdo) . '
    ')->fetchAll();

    $namen = [];
    $stmt = $pdo->query('
        SELECT sz.schicht_id, h.vorname, h.nachname
        FROM schicht_zuteilung sz
        JOIN helfer h ON h.id = sz.helfer_id
        ORDER BY h.nachname, h.vorname
    ');
    foreach ($stmt as $row) {
        $namen[(int) $row['schicht_id']][] = trim($row['vorname'] . ' ' . $row['nachname']);
    }

    $tage = [];
    foreach ($schichten as $s) {
        $tag = (string) ($s['tag'] ?? '');
        $s['namen'] = $namen[(int) $s['id']] ?? [];
        $tage[$tag][] = $s;
    }

    return $tage;
}

/** Datenlader: die Einsätze eines Helfers in Board-Reihenfolge. */
function einsatzplanDatenHelfer(PDO $pdo, int $helferId): array
{
    $extra = einsatzplanHatPostenfelder($pdo) ? ', sc.postennummer, sc.kennung, sc.lat, sc.lon' : '';
    $stmt = $pdo->prepare('
        SELECT sc.id, sc.titel, sc.beschreibung, sc.ort, sc.tag, sc.von, sc.bis, sc.zeitfenster' . $extra . '
        FROM schicht_zuteilung sz
        JOIN schichten sc ON sc.id = sz.schicht_id
        WHERE sz.helfer_id = :id
        ORDER BY ' . schichtenOrderBy($pdo, 'sc') . '
    ');
    $stmt->execute(['id' => $helferId]);
    return $stmt->fetchAll();
}

/**
 * Zeitfenster im Klartext: ab wann vor Ort, bis wann bleiben.
 * "ab 11:00 Uhr vor Ort, bis 12:30 Uhr" liest sich am Renntag schneller als
 * "11:00–12:30".
 */
function einsatzplanFenster(array $s): string
{
    // Ohne feste Uhrzeit steht das Zeitfenster schon in der Meta-Zeile — dann
    // hier nichts, sonst liest der Helfer zweimal "während des Laufs".
    if (empty($s['von'])) {
        return '';
    }
    $t = 'ab ' . substr((string) $s['von'], 0, 5) . ' Uhr vor Ort';
    if (!empty($s['bis'])) {
        $t .= ', bis ' . substr((string) $s['bis'], 0, 5) . ' Uhr';
    }
    return $t;
}

/** Google-Maps-Link zu einem Posten ('' wenn keine Koordinaten hinterlegt). */
function einsatzplanKartenLink(array $s): string
{
    if (empty($s['lat']) || empty($s['lon'])) {
        return '';
    }
    return 'https://www.google.com/maps/search/?api=1&query='
        . rawurlencode($s['lat'] . ',' . $s['lon']);
}

/** Kennung fürs Etikett: "V2" bei den Stationen, sonst "Posten 7". */
function einsatzplanMarke(array $s): string
{
    if (!empty($s['kennung'])) {
        return (string) $s['kennung'];
    }
    return !empty($s['postennummer']) ? 'Posten ' . (int) $s['postennummer'] : '';
}

/**
 * Titel ohne den "Streckenposten N · "-Präfix, wenn die Nummer ohnehin als
 * eigene Marke davorsteht — sonst liest der Helfer "POSTEN 7 Streckenposten 7 …".
 */
function einsatzplanKurzTitel(array $s): string
{
    $titel = (string) $s['titel'];
    if (!empty($s['postennummer'])) {
        $titel = preg_replace('/^Streckenposten\s+\d+\s*·\s*/u', '', $titel) ?? $titel;
    }
    return $titel;
}

/** Zeit-/Ortszeile einer Schicht für das PDF. */
function einsatzplanMetaZeile(array $s): string
{
    $teile = [];
    $zeit = helferSchichtZeitfenster($s);
    if ($zeit !== '') {
        // Feste Uhrzeit bekommt "Uhr", ein Freitext-Fenster nicht.
        $teile[] = !empty($s['von']) ? $zeit . ' Uhr' : $zeit;
    }
    if (!empty($s['ort'])) {
        $teile[] = (string) $s['ort'];
    }
    return implode(' · ', $teile);
}

/**
 * Gesamtplan als PDF (Bytes). Aufbau wie der bisherige Word-Ausdruck:
 * Tagesüberschrift, darunter die Posten in Plan-Reihenfolge mit Namensliste.
 */
function einsatzplanPdfGesamt(PDO $pdo): string
{
    $tage = einsatzplanDatenGesamt($pdo);

    $pdf = new EinsatzplanPdf('Helfereinteilung', 'Gesamtplan für die Orga', 'interner Stand — nicht an Helfer weitergeben');
    $pdf->AddPage();

    if ($tage === []) {
        $pdf->absatz('Es sind noch keine Schichten angelegt.');
        return $pdf->Output('S');
    }

    foreach ($tage as $tag => $schichten) {
        $pdf->tagUeberschrift($tag !== '' ? helferTagLabel((string) $tag) : 'Ohne festen Termin');
        foreach ($schichten as $s) {
            $pdf->schichtBlock([
                'titel'        => einsatzplanKurzTitel($s),
                'meta'         => einsatzplanMetaZeile($s),
                'beschreibung' => (string) ($s['beschreibung'] ?? ''),
                'bedarf'       => (int) $s['bedarf'],
                'marke'        => einsatzplanMarke($s),
                'karte'        => einsatzplanKartenLink($s),
                'koord'        => !empty($s['lat']) ? $s['lat'] . ', ' . $s['lon'] : '',
            ], $s['namen']);
        }
    }

    return $pdf->Output('S');
}

/**
 * Persönlicher Plan eines Helfers als PDF (Bytes).
 * $helfer: Zeile aus `helfer` (vorname, nachname).
 */
function einsatzplanPdfHelfer(PDO $pdo, array $helfer, string $orgaEmail = '', string $orgaPhone = ''): string
{
    $name = trim(($helfer['vorname'] ?? '') . ' ' . ($helfer['nachname'] ?? ''));
    $einsaetze = einsatzplanDatenHelfer($pdo, (int) $helfer['id']);

    $pdf = new EinsatzplanPdf('Dein Einsatzplan', $name, 'Änderungen jederzeit auf deiner Helferseite');
    $pdf->AddPage();

    if ($einsaetze === []) {
        $pdf->absatz(
            'Für dich ist noch kein Einsatz eingeteilt. Sobald die Planung steht, findest du sie '
            . 'auf deiner persönlichen Helferseite — dieses PDF kannst du dann neu herunterladen.'
        );
    } else {
        $pdf->absatz(
            'Danke, dass du beim Marktlauf mit dabei bist! Das hier ist deine Einteilung. '
            . 'Sie kann sich kurzfristig noch ändern — der Stand unten auf der Seite sagt dir, '
            . 'wann dieses PDF erzeugt wurde.'
        );

        $letzterTag = null;
        foreach ($einsaetze as $s) {
            $tag = (string) ($s['tag'] ?? '');
            if ($tag !== $letzterTag) {
                $pdf->tagUeberschrift($tag !== '' ? helferTagLabel($tag) : 'Ohne festen Termin');
                $letzterTag = $tag;
            }
            $pdf->schichtBlock([
                'titel'        => einsatzplanKurzTitel($s),
                'meta'         => einsatzplanMetaZeile($s),
                'beschreibung' => (string) ($s['beschreibung'] ?? ''),
                'marke'        => einsatzplanMarke($s),
                'fenster'      => einsatzplanFenster($s),
                'karte'        => einsatzplanKartenLink($s),
                'koord'        => !empty($s['lat']) ? $s['lat'] . ', ' . $s['lon'] : '',
            ], null);
        }
    }

    $kontakt = [];
    if ($orgaEmail !== '') {
        $kontakt[] = 'E-Mail: ' . $orgaEmail;
    }
    if ($orgaPhone !== '') {
        $kontakt[] = 'Telefon Orga: ' . $orgaPhone;
    }
    if ($kontakt !== []) {
        $pdf->kasten('Fragen vor Ort oder vorher?', $kontakt);
    }

    return $pdf->Output('S');
}
