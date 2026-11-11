<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * قراءة وكتابة ملفات Excel (.xlsx) و CSV بدون مكتبات خارجية.
 * ملف xlsx عبارة عن ملف مضغوط فيه XML، فنقرأ الورقة الأولى والنصوص المشتركة مباشرة.
 */
class Spreadsheet
{
    /**
     * يرجع صفوف الورقة الأولى كمصفوفات من النصوص (الصف الأول = العناوين)
     */
    public static function read(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        return match ($extension) {
            'xlsx'        => self::readXlsx($path),
            'csv', 'txt'  => self::readCsv($path),
            default       => throw new RuntimeException('صيغة الملف غير مدعومة. استخدم ملف Excel (.xlsx) أو CSV.'),
        };
    }

    public static function readCsv(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('تعذر قراءة الملف.');
        }

        // إزالة BOM، وتحويل ترميز Windows العربي إن لم يكن الملف UTF-8
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1256');
        }

        // الفاصل: فاصلة أو فاصلة منقوطة (Excel العربي يحفظ أحياناً بـ ;)
        $firstLine = strtok($content, "\n");
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = array_map(fn ($v) => trim((string) $v), $row);
        }
        fclose($handle);

        return $rows;
    }

    public static function readXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('الملف ليس ملف Excel صالحاً (.xlsx).');
        }

        // النصوص المشتركة
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $doc = simplexml_load_string($xml);
            foreach ($doc->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string) $si->t;
                } else {
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string) $r->t;
                    }
                    $shared[] = $text;
                }
            }
        }

        // الورقة الأولى حسب ترتيب المصنف
        $sheetPath = self::firstSheetPath($zip) ?? 'xl/worksheets/sheet1.xml';
        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException('لم يتم العثور على ورقة بيانات داخل الملف.');
        }

        $sheet = simplexml_load_string($sheetXml);
        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = self::columnIndex(preg_replace('/\d+/', '', $ref));
                $type = (string) $c['t'];

                if ($type === 's') {
                    $value = $shared[(int) $c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($c->is->t ?? '');
                    if ($value === '' && isset($c->is->r)) {
                        foreach ($c->is->r as $r) {
                            $value .= (string) $r->t;
                        }
                    }
                } elseif ($type === 'b') {
                    $value = ((string) $c->v) === '1' ? 'نعم' : 'لا';
                } else {
                    $value = (string) $c->v;
                }

                $cells[$col] = trim($value);
            }

            if ($cells) {
                $max = max(array_keys($cells));
                $line = [];
                for ($i = 0; $i <= $max; $i++) {
                    $line[] = $cells[$i] ?? '';
                }
                $rows[(int) $row['r'] - 1] = $line;
            }
        }

        // إعادة الترتيب مع الصفوف الفارغة
        if (!$rows) {
            return [];
        }
        $out = [];
        for ($i = 0; $i <= max(array_keys($rows)); $i++) {
            $out[] = $rows[$i] ?? [];
        }

        return $out;
    }

    /**
     * كتابة ملف xlsx بسيط: صف عناوين ثم صفوف، اتجاه من اليمين لليسار
     *
     * @param array<int, string> $headers
     * @param array<int, array<int, string|int|float|null>> $rows
     * @param array<int, int> $widths عرض كل عمود (بالحروف)
     */
    public static function writeXlsx(string $path, array $headers, array $rows, array $widths = [], string $sheetName = 'الأدوية', array $textColumns = []): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('تعذر إنشاء ملف Excel.');
        }

        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $cell = function ($col, $rowNum, $value, $style = 0) use ($esc) {
            $ref = self::columnLetter($col) . $rowNum;
            if ($value === null || $value === '') {
                return $style ? "<c r=\"{$ref}\" s=\"{$style}\"/>" : '';
            }
            if (is_int($value) || is_float($value)) {
                return "<c r=\"{$ref}\" s=\"{$style}\"><v>{$value}</v></c>";
            }

            return "<c r=\"{$ref}\" s=\"{$style}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">{$esc($value)}</t></is></c>";
        };

        $cols = '';
        foreach ($headers as $i => $h) {
            $w = $widths[$i] ?? max(12, mb_strlen($h) + 4);
            // أعمدة بتنسيق «نص» (مثل الباركود) حتى لا يحولها Excel لصيغة علمية
            $style = in_array($i, $textColumns, true) ? ' style="2"' : '';
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '"' . $style . ' customWidth="1"/>';
        }

        $sheetRows = '<row r="1" ht="30" customHeight="1">';
        foreach ($headers as $i => $h) {
            $sheetRows .= $cell($i, 1, $h, 1);
        }
        $sheetRows .= '</row>';
        foreach (array_values($rows) as $r => $row) {
            $sheetRows .= '<row r="' . ($r + 2) . '">';
            foreach (array_values($row) as $i => $v) {
                $sheetRows .= $cell($i, $r + 2, in_array($i, $textColumns, true) && $v !== '' && $v !== null ? (string) $v : $v, in_array($i, $textColumns, true) ? 2 : 0);
            }
            $sheetRows .= '</row>';
        }

        $lastCol = self::columnLetter(count($headers) - 1);
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView rightToLeft="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . $cols . '</cols><sheetData>' . $sheetRows . '</sheetData>'
            . '<autoFilter ref="A1:' . $lastCol . max(1, count($rows) + 1) . '"/>'
            . '</worksheet>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0F5C5A"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $esc($sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
    }

    /**
     * تاريخ من خلية Excel: رقم تسلسلي (45678) أو نص (2027-03-31 / 31/03/2027 / 03-2027)
     */
    public static function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            return date('Y-m-d', (int) round(((float) $value - 25569) * 86400));
        }

        $value = strtr($value, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $value = str_replace(['.', '\\'], '/', $value);

        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/', $value, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $value, $m)) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : null;
        }
        // شهر/سنة فقط (كما يُكتب على علب الأدوية): آخر يوم في الشهر
        if (preg_match('/^(\d{1,2})[\/\-](\d{4})$/', $value, $m) && (int) $m[1] >= 1 && (int) $m[1] <= 12) {
            return date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $m[2], $m[1])));
        }

        return null;
    }

    public static function columnLetter(int $index): string
    {
        $letters = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    public static function columnIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    private static function firstSheetPath(ZipArchive $zip): ?string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $rels === false) {
            return null;
        }

        $wb = simplexml_load_string($workbook);
        $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $first = $wb->xpath('//m:sheets/m:sheet')[0] ?? null;
        if (!$first) {
            return null;
        }
        $rid = (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];

        $rel = simplexml_load_string($rels);
        foreach ($rel->Relationship as $r) {
            if ((string) $r['Id'] === $rid) {
                $target = ltrim((string) $r['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }

        return null;
    }
}
