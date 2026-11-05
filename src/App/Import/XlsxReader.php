<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Legge un file .xlsx (zip di XML) senza librerie esterne: per ogni scheda
 * le righe come testo. Le celle data/ora diventano 'AAAA-MM-GG HH:MM:SS'.
 * Serve per l'importazione da file (piano B al foglio Google collegato).
 *
 * readCells() da' anche il colore di sfondo e il link di ogni cella: nei
 * fogli di lavoro il colore e' spesso uno stato ("verde = programmabile").
 */
final class XlsxReader
{
    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** @return array<string, list<list<string>>> nome scheda => righe */
    public static function read(string $path): array
    {
        $zip = self::open($path);
        $shared = self::sharedStrings($zip);
        $dateStyles = self::dateStyles($zip);

        $tabs = [];
        foreach (self::sheetFiles($zip) as $name => $file) {
            $data = $zip->getFromName($file);
            if ($data === false) {
                continue;
            }
            $tabs[$name] = self::rows(simplexml_load_string($data), $shared, $dateStyles);
        }
        $zip->close();

        return $tabs;
    }

    /**
     * Le celle non vuote con valore, colore di sfondo ('RRGGBB', anche dai
     * colori del tema; null se senza) e link. Righe e colonne da 0, come read().
     *
     * @param list<string>|null $only solo queste schede (null: tutte)
     * @param bool $withEmpty anche le celle vuote ma colorate (lo stato a volte e' solo il colore)
     * @return array<string, array<int, array<int, array{value:string, fill:?string, link:?string}>>>
     */
    public static function readCells(string $path, ?array $only = null, bool $withEmpty = false): array
    {
        $zip = self::open($path);
        $shared = self::sharedStrings($zip);
        $dateStyles = self::dateStyles($zip);
        $fills = self::styleFills($zip);

        $tabs = [];
        foreach (self::sheetFiles($zip) as $name => $file) {
            // Una scheda "2025" diventa una chiave intera: si confronta come testo.
            if ($only !== null && !in_array((string) $name, $only, true)) {
                continue;
            }
            $data = $zip->getFromName($file);
            if ($data === false) {
                continue;
            }
            $sheet = simplexml_load_string($data);
            $links = self::sheetLinks($zip, $file, $sheet);
            $cells = [];
            foreach ($sheet->sheetData->row as $row) {
                foreach ($row->c as $cell) {
                    $ref = (string) $cell['r'];
                    $value = self::cellValue($cell, $shared, $dateStyles);
                    if ($value === '' && (!$withEmpty || !isset($fills[(int) $cell['s']]))) {
                        continue;
                    }
                    preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
                    $cells[(int) $m[2] - 1][self::columnIndex($m[1])] = [
                        'value' => $value,
                        'fill' => $fills[(int) $cell['s']] ?? null,
                        'link' => $links[$ref] ?? null,
                    ];
                }
            }
            $tabs[$name] = $cells;
        }
        $zip->close();

        return $tabs;
    }

    private static function open(string $path): \ZipArchive
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new ImportException('Il file non e\' un Excel (.xlsx) leggibile.');
        }

        return $zip;
    }

    /** @return list<string> */
    private static function sharedStrings(\ZipArchive $zip): array
    {
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $doc = simplexml_load_string($xml);
            foreach ($doc->si as $si) {
                $text = '';
                if (isset($si->t)) {
                    $text = (string) $si->t;
                } else {
                    foreach ($si->r as $run) {
                        $text .= (string) $run->t;
                    }
                }
                $shared[] = $text;
            }
        }

        return $shared;
    }

    /** @return array<string, string> nome scheda => file nello zip */
    private static function sheetFiles(\ZipArchive $zip): array
    {
        $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        $targets = [];
        foreach ($rels->Relationship as $rel) {
            $targets[(string) $rel['Id']] = (string) $rel['Target'];
        }

        $files = [];
        foreach ($workbook->sheets->sheet as $sheet) {
            $rid = (string) $sheet->attributes(self::REL_NS)['id'];
            $target = ltrim($targets[$rid] ?? '', '/');
            $files[trim((string) $sheet['name'])] = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
        }

        return $files;
    }

    /** @return list<list<string>> */
    private static function rows(\SimpleXMLElement $sheet, array $shared, array $dateStyles): array
    {
        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                preg_match('/^([A-Z]+)/', (string) $cell['r'], $m);
                $cells[self::columnIndex($m[1] ?? 'A')] = self::cellValue($cell, $shared, $dateStyles);
            }
            if ($cells === []) {
                continue;
            }
            $line = array_fill(0, max(array_keys($cells)) + 1, '');
            foreach ($cells as $i => $v) {
                $line[$i] = $v;
            }
            $rows[] = $line;
        }

        return $rows;
    }

    private static function cellValue(\SimpleXMLElement $cell, array $shared, array $dateStyles): string
    {
        $type = (string) $cell['t'];
        $value = isset($cell->v) ? (string) $cell->v : (isset($cell->is->t) ? (string) $cell->is->t : '');
        if ($type === 's') {
            $value = $shared[(int) $value] ?? '';
        } elseif ($type === '' && $value !== '' && is_numeric($value) && isset($dateStyles[(int) $cell['s']])) {
            $value = self::excelDate((float) $value);
        } elseif ($type === '' && is_numeric($value) && str_ends_with($value, '.0')) {
            $value = substr($value, 0, -2);
        }

        return trim($value);
    }

    /** @return array<string, string> riferimento cella (B3) => indirizzo del link */
    private static function sheetLinks(\ZipArchive $zip, string $file, \SimpleXMLElement $sheet): array
    {
        if (!isset($sheet->hyperlinks)) {
            return [];
        }
        $relsXml = $zip->getFromName(dirname($file) . '/_rels/' . basename($file) . '.rels');
        $targets = [];
        if ($relsXml !== false) {
            foreach (simplexml_load_string($relsXml)->Relationship as $rel) {
                $targets[(string) $rel['Id']] = (string) $rel['Target'];
            }
        }
        $links = [];
        foreach ($sheet->hyperlinks->hyperlink as $link) {
            $target = $targets[(string) $link->attributes(self::REL_NS)['id']] ?? null;
            if ($target === null) {
                continue;
            }
            // Un link puo' coprire un intervallo (B3:B5): vale per ogni cella.
            [$from, $to] = array_pad(explode(':', (string) $link['ref']), 2, null);
            preg_match('/^([A-Z]+)(\d+)$/', $from, $a);
            preg_match('/^([A-Z]+)(\d+)$/', $to ?? $from, $b);
            for ($r = (int) $a[2]; $r <= (int) $b[2]; $r++) {
                for ($c = self::columnIndex($a[1]); $c <= self::columnIndex($b[1]); $c++) {
                    $links[self::columnLetters($c) . $r] = $target;
                }
            }
        }

        return $links;
    }

    /** @return array<int, string> indice di stile => colore di sfondo 'RRGGBB' */
    private static function styleFills(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/styles.xml');
        if ($xml === false) {
            return [];
        }
        $doc = simplexml_load_string($xml);
        $theme = self::themeColors($zip);
        $colors = [];
        $i = 0;
        foreach ($doc->fills->fill ?? [] as $fill) {
            $pattern = $fill->patternFill;
            $rgb = isset($pattern->fgColor) ? (string) $pattern->fgColor['rgb'] : '';
            if ($rgb === '' && isset($pattern->fgColor['theme'])) {
                $rgb = $theme[(int) $pattern->fgColor['theme']] ?? '';
            }
            if ($pattern !== null && (string) $pattern['patternType'] !== 'none' && strlen($rgb) >= 6) {
                $colors[$i] = strtoupper(substr($rgb, -6));
            }
            $i++;
        }
        $styles = [];
        $i = 0;
        foreach ($doc->cellXfs->xf ?? [] as $xf) {
            if (isset($colors[(int) $xf['fillId']])) {
                $styles[$i] = $colors[(int) $xf['fillId']];
            }
            $i++;
        }

        return $styles;
    }

    /**
     * I colori del tema per indice (theme="7" nello stile). Nel file l'ordine
     * e' dk1, lt1, dk2, lt2, accent1..6; gli indici di Excel scambiano i
     * primi due di ogni coppia (0 = lt1, 1 = dk1, 2 = lt2, 3 = dk2).
     *
     * @return array<int, string> indice => 'RRGGBB'
     */
    private static function themeColors(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/theme/theme1.xml');
        if ($xml === false) {
            return [];
        }
        $doc = simplexml_load_string($xml);
        $doc->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $colors = [];
        foreach ($doc->xpath('//a:clrScheme/*') ?: [] as $color) {
            $color->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
            $value = $color->xpath('a:srgbClr/@val | a:sysClr/@lastClr');
            $colors[] = $value ? (string) $value[0] : '';
        }
        [$colors[0], $colors[1], $colors[2], $colors[3]] = [$colors[1] ?? '', $colors[0] ?? '', $colors[3] ?? '', $colors[2] ?? ''];

        return array_map('strtoupper', $colors);
    }

    /** @return array<int, true> indici di stile che sono date */
    private static function dateStyles(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/styles.xml');
        if ($xml === false) {
            return [];
        }
        $doc = simplexml_load_string($xml);
        $custom = [];
        if (isset($doc->numFmts)) {
            foreach ($doc->numFmts->numFmt as $fmt) {
                $code = strtolower((string) $fmt['formatCode']);
                $custom[(int) $fmt['numFmtId']] = (bool) preg_match('/(^|[^"\\\\])[dmyh]/', preg_replace('/"[^"]*"/', '', $code));
            }
        }
        $styles = [];
        $i = 0;
        foreach ($doc->cellXfs->xf ?? [] as $xf) {
            $id = (int) $xf['numFmtId'];
            if (($id >= 14 && $id <= 22) || ($id >= 45 && $id <= 47) || ($custom[$id] ?? false)) {
                $styles[$i] = true;
            }
            $i++;
        }

        return $styles;
    }

    private static function excelDate(float $serial): string
    {
        $seconds = (int) round(($serial - 25569) * 86400);

        return gmdate($serial - floor($serial) > 0 ? 'Y-m-d H:i:s' : 'Y-m-d', $seconds);
    }

    private static function columnIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split($letters) as $char) {
            $n = $n * 26 + (ord($char) - 64);
        }

        return $n - 1;
    }

    private static function columnLetters(int $index): string
    {
        $letters = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letters = chr(65 + ($n - 1) % 26) . $letters;
        }

        return $letters;
    }
}
