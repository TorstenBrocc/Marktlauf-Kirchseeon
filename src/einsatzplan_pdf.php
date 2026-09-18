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
     * Ein Block: Schicht mit Zeit/Ort/Beschreibung und der Namensliste darunter.
     * $namen = []   => "— noch offen —" (Gesamtplan: unbesetzte Schicht)
     * $namen = null => gar keine Namensliste (persönlicher Plan: der Helfer weiß,
     *                  dass er selbst gemeint ist; wer sonst dort eingeteilt ist,
     *                  geht ihn nichts an — und der Plan bleibt DSGVO-schlank).
     */
    public function schichtBlock(string $titel, string $meta, string $beschreibung, ?array $namen, int $bedarf = 0): void
    {
        $this->ensureRaum(22);

        $this->SetFont('popsb', '', 10);
        $this->SetTextColor(...$this->ink);
        $this->SetX($this->L);
        $this->Cell(0, 5.5, $this->t($titel), 0, 1, 'L');

        if ($meta !== '') {
            $this->SetFont('pop', '', 8.5);
            $this->SetTextColor(...$this->label);
            $this->SetX($this->L);
            $this->Cell(0, 4.5, $this->t($meta), 0, 1, 'L');
        }

        if ($beschreibung !== '') {
            $this->SetFont('pop', '', 8);
            $this->SetTextColor(...$this->muted);
            $this->SetX($this->L);
            $this->MultiCell($this->R - $this->L, 4, $this->t($beschreibung), 0, 'L');
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
function einsatzplanDatenGesamt(PDO $pdo): array
{
    $schichten = $pdo->query('
        SELECT id, titel, beschreibung, ort, tag, von, bis, zeitfenster, bedarf
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
    $stmt = $pdo->prepare('
        SELECT sc.id, sc.titel, sc.beschreibung, sc.ort, sc.tag, sc.von, sc.bis, sc.zeitfenster
        FROM schicht_zuteilung sz
        JOIN schichten sc ON sc.id = sz.schicht_id
        WHERE sz.helfer_id = :id
        ORDER BY ' . schichtenOrderBy($pdo, 'sc') . '
    ');
    $stmt->execute(['id' => $helferId]);
    return $stmt->fetchAll();
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
            $pdf->schichtBlock(
                (string) $s['titel'],
                einsatzplanMetaZeile($s),
                (string) ($s['beschreibung'] ?? ''),
                $s['namen'],
                (int) $s['bedarf']
            );
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
            $pdf->schichtBlock(
                (string) $s['titel'],
                einsatzplanMetaZeile($s),
                (string) ($s['beschreibung'] ?? ''),
                null
            );
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
