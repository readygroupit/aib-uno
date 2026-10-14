<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Legge un file .xlsx (zip di XML) senza librerie esterne: per ogni scheda
 * le righe come testo. Le celle data/ora diventano 'AAAA-MM-GG HH:MM:SS'.
 * Serve per l'importazione da file (piano B al foglio Google collegato).
 */
final class XlsxReader
{
    /** @return array<string, list<list<string>>> nome scheda => righe */
    public static function read(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new ImportException('Il file non e\' un Excel (.xlsx) leggibile.');
        }

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

        $dateStyles = self::dateStyles($zip);
        $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        $targets = [];
        foreach ($rels->Relationship as $rel) {
            $targets[(string) $rel['Id']] = (string) $rel['Target'];
        }

        $tabs = [];
        foreach ($workbook->sheets->sheet as $sheet) {
            $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $target = ltrim($targets[$rid] ?? '', '/');
            $file = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            $data = $zip->getFromName($file);
            if ($data === false) {
                continue;
            }
            $tabs[trim((string) $sheet['name'])] = self::rows(simplexml_load_string($data), $shared, $dateStyles);
        }
        $zip->close();

        return $tabs;
    }

    /** @return list<list<string>> */
    private static function rows(\SimpleXMLElement $sheet, array $shared, array $dateStyles): array
    {
        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                preg_match('/^([A-Z]+)/', (string) $cell['r'], $m);
                $index = self::columnIndex($m[1] ?? 'A');
                $type = (string) $cell['t'];
                $value = isset($cell->v) ? (string) $cell->v : (isset($cell->is->t) ? (string) $cell->is->t : '');
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ($type === '' && $value !== '' && is_numeric($value) && isset($dateStyles[(int) $cell['s']])) {
                    $value = self::excelDate((float) $value);
                } elseif ($type === '' && is_numeric($value) && str_ends_with($value, '.0')) {
                    $value = substr($value, 0, -2);
                }
                $cells[$index] = trim($value);
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
}
