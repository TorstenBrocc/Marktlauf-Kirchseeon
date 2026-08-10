<?php
/**
 * Rechnungs-PDF-Renderer (Sponsoring) — Layout exakt nach dem Design-Briefing
 * (A4, Satzspiegel 174×265, Ränder oben 14 / seitlich 18 / unten 18).
 *
 * Schriften: Montserrat (Versal/Überschriften), Poppins (Fließtext) — eingebettet
 * in lib/fpdf/font. Grün erscheint nur an vier Textstellen (Absenderzeile,
 * Rechnungsnummer, h1, Betrag). Texte UTF-8 -> Windows-1252 (Umlaute, €).
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/fpdf/fpdf.php';
require_once __DIR__ . '/rechnung.php';

class RechnungPdf extends FPDF
{
    private array $s;
    private array $r;
    private string $nummer;

    // Farben (Briefing §3/§4)
    private array $green1 = [0, 150, 64];    // #009640 Absenderzeile
    private array $green2 = [0, 114, 48];    // #007230 h1, Rechnungsnummer, Betrag
    private array $ink    = [31, 42, 34];    // #1f2a22
    private array $body   = [51, 65, 85];    // #334155 Fließtext
    private array $label  = [100, 116, 139]; // #64748b Labels, Footer-Überschriften
    private array $muted  = [148, 163, 184]; // #94a3b8 Rücksendezeile, Tabellenkopf, Zeitraum, Footertext
    private array $desc   = [71, 85, 105];   // #475569 Positionsbeschreibung, Summen
    private array $lnHead = [230, 232, 224]; // #e6e8e0 Kopftrenner/Footerlinie/Box
    private array $lnPos  = [236, 238, 232]; // #eceee8 Positions-/Summentrenner
    private array $lnMeta = [241, 243, 238]; // #f1f3ee Metadaten-Trenner

    private float $L = 18.0;
    private float $R = 192.0;

    public function __construct(array $snapshot, string $nummer)
    {
        parent::__construct('P', 'mm', 'A4');
        $this->s      = rechnungStammdaten();
        $this->r      = $snapshot;
        $this->nummer = $nummer;
        $this->SetAutoPageBreak(false);
        $this->SetMargins(18, 14, 18);
        $this->AddFont('pop', '', 'Poppins-Light.php');      // 300
        $this->AddFont('popmed', '', 'Poppins-Medium.php');  // 500
        $this->AddFont('popsb', '', 'Poppins-SemiBold.php'); // 600
        $this->AddFont('mont', '', 'Montserrat-SemiBold.php');// 600
        $this->AddFont('montbd', '', 'Montserrat-Bold.php'); // 700
        $this->SetTitle($this->t('Rechnung ' . ($nummer !== '' ? $nummer : 'Entwurf')));
        $this->SetCreator($this->t('ATSV Kirchseeon Marktlauf'));
    }

    private function t(string $s): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $s) ?: $s;
    }

    private function eur(float $v): string
    {
        return number_format($v, 2, ',', '.') . ' ' . "\xE2\x82\xAC";
    }

    /** Versal-Laufweite (character spacing) in Punkt setzen; mit 0 zurücksetzen. */
    private function tc(float $pt): void
    {
        $this->_out(sprintf('BT %.3F Tc ET', $pt));
    }

    private function trackedCell(float $w, float $h, string $txt, float $spacing, int $ln, string $align): void
    {
        $this->tc($spacing);
        $this->Cell($w, $h, $this->t($txt), 0, $ln, $align);
        $this->tc(0);
    }

    private function hline(float $x1, float $x2, float $y, array $color, float $width = 0.2): void
    {
        $this->SetDrawColor(...$color);
        $this->SetLineWidth($width);
        $this->Line($x1, $y, $x2, $y);
    }

    private function RoundedRect(float $x, float $y, float $w, float $h, float $r, string $style = 'D'): void
    {
        $k = $this->k; $hp = $this->h;
        $op = ($style === 'F') ? 'f' : (($style === 'FD' || $style === 'DF') ? 'B' : 'S');
        $m = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->_arc($xc + $r * $m, $yc - $r, $xc + $r, $yc - $r * $m, $xc + $r, $yc);
        $xc = $x + $w - $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_arc($xc + $r, $yc + $r * $m, $xc + $r * $m, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_arc($xc - $r * $m, $yc + $r, $xc - $r, $yc + $r * $m, $xc - $r, $yc);
        $xc = $x + $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->_arc($xc - $r, $yc - $r * $m, $xc - $r * $m, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    private function _arc(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void
    {
        $h = $this->h; $k = $this->k;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            $x1 * $k, ($h - $y1) * $k, $x2 * $k, ($h - $y2) * $k, $x3 * $k, ($h - $y3) * $k));
    }

    public function Header(): void {}
    public function Footer(): void {}

    public function render(): void
    {
        $this->AddPage();
        $L = $this->L; $R = $this->R;

        // ===== 1 · Kopf =====
        $this->SetXY($L, 14);
        $this->SetFont('montbd', '', 8);
        $this->SetTextColor(...$this->green1);
        // "e.V." bewusst klein-e inmitten der Versalzeile, Gründungsjahr angehängt (TT, 2026-08-10).
        $this->trackedCell(0, 4, 'ALLGEMEINER TURN- UND SPORTVEREIN KIRCHSEEON e.V. - 1906', 0.8, 1, 'L');

        $this->SetXY($L, 18.5);
        $this->SetFont('mont', '', 17);
        $this->SetTextColor(...$this->ink);
        $this->Cell(0, 9, $this->t("Abteilung 'Marktlauf Kirchseeon'"), 0, 1, 'L');

        $shield = __DIR__ . '/../assets/images/ATSV_Logo-750x968.png';
        if (is_file($shield)) {
            $this->Image($shield, $R - 18 * 750 / 968, 12, 0, 18);
        }

        // ===== 2 · Kopftrenner =====
        $this->hline($L, $R, 34, $this->lnHead, 0.3);

        // ===== 3 · Zweispalter: Anschrift links, Metadaten rechts =====
        // Rücksendezeile
        $this->SetXY($L, 39);
        $this->SetFont('pop', '', 7);
        $this->SetTextColor(...$this->muted);
        $this->Cell(0, 3.5, $this->t($this->s['verein'] . ' · '
            . $this->s['strasse'] . ' · ' . $this->s['plz'] . ' ' . $this->s['ort']), 0, 1, 'L');
        // Empfänger
        $this->SetXY($L, 45);
        $this->SetFont('popsb', '', 11);
        $this->SetTextColor(...$this->ink);
        $this->Cell(0, 6, $this->t((string) $this->r['empfaenger_firma']), 0, 1, 'L');
        $this->SetFont('pop', '', 11);
        if (!empty($this->r['empfaenger_strasse'])) {
            $this->SetX($L);
            $this->Cell(0, 5.6, $this->t((string) $this->r['empfaenger_strasse']), 0, 1, 'L');
        }
        $plzOrt = trim(($this->r['empfaenger_plz'] ?? '') . ' ' . ($this->r['empfaenger_ort'] ?? ''));
        if ($plzOrt !== '') {
            $this->SetX($L);
            $this->Cell(0, 5.6, $this->t($plzOrt), 0, 1, 'L');
        }

        // Metadaten rechts
        $datum = !empty($this->r['erstellt_am'])
            ? date('d.m.Y', strtotime((string) $this->r['erstellt_am']))
            : date('d.m.Y');
        $nummerAnzeige = $this->nummer !== '' ? $this->nummer : '(wird vergeben)';
        $mx = 120.0; $lblW = 40.0; $valW = $R - $mx - $lblW;
        // rowH 6.5 statt 9: Zeilenabstand im Metadatenblock halbiert (der Textkörper einer Zeile
        // ist ~3,2 mm hoch, der Weißraum dazwischen damit ~3,3 statt ~5,8 mm).
        $my = 39.0; $rowH = 6.5;
        $meta = [
            ['Rechnungsdatum', $datum, false],
            ['Rechnungs-Nr.',  $nummerAnzeige, $this->nummer !== ''],
            ['Steuernummer',   $this->s['steuernummer'], false],
            ['USt-IdNr.',      $this->s['ust_id'], false],
        ];
        foreach ($meta as $i => [$lab, $val, $green]) {
            $y = $my + $i * $rowH;
            if ($i > 0) {
                $this->hline($mx, $R, $y, $this->lnMeta, 0.2); // Trenner zwischen Zeilen
            }
            $this->SetXY($mx, $y + 0.75); // Text in der schmaleren Zeile weiter zentriert
            $this->SetFont('pop', '', 9);
            $this->SetTextColor(...$this->label);
            $this->Cell($lblW, 5, $this->t($lab), 0, 0, 'L');
            if ($green) {
                $this->SetFont('popsb', '', 9);
                $this->SetTextColor(...$this->green2);
            } else {
                $this->SetFont('popmed', '', 9);
                $this->SetTextColor(...$this->ink);
            }
            $this->Cell($valW, 5, $this->t($val), 0, 0, 'R');
        }

        // ===== 4 · h1 =====
        $this->SetXY($L, 82);
        $this->SetFont('mont', '', 23);
        $this->SetTextColor(...$this->green2);
        $this->Cell(0, 10, $this->t('Rechnung'), 0, 1, 'L');

        // ===== 5 · Anschreiben =====
        $this->SetXY($L, 95);
        $this->SetFont('pop', '', 10.5);
        $this->SetTextColor(...$this->body);
        $this->Cell(0, 6, $this->t('Sehr geehrte Damen und Herren,'), 0, 1, 'L');
        // Leerzeile zwischen Anrede und Satz; der Satz endet damit eine Zeilenhöhe
        // über dem Tabellenkopf (ty = 118) — bewusst enger als zuvor.
        $this->SetXY($L, 107);
        $this->MultiCell($R - $L, 6, $this->t(
            'für Ihr Sponsoring berechnen wir gemäß unserer Vereinbarung die folgende Leistung:'
        ), 0, 'L');

        // ===== 6 · Positionstabelle =====
        $ty = 118.0;
        $this->SetXY($L, $ty);
        $this->SetFont('mont', '', 7.5);
        $this->SetTextColor(...$this->muted);
        $this->trackedCell(120, 5, 'LEISTUNG', 0.9, 0, 'L');
        $this->trackedCell($R - $L - 120, 5, 'NETTO', 0.9, 1, 'R');
        $this->hline($L, $R, $ty + 6, $this->ink, 0.4); // kräftige Tabellenkopf-Unterkante

        // Leistung in Titel + Beschreibung zerlegen
        $voll = (string) ($this->r['leistung'] ?? '');
        $pos  = strpos($voll, ':');
        if ($pos !== false) {
            $titel  = trim(substr($voll, 0, $pos));
            $beschr = trim(substr($voll, $pos + 1));
        } else {
            $titel  = $voll;
            $beschr = '';
        }
        // Kein Komma→"·"-Ersatz mehr: die Posten kommen bereits mit " · " getrennt, und die
        // Kommas innerhalb der Logo-Aufzählung ("Logo auf Website, auf Startnummer") gehören dazu.
        $beschr = rtrim($beschr, '.');

        $this->SetXY($L, $ty + 9);
        $this->SetFont('popsb', '', 10.5);
        $this->SetTextColor(...$this->ink);
        $this->Cell(120, 6, $this->t($titel), 0, 0, 'L');
        $this->SetFont('popmed', '', 10.5);
        $this->Cell($R - $L - 120, 6, $this->t($this->eur((float) $this->r['netto'])), 0, 1, 'R');

        if ($beschr !== '') {
            $this->SetX($L);
            $this->SetFont('pop', '', 9.5);
            $this->SetTextColor(...$this->desc);
            $this->MultiCell(120, 5, $this->t($beschr), 0, 'L');
        }
        // Keine eigene "Leistungszeitraum"-Zeile: der Zeitraum steht bereits im
        // Positionstitel ("<Paket>-Sponsoring Marktlauf <Jahr>") und erfüllt dort die
        // Pflichtangabe nach § 14 Abs. 4 Nr. 6 UStG. Die zweite Nennung war Dopplung.
        $posEnd = $this->GetY() + 2;
        $this->hline($L, $R, $posEnd, $this->lnPos, 0.2);

        // ===== 7 · Summenblock (rechtsbündig) =====
        $sx = 118.0; $slw = 42.0; $svw = $R - $sx - $slw;
        $sy = $posEnd + 6;
        // Nettobetrag
        $this->SetXY($sx, $sy);
        $this->SetFont('pop', '', 10);
        $this->SetTextColor(...$this->desc);
        $this->Cell($slw, 6, $this->t('Nettobetrag'), 0, 0, 'L');
        $this->Cell($svw, 6, $this->t($this->eur((float) $this->r['netto'])), 0, 1, 'R');
        // Zeilenabstände halbiert: 8 / 8,5 mm statt 10 / 10,5 zwischen den Zeilenanfängen,
        // Trenner bleiben mittig im jeweiligen Zwischenraum.
        $this->hline($sx, $R, $sy + 7, $this->lnPos, 0.2);
        // USt
        $ustLabel = 'zzgl. ' . rtrim(rtrim(number_format((float) $this->r['ust_satz'], 2, ',', '.'), '0'), ',') . ' % USt';
        $this->SetXY($sx, $sy + 8);
        $this->Cell($slw, 6, $this->t($ustLabel), 0, 0, 'L');
        $this->Cell($svw, 6, $this->t($this->eur((float) $this->r['ust_betrag'])), 0, 1, 'R');
        $this->hline($sx, $R, $sy + 15, $this->lnPos, 0.2);
        // Rechnungsbetrag
        $this->SetXY($sx, $sy + 16);
        $this->SetFont('montbd', '', 8);
        $this->SetTextColor(...$this->ink);
        $this->trackedCell($slw, 10, 'RECHNUNGSBETRAG', 0.8, 0, 'L');
        $this->SetFont('mont', '', 15);
        $this->SetTextColor(...$this->green2);
        $this->Cell($svw, 10, $this->t($this->eur((float) $this->r['brutto'])), 0, 1, 'R');

        // ===== 8 · Zahlungskasten =====
        // sy + 29,5: der Betragsblock endet jetzt bei sy + 26 (engere Summenzeilen),
        // der Abstand von 3,5 mm zum Kasten bleibt derselbe wie zuvor.
        $by = $sy + 29.5; $bh = 40.0;
        $this->SetDrawColor(...$this->lnHead);
        $this->SetLineWidth(0.3);
        $this->RoundedRect($L, $by, $R - $L, $bh, 3, 'D');

        $this->SetXY($L + 5, $by + 5);
        $this->SetFont('popsb', '', 10);
        $this->SetTextColor(...$this->ink);
        $this->Cell(0, 5, $this->t('Bitte überweisen Sie den Rechnungsbetrag innerhalb von 14 Tagen auf folgendes Konto:'), 0, 1, 'L');

        $rows = [
            ['Kontoinhaber', $this->s['kontoinhaber']],
            ['IBAN',         $this->s['iban']],
            ['Bank',         $this->s['bank']],
            ['Verwendung',   'Rechnung ' . ($this->nummer !== '' ? $this->nummer : '<Nr.>')],
        ];
        foreach ($rows as $j => [$lab, $val]) {
            $yy = $by + 12.5 + $j * 6.2;
            $this->SetXY($L + 5, $yy);
            $this->SetFont('pop', '', 9.5);
            $this->SetTextColor(...$this->label);
            $this->Cell(30, 5, $this->t($lab), 0, 0, 'L');
            $this->SetFont('popsb', '', 9.5);
            $this->SetTextColor(...$this->ink);
            $this->Cell(0, 5, $this->t($val), 0, 0, 'L');
        }

        // ===== 9 · Dank + 10 · Grußformel =====
        $gy = $by + $bh + 5;
        $this->SetXY($L, $gy);
        $this->SetFont('pop', '', 10.5);
        $this->SetTextColor(...$this->body);
        $this->Cell(0, 6, $this->t('Vielen Dank für Ihre Unterstützung des Marktlaufs.'), 0, 1, 'L');
        $this->SetXY($L, $gy + 8);
        $this->Cell(0, 6, $this->t('Mit sportlichen Grüßen'), 0, 1, 'L');
        $this->SetXY($L, $gy + 14);
        $this->SetFont('popsb', '', 10.5);
        $this->SetTextColor(...$this->ink);
        $this->Cell(0, 6, $this->t($this->s['verein']), 0, 1, 'L');

        // ===== 12 · Fußzeile (am unteren Satzspiegelrand) =====
        $this->drawFooter();
    }

    private function drawFooter(): void
    {
        $L = $this->L; $R = $this->R;
        $bottom = 283.0;            // 297 - 14: Fußzeile bewusst tiefer als der Satzspiegel
        $lineY  = $bottom - 23.6;   // Linie oben im Footerblock
        $this->hline($L, $R, $lineY, $this->lnHead, 0.3);

        $colTop = $lineY + 4;
        // Spaltenbreiten einzeln, nicht pauschal 54 mm: „Kreissparkasse München Starnberg
        // Ebersberg" misst in Poppins-Light 7,5 pt 60,0 mm und brach sonst um. MultiCell zieht
        // links und rechts je 1 mm Zellenrand ab — die Bankspalte braucht deshalb 64 mm Breite
        // (62 mm nutzbar) und beginnt bei 128, damit sie am Satzspiegelrand 192 endet.
        // Kontakt beginnt bei 72 mit 56 mm (54 nutzbar), damit die längste Zeile dort —
        // https://atsv-kirchseeon-marktlauf.de mit 48,4 mm — einzeilig bleibt.
        $cols = [
            [$L,     54.0, 'VEREIN', [
                $this->s['verein'], $this->s['strasse'], $this->s['plz'] . ' ' . $this->s['ort'], $this->s['burozeiten'],
            ]],
            [72.0,   56.0, 'KONTAKT', [
                'Telefon ' . str_replace('/', ' ', $this->s['telefon']),
                'Telefax ' . str_replace('/', ' ', $this->s['telefax']),
                $this->s['email'], $this->s['web'],
            ]],
            [128.0,  64.0, 'BANKVERBINDUNG', [
                $this->s['bank1_name'], $this->s['bank1_iban'], $this->s['bank2_name'], $this->s['bank2_iban'],
            ]],
        ];
        foreach ($cols as [$cx, $cw, $head, $lines]) {
            $this->SetXY($cx, $colTop);
            $this->SetFont('mont', '', 7);
            $this->SetTextColor(...$this->label);
            $this->trackedCell($cw, 4.2, $head, 0.7, 2, 'L');
            $this->SetFont('pop', '', 7.5);
            $this->SetTextColor(...$this->muted);
            foreach ($lines as $ln) {
                $this->MultiCell($cw, 3.8, $this->t($ln), 0, 'L');
                $this->SetX($cx);
            }
        }
    }
}

/**
 * Erzeugt das Rechnungs-PDF aus einem Snapshot-Array.
 * $nummer='' => Entwurf. Rückgabe: PDF als String; optional zusätzlich in $zielDatei.
 */
function rechnungPdfErzeugen(array $snapshot, string $nummer = '', string $zielDatei = ''): string
{
    $pdf = new RechnungPdf($snapshot, $nummer);
    $pdf->render();
    $bytes = $pdf->Output('S');
    if ($zielDatei !== '') {
        file_put_contents($zielDatei, $bytes);
    }
    return $bytes;
}
