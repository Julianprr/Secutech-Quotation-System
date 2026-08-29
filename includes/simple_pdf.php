<?php

/*
 * A minimal, dependency-free PDF generator.
 *
 * Works entirely in millimeters (matching the printable quote's CSS
 * layout), using the standard Helvetica/Helvetica-Bold fonts that
 * every PDF reader already has built in - no font files or external
 * libraries needed.
 *
 * Supports: filled/stroked rectangles, lines, left/right-aligned
 * text, and simple word-wrapping. That's enough to lay out a
 * professional-looking quote or invoice.
 */
class SimplePdf
{
    private const MM_TO_PT = 2.83464567;

    private float $widthMm;
    private float $heightMm;
    private float $pageWidthPt;
    private float $pageHeightPt;
    private string $content = '';

    private const HELVETICA_WIDTHS = [
        ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667, "'" => 191,
        '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278,
        '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556,
        '8' => 556, '9' => 556, ':' => 278, ';' => 278, '<' => 584, '=' => 584, '>' => 584, '?' => 556, '@' => 1015,
        'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778, 'H' => 722,
        'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778, 'P' => 667,
        'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944, 'X' => 667,
        'Y' => 667, 'Z' => 611, '[' => 278, '\\' => 278, ']' => 278, '^' => 469, '_' => 556, '`' => 333,
        'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556, 'h' => 556,
        'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556, 'o' => 556, 'p' => 556,
        'q' => 556, 'r' => 333, 's' => 500, 't' => 278, 'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500,
        'y' => 500, 'z' => 500, '{' => 334, '|' => 260, '}' => 334, '~' => 584,
    ];

    public function __construct(float $widthMm = 210, float $heightMm = 297)
    {
        $this->widthMm = $widthMm;
        $this->heightMm = $heightMm;
        $this->pageWidthPt = $widthMm * self::MM_TO_PT;
        $this->pageHeightPt = $heightMm * self::MM_TO_PT;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    public function setColor(string $hex): void
    {
        [$r, $g, $b] = $this->hexToRgb($hex);
        $this->content .= sprintf("%.3F %.3F %.3F rg\n", $r, $g, $b);
    }

    public function setStrokeColor(string $hex): void
    {
        [$r, $g, $b] = $this->hexToRgb($hex);
        $this->content .= sprintf("%.3F %.3F %.3F RG\n", $r, $g, $b);
    }

    public function textWidthMm(string $text, float $fontSize, bool $bold = false): float
    {
        $total = 0;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $total += self::HELVETICA_WIDTHS[$text[$i]] ?? 556;
        }
        $widthPt = ($total / 1000) * $fontSize;
        if ($bold) {
            $widthPt *= 1.06;
        }
        return $widthPt / self::MM_TO_PT;
    }

    public function text(float $xMm, float $yMm, string $text, float $fontSize = 10, bool $bold = false): void
    {
        $font = $bold ? '/F2' : '/F1';
        $xPt = $xMm * self::MM_TO_PT;
        $yPt = $this->pageHeightPt - ($yMm * self::MM_TO_PT);

        $this->content .= "BT\n";
        $this->content .= sprintf("%s %g Tf\n", $font, $fontSize);
        $this->content .= sprintf("%.2F %.2F Td\n", $xPt, $yPt);
        $this->content .= '(' . $this->escape($text) . ") Tj\n";
        $this->content .= "ET\n";
    }

    public function textRight(float $xRightMm, float $yMm, string $text, float $fontSize = 10, bool $bold = false): void
    {
        $w = $this->textWidthMm($text, $fontSize, $bold);
        $this->text($xRightMm - $w, $yMm, $text, $fontSize, $bold);
    }

    public function textCentered(float $centerXMm, float $yMm, string $text, float $fontSize = 10, bool $bold = false): void
    {
        $w = $this->textWidthMm($text, $fontSize, $bold);
        $this->text($centerXMm - ($w / 2), $yMm, $text, $fontSize, $bold);
    }

    public function rect(float $xMm, float $yMm, float $wMm, float $hMm, string $style = 'F'): void
    {
        $xPt = $xMm * self::MM_TO_PT;
        $yPt = $this->pageHeightPt - ($yMm * self::MM_TO_PT) - ($hMm * self::MM_TO_PT);
        $wPt = $wMm * self::MM_TO_PT;
        $hPt = $hMm * self::MM_TO_PT;

        $this->content .= sprintf("%.2F %.2F %.2F %.2F re\n", $xPt, $yPt, $wPt, $hPt);

        $op = ['F' => 'f', 'S' => 'S', 'FD' => 'B'][$style] ?? 'f';
        $this->content .= "{$op}\n";
    }

    public function line(float $x1Mm, float $y1Mm, float $x2Mm, float $y2Mm, float $widthPt = 0.5): void
    {
        $x1 = $x1Mm * self::MM_TO_PT;
        $y1 = $this->pageHeightPt - ($y1Mm * self::MM_TO_PT);
        $x2 = $x2Mm * self::MM_TO_PT;
        $y2 = $this->pageHeightPt - ($y2Mm * self::MM_TO_PT);

        $this->content .= sprintf("%.2F w\n", $widthPt);
        $this->content .= sprintf("%.2F %.2F m\n", $x1, $y1);
        $this->content .= sprintf("%.2F %.2F l\n", $x2, $y2);
        $this->content .= "S\n";
    }

    public function wrapText(string $text, float $maxWidthMm, float $fontSize, bool $bold = false): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $test = $current === '' ? $word : $current . ' ' . $word;

            if ($this->textWidthMm($test, $fontSize, $bold) > $maxWidthMm && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $test;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    public function getContentHeightUsedMm(): float
    {
        return $this->heightMm;
    }

    public function output(): string
    {
        $objects = [];

        $objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[2] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $objects[3] = sprintf(
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj\n",
            $this->pageWidthPt,
            $this->pageHeightPt
        );
        $objects[4] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";
        $objects[5] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

        $streamLength = strlen($this->content);
        $objects[6] = "6 0 obj\n<< /Length {$streamLength} >>\nstream\n{$this->content}endstream\nendobj\n";

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $num => $objStr) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $objStr;
        }

        $xrefStart = strlen($pdf);
        $count = count($objects) + 1;

        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefStart}\n%%EOF";

        return $pdf;
    }
}
