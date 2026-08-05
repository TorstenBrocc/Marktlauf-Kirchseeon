<?php
/**
 * Rechnungs-PDF-Renderer (Sponsoring) im Marktlauf-CI.
 *
 * Nutzt FPDF (lib/fpdf, core font Helvetica). Der Kernfont ist Latin-1 —
 * alle Texte werden daher von UTF-8 nach Windows-1252 konvertiert (Umlaute, €).
 * Layout erfüllt die Pflichtangaben nach §14 UStG.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/fpdf/fpdf.php';
require_once __DIR__ . '/rechnung.php';

class RechnungPdf extends FPDF
{
    /** @var array Vereins-Stammdaten */
    private array $s;
    /** @var array Rechnungs-Snapshot */
    private array $r;
    /** @var string Fortlaufende Nummer oder '' (Entwurf) */
    private string $nummer;

    // Marken-Farben (RGB)
    private array $gruen = [0, 150, 64];    // #009640
    private array $gold  = [244, 184, 30];  // #f4b81e
    private array $ink   = [31, 42, 34];    // #1f2a22
    private array $grau  = [110, 120, 114];

    public function __construct(array $snapshot, string $nummer)
    {
        parent::__construct('P', 'mm', 'A4');
        $this->s      = rechnungStammdaten();
        $this->r      = $snapshot;
        $this->nummer = $nummer;
        $this->SetAutoPageBreak(true, 20);
        $this->SetMargins(20, 18, 20);
        $this->SetTitle($this->t('Rechnung ' . ($nummer !== '' ? $nummer : 'Entwurf')));
        $this->SetCreator($this->t('ATSV Kirchseeon Marktlauf'));
    }

    /** UTF-8 -> Windows-1252 für die FPDF-Kernfonts. */
    private function t(string $s): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $s) ?: $s;
    }

    private function eur(float $v): string
    {
        return number_format($v, 2, ',', '.') . ' ' . "\xE2\x82\xAC"; // UTF-8 €, wird in t() konvertiert
    }

    // --- Kopfzeile: Logo + grüner Akzentbalken ---
    public function Header(): void
    {
        $logo = __DIR__ . '/../assets/images/Marktlauf-Logo-Schrift-1180x579 freigestellt.png';
        if (is_file($logo)) {
            // 1180x579 -> Breite 52mm, Höhe proportional
            $this->Image($logo, 20, 12, 52);
        }
        // Grüner Akzentbalken rechts oben
        $this->SetFillColor(...$this->gruen);
        $this->Rect(150, 14, 40, 3, 'F');
        $this->SetFillColor(...$this->gold);
        $this->Rect(150, 17, 40, 1.2, 'F');
        $this->Ln(24);
    }

    public function Footer(): void
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(...$this->grau);
        $foot = $this->s['verein'] . ' · ' . $this->s['abteilung'] . ' · '
              . $this->s['strasse'] . ' · ' . $this->s['plz'] . ' ' . $this->s['ort']
              . ' · USt-IdNr ' . $this->s['ust_id'];
        $this->Cell(0, 5, $this->t($foot), 0, 0, 'C');
    }

    public function render(): void
    {
        $this->AddPage();

        // --- Absender-Kleinzeile über dem Empfänger (DIN 5008) ---
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(...$this->grau);
        $absender = $this->s['verein'] . ' · ' . $this->s['abteilung'] . ' · '
                  . $this->s['strasse'] . ' · ' . $this->s['plz'] . ' ' . $this->s['ort'];
        $this->Cell(0, 4, $this->t($absender), 0, 1, 'L');
        $this->Ln(2);

        // --- Empfänger-Adressblock ---
        $this->SetTextColor(...$this->ink);
        $this->SetFont('Helvetica', '', 11);
        $empf = [];
        $empf[] = (string) ($this->r['empfaenger_firma'] ?? '');
        if (!empty($this->r['empfaenger_strasse'])) {
            $empf[] = (string) $this->r['empfaenger_strasse'];
        }
        $plzOrt = trim(($this->r['empfaenger_plz'] ?? '') . ' ' . ($this->r['empfaenger_ort'] ?? ''));
        if ($plzOrt !== '') {
            $empf[] = $plzOrt;
        }
        foreach ($empf as $zeile) {
            $this->Cell(0, 5.5, $this->t($zeile), 0, 1, 'L');
        }

        // --- Meta-Block rechts (Datum, Nummer, Steuer-IDs) ---
        $datum = !empty($this->r['erstellt_am'])
            ? date('d.m.Y', strtotime((string) $this->r['erstellt_am']))
            : date('d.m.Y');
        $nummerAnzeige = $this->nummer !== '' ? $this->nummer : '(wird vom Kassier vergeben)';

        $metaY = 52;
        $this->SetXY(120, $metaY);
        $this->SetFont('Helvetica', '', 9.5);
        $this->SetTextColor(...$this->ink);
        $meta = [
            ['Rechnungsdatum', $datum],
            ['Rechnungs-Nr.',  $nummerAnzeige],
            ['Steuernummer',   $this->s['steuernummer']],
            ['USt-IdNr.',      $this->s['ust_id']],
        ];
        foreach ($meta as [$label, $wert]) {
            $this->SetX(120);
            $this->SetFont('Helvetica', '', 9.5);
            $this->SetTextColor(...$this->grau);
            $this->Cell(28, 5.5, $this->t($label), 0, 0, 'L');
            $this->SetTextColor(...$this->ink);
            $this->SetFont('Helvetica', 'B', 9.5);
            $this->Cell(0, 5.5, $this->t($wert), 0, 1, 'L');
        }

        // --- Titel ---
        $this->SetY(84);
        $this->SetFont('Helvetica', 'B', 20);
        $this->SetTextColor(...$this->gruen);
        $this->Cell(0, 10, $this->t('Rechnung'), 0, 1, 'L');
        $this->Ln(2);

        // --- Einleitung ---
        $this->SetFont('Helvetica', '', 10.5);
        $this->SetTextColor(...$this->ink);
        $this->MultiCell(0, 5.5, $this->t(
            'Sehr geehrte Damen und Herren,'
        ), 0, 'L');
        $this->Ln(1);
        $this->MultiCell(0, 5.5, $this->t(
            'für Ihr Sponsoring berechnen wir gemäß unserer Vereinbarung die folgende Leistung:'
        ), 0, 'L');
        $this->Ln(4);

        // --- Leistungstabelle ---
        $this->positionsTabelle();

        // --- Zahlungshinweis ---
        $this->Ln(8);
        $this->SetFont('Helvetica', '', 10.5);
        $this->SetTextColor(...$this->ink);
        $this->MultiCell(0, 5.5, $this->t(
            'Bitte überweisen Sie den Rechnungsbetrag innerhalb von 14 Tagen auf folgendes Konto:'
        ), 0, 'L');
        $this->Ln(2);

        $this->zahlungsBox();

        // --- Dank ---
        $this->Ln(8);
        $this->SetFont('Helvetica', '', 10.5);
        $this->MultiCell(0, 5.5, $this->t(
            'Vielen Dank für Ihre Unterstützung des Marktlaufs.'
        ), 0, 'L');
        $this->Ln(4);
        $this->MultiCell(0, 5.5, $this->t(
            'Mit sportlichen Grüßen' . "\n" . $this->s['verein'] . ' – ' . $this->s['abteilung']
        ), 0, 'L');
    }

    private function positionsTabelle(): void
    {
        $x0 = 20;
        $wBeschr = 108;
        $wNetto  = 42;

        // Kopf
        $this->SetX($x0);
        $this->SetFillColor(...$this->gruen);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 9.5);
        $this->Cell($wBeschr, 8, $this->t('  Leistung'), 0, 0, 'L', true);
        $this->Cell($wNetto, 8, $this->t('Netto  '), 0, 1, 'R', true);

        // Zeile
        $this->SetTextColor(...$this->ink);
        $this->SetFont('Helvetica', '', 10);

        $beschr = (string) ($this->r['leistung'] ?? '');
        $zeitraum = (string) ($this->r['zeitraum'] ?? '');

        $yStart = $this->GetY();
        $this->SetX($x0);
        // Beschreibung als MultiCell, Netto rechts daneben
        $this->MultiCell($wBeschr, 5.5, $this->t('  ' . $beschr), 0, 'L');
        $yEnd = $this->GetY();
        // Netto-Wert auf Höhe des Zeilenstarts
        $this->SetXY($x0 + $wBeschr, $yStart);
        $this->SetFont('Helvetica', '', 10);
        $this->Cell($wNetto, 5.5, $this->t($this->eur((float) $this->r['netto']) . '  '), 0, 1, 'R');

        // Leistungszeitraum als Unterzeile
        $this->SetXY($x0, max($yEnd, $yStart + 5.5));
        $this->SetFont('Helvetica', '', 8.5);
        $this->SetTextColor(...$this->grau);
        $this->Cell($wBeschr, 5, $this->t('  Leistungszeitraum: ' . $zeitraum), 0, 1, 'L');

        // Trennlinie
        $this->Ln(1);
        $this->SetDrawColor(210, 214, 211);
        $this->Line($x0, $this->GetY(), $x0 + $wBeschr + $wNetto, $this->GetY());
        $this->Ln(2);

        // Summen
        $this->summeZeile('Nettobetrag', $this->eur((float) $this->r['netto']), false);
        $ustLabel = 'zzgl. ' . rtrim(rtrim(number_format((float) $this->r['ust_satz'], 2, ',', '.'), '0'), ',') . ' % USt';
        $this->summeZeile($ustLabel, $this->eur((float) $this->r['ust_betrag']), false);
        $this->summeZeile('Rechnungsbetrag', $this->eur((float) $this->r['brutto']), true);
    }

    private function summeZeile(string $label, string $wert, bool $betont): void
    {
        $x0 = 20;
        $wBeschr = 108;
        $wNetto  = 42;
        $this->SetX($x0 + $wBeschr - 30);
        if ($betont) {
            $this->SetFillColor(244, 246, 244);
            $this->SetFont('Helvetica', 'B', 11);
            $this->SetTextColor(...$this->gruen);
            $this->Cell(30 + 0, 8, $this->t($label), 0, 0, 'R', true);
            $this->Cell($wNetto, 8, $this->t($wert . '  '), 0, 1, 'R', true);
        } else {
            $this->SetFont('Helvetica', '', 10);
            $this->SetTextColor(...$this->ink);
            $this->Cell(30 + 0, 6.5, $this->t($label), 0, 0, 'R');
            $this->Cell($wNetto, 6.5, $this->t($wert . '  '), 0, 1, 'R');
        }
    }

    private function zahlungsBox(): void
    {
        $x0 = 20;
        $w = 150;
        $yStart = $this->GetY();
        $this->SetFillColor(247, 249, 247);
        $this->SetDrawColor(...$this->gruen);
        // Rahmen mit Inhalt
        $lines = [
            ['Kontoinhaber', $this->s['kontoinhaber']],
            ['IBAN',         $this->s['iban']],
            ['Bank',         $this->s['bank']],
            ['Verwendung',   'Rechnung ' . ($this->nummer !== '' ? $this->nummer : '<Nr.>')],
        ];
        $h = 6.5 * count($lines) + 4;
        $this->Rect($x0, $yStart, $w, $h, 'DF');
        $this->SetXY($x0 + 3, $yStart + 2);
        foreach ($lines as [$label, $wert]) {
            $this->SetX($x0 + 3);
            $this->SetFont('Helvetica', '', 9);
            $this->SetTextColor(...$this->grau);
            $this->Cell(28, 6.5, $this->t($label), 0, 0, 'L');
            $this->SetFont('Helvetica', 'B', 9.5);
            $this->SetTextColor(...$this->ink);
            $this->Cell(0, 6.5, $this->t($wert), 0, 1, 'L');
        }
        $this->SetY($yStart + $h);
    }
}

/**
 * Erzeugt das Rechnungs-PDF aus einem Snapshot-Array.
 * $nummer='' => Entwurf (Nummer als Platzhalter). Rückgabe: PDF als String.
 * Wenn $zielDatei gesetzt ist, wird das PDF zusätzlich dorthin geschrieben.
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
