<?php
/**
 * XlsxReader — Pure PHP XLSX/CSV parser.
 * Primary:  ZipArchive (ext-zip)
 * Fallback: PharData   (built-in Phar extension — no php.ini change needed)
 *
 * Usage:
 *   $reader = new XlsxReader('/path/to/file.xlsx');
 *   $rows   = $reader->getRows();   // array of string[]
 */
class XlsxReader
{
    private ?ZipArchive $zip      = null;
    private bool        $useZip   = true;
    private string      $pharZip  = '';    // temp .zip path used in PharData mode

    private array $sharedStrings = [];
    private array $dateXfIds     = [];   // xf style indexes that format as dates

    /** Excel built-in numFmt IDs that represent date/time values */
    private const DATE_BUILTIN = [14,15,16,17,18,19,20,21,22,45,46,47];

    // ── Constructor ─────────────────────────────────────────────────

    public function __construct(string $path)
    {
        if (class_exists('ZipArchive')) {
            // ── Primary path: ZipArchive ─────────────────────────
            $this->zip    = new ZipArchive();
            $code         = $this->zip->open($path);
            if ($code !== true) {
                throw new RuntimeException("Cannot open XLSX (ZipArchive error code {$code}).");
            }
            $this->useZip = true;

        } elseif (class_exists('PharData')) {
            // ── Fallback: PharData (Phar extension, always available) ──
            // PharData identifies archive format by file extension,
            // so we must copy the .xlsx file to a .zip temp name.
            $tmp = tempnam(sys_get_temp_dir(), 'xlsx') . '.zip';
            if (!copy($path, $tmp)) {
                throw new RuntimeException(
                    'ZipArchive not loaded and cannot create temp file for PharData fallback.'
                );
            }
            $this->pharZip = $tmp;
            $this->useZip  = false;

        } else {
            throw new RuntimeException(
                'ZipArchive extension is not loaded. '
                . 'To fix: open XAMPP → Apache Config → php.ini, '
                . 'find ";extension=zip", remove the semicolon, save, restart Apache.'
            );
        }

        $this->parseSharedStrings();
        $this->parseDateStyles();
    }

    public function __destruct()
    {
        if ($this->useZip && $this->zip !== null) {
            @$this->zip->close();
        }
        if (!$this->useZip && $this->pharZip !== '' && file_exists($this->pharZip)) {
            @unlink($this->pharZip);
        }
    }

    // ── Public API ───────────────────────────────────────────────────

    /**
     * Return all rows of the first worksheet as a 2-D array of strings.
     * Each inner array has 0-based numeric keys.
     * Empty trailing cells in a row are NOT added; sparse cells become ''.
     */
    public function getRows(): array
    {
        $sheetPath = $this->resolveSheetPath(1);
        $xml = $this->archiveRead($sheetPath);
        if ($xml === false) {
            throw new RuntimeException("Cannot read worksheet XML at: {$sheetPath}");
        }
        return $this->parseWorksheet($xml);
    }

    // ── Archive abstraction ──────────────────────────────────────────

    /** Read a file from the archive; returns content string or false. */
    private function archiveRead(string $innerPath): string|false
    {
        if ($this->useZip) {
            return $this->zip->getFromName($innerPath);
        }
        $result = @file_get_contents('phar://' . $this->pharZip . '/' . $innerPath);
        return ($result !== false) ? $result : false;
    }

    /** Check whether a path exists inside the archive. */
    private function archiveHas(string $innerPath): bool
    {
        if ($this->useZip) {
            return $this->zip->locateName($innerPath) !== false;
        }
        return file_exists('phar://' . $this->pharZip . '/' . $innerPath);
    }

    // ── Shared Strings ───────────────────────────────────────────────

    private function parseSharedStrings(): void
    {
        $xml = $this->archiveRead('xl/sharedStrings.xml');
        if ($xml === false) return;

        $dom = $this->xml($xml);

        foreach ($dom->getElementsByTagName('si') as $si) {
            // Concatenate ALL <t> children — handles rich-text <r><t>...</t></r>
            $text = '';
            foreach ($si->getElementsByTagName('t') as $t) {
                $text .= $t->nodeValue;
            }
            $this->sharedStrings[] = $text;
        }
    }

    // ── Date Style Detection ─────────────────────────────────────────

    private function parseDateStyles(): void
    {
        $xml = $this->archiveRead('xl/styles.xml');
        if ($xml === false) return;

        $dom = $this->xml($xml);

        // 1. Collect custom numFmt IDs that contain date tokens (d/m/y)
        $customDateIds = [];
        foreach ($dom->getElementsByTagName('numFmt') as $el) {
            $id   = (int) $el->getAttribute('numFmtId');
            $code = $el->getAttribute('formatCode');
            // Remove quoted literal strings, then look for date tokens
            $bare = preg_replace('/"[^"]*"/', '', $code);
            if (preg_match('/[yYmMdD]/', $bare)) {
                $customDateIds[] = $id;
            }
        }

        // 2. Walk <cellXfs> (NOT <cellStyleXfs>) — cell s="" attribute indexes into this
        $cellXfsNode = null;
        foreach ($dom->getElementsByTagName('cellXfs') as $node) {
            $cellXfsNode = $node;
            break;
        }
        if (!$cellXfsNode) return;

        $idx = 0;
        foreach ($cellXfsNode->childNodes as $child) {
            if (!($child instanceof DOMElement)) continue;
            $numFmtId = (int) $child->getAttribute('numFmtId');
            if (in_array($numFmtId, self::DATE_BUILTIN, true) ||
                in_array($numFmtId, $customDateIds, true)) {
                $this->dateXfIds[] = $idx;
            }
            $idx++;
        }
    }

    // ── Worksheet Parser ─────────────────────────────────────────────

    private function parseWorksheet(string $xml): array
    {
        $dom  = $this->xml($xml);
        $rows = [];

        foreach ($dom->getElementsByTagName('row') as $rowEl) {
            $sparse = [];
            $maxCol = -1;

            foreach ($rowEl->getElementsByTagName('c') as $cellEl) {
                $col = $this->refToColIndex($cellEl->getAttribute('r'));
                if ($col > $maxCol) $maxCol = $col;
                $sparse[$col] = $this->readCell($cellEl);
            }

            if ($maxCol < 0) {
                $rows[] = [];
                continue;
            }

            $row = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $row[] = $sparse[$c] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    // ── Cell Value Reader ────────────────────────────────────────────

    private function readCell(DOMElement $cell): string
    {
        $type    = $cell->getAttribute('t');      // s, str, inlineStr, b, '' / n
        $styleId = (int) $cell->getAttribute('s');

        // Get raw numeric value
        $vNode = $cell->getElementsByTagName('v')->item(0);
        $v     = $vNode ? $vNode->nodeValue : null;

        switch ($type) {
            case 's':
                // Shared-string index
                return isset($v) ? ($this->sharedStrings[(int) $v] ?? '') : '';

            case 'inlineStr':
                // Inline string — value is in <is><t>
                $t = $cell->getElementsByTagName('t')->item(0);
                return $t ? $t->nodeValue : '';

            case 'str':
                // Calculated / formula string result
                return $v ?? '';

            case 'b':
                return ($v === '1') ? 'TRUE' : 'FALSE';

            default:
                // Numeric (type='' or 'n') or empty
                if ($v === null || $v === '') return '';

                if (is_numeric($v) && in_array($styleId, $this->dateXfIds, true)) {
                    return $this->serialToYmd((float) $v);
                }
                return $v;
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Convert cell reference column letters to 0-based index.
     * "A1" → 0,  "B5" → 1,  "Z3" → 25,  "AA1" → 26.
     */
    private function refToColIndex(string $ref): int
    {
        preg_match('/^([A-Za-z]+)/', $ref, $m);
        if (empty($m)) return 0;

        $letters = strtoupper($m[1]);
        $idx     = 0;
        foreach (str_split($letters) as $ch) {
            $idx = $idx * 26 + (ord($ch) - 64);
        }
        return $idx - 1;
    }

    /**
     * Convert Excel serial date number to Y-m-d string.
     * Handles the Lotus 1-2-3 leap-year 1900 bug (Excel serial 60 = fake Feb 29 1900).
     */
    private function serialToYmd(float $serial): string
    {
        if ($serial < 1) return number_format($serial, 10, '.', '');
        // 25569 = Excel serial for 1970-01-01, already incorporates the fake Feb 29 1900 (Lotus bug).
        // Do NOT subtract 1 — that would double-correct and shift all dates back by one day.
        // Serial 60 (fake Feb 29) maps to 1900-02-28; anything else converts correctly as-is.
        $s = ($serial == 60) ? 59.0 : $serial;
        $unix = (int) round(($s - 25569) * 86400);
        if ($unix < -2208988800 || $unix > 32503680000) {
            return (string) $serial; // out of sane range — return raw
        }
        return gmdate('Y-m-d', $unix);
    }

    /**
     * Locate the first worksheet XML path inside the ZIP.
     * Most files use xl/worksheets/sheet1.xml; fall back to workbook.xml.rels.
     */
    private function resolveSheetPath(int $sheetNum = 1): string
    {
        $default = "xl/worksheets/sheet{$sheetNum}.xml";
        if ($this->archiveHas($default)) {
            return $default;
        }

        // Parse relationships to find the real path
        $relsXml = $this->archiveRead('xl/_rels/workbook.xml.rels');
        if ($relsXml === false) {
            throw new RuntimeException('workbook.xml.rels not found inside XLSX.');
        }

        $dom  = $this->xml($relsXml);
        $n    = 0;
        foreach ($dom->getElementsByTagName('Relationship') as $rel) {
            if (!str_contains($rel->getAttribute('Type'), 'worksheet')) continue;
            $n++;
            if ($n === $sheetNum) {
                $target = $rel->getAttribute('Target');
                // Target can be "worksheets/sheet1.xml" or "/xl/worksheets/sheet1.xml"
                if (str_starts_with($target, '/')) {
                    return ltrim($target, '/');
                }
                return 'xl/' . ltrim($target, '/');
            }
        }
        throw new RuntimeException("Sheet {$sheetNum} not found in workbook.xml.rels.");
    }

    /** Parse XML string into a DOMDocument, suppressing warnings. */
    private function xml(string $raw): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($raw, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        return $dom;
    }
}

// ── CSV reader (standalone function) ────────────────────────────────

/**
 * Read a CSV file (UTF-8 or UTF-8 BOM) into a 2-D array of strings.
 *
 * @param string $path      Absolute file path.
 * @param string $delimiter Auto-detected if not specified (comma or semicolon).
 * @return string[][]
 */
function readCsvFile(string $path, string $delimiter = ''): array
{
    $fp = fopen($path, 'r');
    if (!$fp) throw new RuntimeException("Cannot open CSV file: {$path}");

    // Strip UTF-8 BOM if present
    $bom = fread($fp, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        fseek($fp, 0);
    }

    // Auto-detect delimiter from first line
    if ($delimiter === '') {
        $firstLine = fgets($fp);
        fseek($fp, $bom === "\xEF\xBB\xBF" ? 3 : 0);
        $commas     = substr_count($firstLine, ',');
        $semicolons = substr_count($firstLine, ';');
        $delimiter  = ($semicolons > $commas) ? ';' : ',';
    }

    $rows = [];
    while (($row = fgetcsv($fp, 0, $delimiter)) !== false) {
        // Ensure valid UTF-8 — convert from Windows-874 (Thai) if needed
        $rows[] = array_map(function (string $v): string {
            $v = trim($v);
            if ($v !== '' && !mb_check_encoding($v, 'UTF-8')) {
                $v = mb_convert_encoding($v, 'UTF-8', 'Windows-874');
            }
            return $v;
        }, $row);
    }
    fclose($fp);
    return $rows;
}
