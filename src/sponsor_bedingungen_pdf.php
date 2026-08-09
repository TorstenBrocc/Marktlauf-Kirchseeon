<?php

/**
 * Sponsoring-Bedingungen als PDF-Anhang — EIN Dokument für Geld- und Sachsponsoring.
 *
 * Grundlage/Design: intern/rechtstexte-update-spec.md (③). Layout markenkonform aus
 * dem Rechnungs-PDF abgeleitet (Fonts Montserrat/Poppins, Grün, Kopf mit Wappen,
 * 3-Spalten-Fuß, Stammdaten aus rechnungStammdaten()). Jahr dynamisch.
 * Rechtlicher Hinweis: kein Ersatz für anwaltliche Prüfung (v. a. bei größeren Beträgen).
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/fpdf/fpdf.php';
require_once __DIR__ . '/rechnung.php';

class SponsorBedingungenPdf extends FPDF
{
    private array $s;
    private int $jahr;

    private array $green1 = [0, 150, 64];    // #009640 Absenderzeile
    private array $green2 = [0, 114, 48];    // #007230 h1
    private array $ink    = [31, 42, 34];    // #1f2a22 Überschriften
    private array $body   = [51, 65, 85];    // #334155 Fließtext
    private array $label  = [100, 116, 139]; // #64748b Labels/Footer-Überschriften
    private array $muted  = [148, 163, 184]; // #94a3b8 Subzeile/Footertext
    private array $lnHead = [230, 232, 224]; // #e6e8e0 Trennlinien

    private float $L = 18.0;
    private float $R = 192.0;

    public function __construct(int $jahr)
    {
        parent::__construct('P', 'mm', 'A4');
        $this->s    = rechnungStammdaten();
        $this->jahr = $jahr;
        $this->SetAutoPageBreak(true, 30);
        $this->SetMargins(18, 14, 18);
        $this->AddFont('pop', '', 'Poppins-Light.php');
        $this->AddFont('popmed', '', 'Poppins-Medium.php');
        $this->AddFont('popsb', '', 'Poppins-SemiBold.php');
        $this->AddFont('mont', '', 'Montserrat-SemiBold.php');
        $this->AddFont('montbd', '', 'Montserrat-Bold.php');
        $this->SetTitle($this->t('Sponsoring-Bedingungen Marktlauf Kirchseeon ' . $jahr));
        $this->SetCreator($this->t('ATSV Kirchseeon Marktlauf'));
    }

    private function t(string $s): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $s) ?: $s;
    }

    private function hline(float $x1, float $x2, float $y, array $color, float $width = 0.3): void
    {
        $this->SetDrawColor(...$color);
        $this->SetLineWidth($width);
        $this->Line($x1, $y, $x2, $y);
    }

    private function trackedCell(float $w, float $h, string $txt, float $spacing, int $ln, string $align): void
    {
        $this->_out(sprintf('BT %.3F Tc ET', $spacing));
        $this->Cell($w, $h, $this->t($txt), 0, $ln, $align);
        $this->_out('BT 0 Tc ET');
    }

    public function Header(): void {}

    /** 3-Spalten-Fußzeile am unteren Satzspiegelrand — auf jeder Seite. */
    public function Footer(): void
    {
        $L = $this->L; $R = $this->R;
        $lineY = 273.4;
        $this->hline($L, $R, $lineY, $this->lnHead, 0.3);
        $colTop = $lineY + 4;
        $cols = [
            [$L, 'VEREIN', [
                $this->s['verein'], $this->s['strasse'], $this->s['plz'] . ' ' . $this->s['ort'],
            ]],
            [77.0, 'KONTAKT', [
                'Telefon ' . str_replace('/', ' ', $this->s['telefon']),
                'info@atsv-kirchseeon-marktlauf.de',
                'atsv-kirchseeon-marktlauf.de',
            ]],
            [137.0, 'VERANSTALTER', [
                $this->s['verein'] . ' – ' . $this->s['abteilung'],
            ]],
        ];
        foreach ($cols as [$cx, $head, $lines]) {
            $this->SetXY($cx, $colTop);
            $this->SetFont('mont', '', 7);
            $this->SetTextColor(...$this->label);
            $this->trackedCell(54, 4.2, $head, 0.7, 2, 'L');
            $this->SetFont('pop', '', 7.5);
            $this->SetTextColor(...$this->muted);
            foreach ($lines as $ln) {
                $this->MultiCell(54, 3.8, $this->t($ln), 0, 'L');
                $this->SetX($cx);
            }
        }
    }

    /** Abschnitt: Nummer+Titel, dann Fließtext. */
    private function section(string $titel, string $text): void
    {
        $this->Ln(3.5);
        $this->SetX($this->L);
        $this->SetFont('popsb', '', 11);
        $this->SetTextColor(...$this->ink);
        $this->MultiCell($this->R - $this->L, 6, $this->t($titel), 0, 'L');
        $this->SetX($this->L);
        $this->SetFont('pop', '', 10.5);
        $this->SetTextColor(...$this->body);
        $this->MultiCell($this->R - $this->L, 5.4, $this->t($text), 0, 'L');
    }

    /** Eingerückter, beschrifteter Unterpunkt (für §3 Geld/Sach). */
    private function subPoint(string $label, string $text): void
    {
        $this->Ln(1.5);
        $this->SetX($this->L + 4);
        $this->SetFont('popsb', '', 10);
        $this->SetTextColor(...$this->green2);
        $this->MultiCell($this->R - $this->L - 4, 5, $this->t($label), 0, 'L');
        $this->SetX($this->L + 4);
        $this->SetFont('pop', '', 10.5);
        $this->SetTextColor(...$this->body);
        $this->MultiCell($this->R - $this->L - 4, 5.4, $this->t($text), 0, 'L');
    }

    public function render(): void
    {
        $this->AddPage();
        $L = $this->L; $R = $this->R;
        $jahr = $this->jahr;

        // ===== Kopf (wie Rechnung) =====
        $this->SetXY($L, 14);
        $this->SetFont('montbd', '', 8);
        $this->SetTextColor(...$this->green1);
        $this->trackedCell(0, 4, 'ATSV KIRCHSEEON 1906 E.V.', 0.8, 1, 'L');
        $this->SetXY($L, 18.5);
        $this->SetFont('mont', '', 17);
        $this->SetTextColor(...$this->ink);
        $this->Cell(0, 9, $this->t('Abteilung Marktlauf'), 0, 1, 'L');
        $shield = __DIR__ . '/../assets/images/ATSV_Logo-750x968.png';
        if (is_file($shield)) {
            $this->Image($shield, $R - 18 * 750 / 968, 12, 0, 18);
        }
        $this->hline($L, $R, 34, $this->lnHead, 0.3);

        // ===== Titel =====
        $this->SetXY($L, 41);
        $this->SetFont('mont', '', 23);
        $this->SetTextColor(...$this->green2);
        $this->Cell(0, 10, $this->t('Sponsoring-Bedingungen'), 0, 1, 'L');
        $this->SetX($L);
        $this->SetFont('pop', '', 10.5);
        $this->SetTextColor(...$this->muted);
        $this->Cell(0, 6, $this->t('Marktlauf Kirchseeon ' . $jahr . '  ·  gültig für Geld- und Sachsponsoring'), 0, 1, 'L');

        // ===== Einleitung =====
        $this->SetXY($L, 60);
        $this->SetFont('pop', '', 10.5);
        $this->SetTextColor(...$this->body);
        $this->MultiCell($R - $L, 5.4, $this->t(
            'Diese Bedingungen regeln die Zusammenarbeit zwischen dem ' . $this->s['verein'] . ' (Veranstalter) '
            . 'und dem Sponsor des Marktlauf Kirchseeon ' . $jahr . '. Sie gelten für Geld- und Sachsponsoring; '
            . 'die jeweils einschlägigen Absätze sind gekennzeichnet.'
        ), 0, 'L');

        // ===== Abschnitte =====
        $this->section('§1  Gegenstand & Gegenleistung',
            'Der Sponsor unterstützt den Marktlauf Kirchseeon ' . $jahr . ' mit dem in der Sponsoring-Bestätigung '
            . 'bzw. Rechnung genannten Geldbetrag und/oder mit der dort genannten Sachleistung. Im Gegenzug erhält '
            . 'er die zugesagte Sichtbarkeit gemäß gebuchtem Paket bzw. Vereinbarung für die Dauer der Vorbereitung '
            . 'und Durchführung der Veranstaltung.');

        $this->section('§2  Leistungszeitraum',
            'Die vereinbarte Sichtbarkeit wird überwiegend bereits im Vorfeld erbracht (u. a. Nennung/Logo auf der '
            . 'Website und in Bewerbungs- und Vorbereitungsmaterialien).');

        $this->section('§3  Höhere Gewalt, Absage, Verschiebung',
            'Muss die Veranstaltung aufgrund höherer Gewalt (insbesondere Unwetter, behördliche Anordnung, '
            . 'Naturkatastrophen, Pandemien) oder aus vom Veranstalter nicht zu vertretenden Gründen ganz oder '
            . 'teilweise abgesagt, verschoben oder abgebrochen werden, gilt:');
        $this->subPoint('Bei Geldsponsoring',
            'Es besteht kein Anspruch auf Rückerstattung des Sponsoringbeitrags, da die vereinbarte Gegenleistung '
            . 'überwiegend im Vorfeld erbracht wird. Der Veranstalter kann nach eigener Wahl anbieten, die '
            . 'Sichtbarkeit bei einer Ersatz- oder Folgeveranstaltung fortzuführen.');
        $this->subPoint('Bei Sachsponsoring',
            'Es erfolgt keine Erstattung. Überlassene, noch nicht verbrauchte Sachleistungen werden nach Absprache '
            . 'zurückgegeben; bereits erbrachte Sichtbarkeit bleibt hiervon unberührt.');

        $this->section('§4  Logo- und Namensnutzung',
            'Der Sponsor gestattet dem Veranstalter, das überlassene Logo bzw. seinen Namen im Rahmen der zugesagten '
            . 'Sichtbarkeit zu verwenden, und sichert zu, zur Einräumung dieses Rechts berechtigt zu sein.');

        $this->section('§5  Zahlung (Geldsponsoring)',
            'Der Geldbeitrag ist gemäß der gestellten Rechnung fällig.');

        $this->section('§6  Schlussbestimmungen',
            'Es gilt deutsches Recht. Sollten einzelne Bestimmungen unwirksam sein, bleibt der Vertrag im Übrigen '
            . 'wirksam. Änderungen und Ergänzungen bedürfen der Textform.');
    }
}

/** PDF-Bytes der Sponsoring-Bedingungen (ein Dokument, Geld + Sach). */
function sponsorBedingungenPdfBytes(int $jahr): string
{
    $pdf = new SponsorBedingungenPdf($jahr);
    $pdf->render();
    return (string) $pdf->Output('S');
}

/**
 * Sponsoring-Bedingungen als pfadbasiertes Anhang-Array (Bytes -> Temp-Datei,
 * via Shutdown-Hook aufgeräumt — passt zum pfadbasierten SMTP-Mailer).
 * @return array<int,array{path:string,name:string,mime:string}>
 */
function sponsorBedingungenAnhang(int $jahr): array
{
    $bytes = sponsorBedingungenPdfBytes($jahr);
    $tmp   = tempnam(sys_get_temp_dir(), 'spbed_');
    if ($tmp === false) {
        logError('sponsorBedingungenAnhang: Temp-Datei fehlgeschlagen');
        return [];
    }
    file_put_contents($tmp, $bytes);
    register_shutdown_function(static function () use ($tmp) {
        @unlink($tmp);
    });
    return [[
        'path' => $tmp,
        'name' => 'Sponsoring-Bedingungen.pdf',
        'mime' => 'application/pdf',
    ]];
}
