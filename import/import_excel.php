<?php
/**
 * Excel / CSV Import Library — Die Planning
 *
 * Provides importFromFile() — reads .xlsx or .csv and upserts into dies table.
 * Uses XlsxReader (pure PHP, no Composer) — no external dependencies.
 *
 * Column map (0-based, actual Excel file structure):
 *   0  = No                          → no
 *   1  = สาเหตุขอสร้างแม่พิมพ์       → reason
 *   2  = Customer                    → customer
 *   3  = Section (D) — Die No part 1 → section
 *   5  = Index (F)  — Die No part 2  → index_tab   Die No = D-F  e.g. 4012-443
 *   7  = Tech DWG No                 → tech_dwg_no
 *   8  = วันที่จ่ายแบบ               → plan_send_date
 *   9  = วันที่ต้องส่ง DPC           → die_pc_due_date
 *   11 = Die finish plan (Week/Year) → die_finish_plan
 *   12 = Machine                     → machine
 *   15 = สถานะ DMK                   → dmk_status
 *   19 = ประเภทพิมพ์                 → die_type
 *   20 = หมายเหตุ                    → remarks
 */

require_once __DIR__ . '/../includes/XlsxReader.php';

// ── Column map (0-based Excel columns) ──────────────────────────────
const IMPORT_COL_MAP = [
    0  => 'no',
    1  => 'reason',
    2  => 'customer',
    3  => 'section',        // D — first part of Die No
    5  => 'index_tab',      // F — second part of Die No  (Die No = D-F)
    7  => 'tech_dwg_no',    // H
    8  => 'plan_send_date', // I
    9  => 'die_pc_due_date',// J
    11 => 'die_finish_plan',// L
    12 => 'machine',        // M
    15 => 'dmk_status',     // P
    19 => 'die_type',       // T
    20 => 'remarks',        // U
];

// Labels shown in the preview table (Excel cols A–U, 0-based 0–20)
const IMPORT_PREVIEW_LABELS = [
    'A: no (Col 0)',
    'B: reason (Col 1)',
    'C: customer (Col 2)',
    'D: section/Die No pt1 (Col 3)',
    'E: (ไม่ใช้ Col 4)',
    'F: index_tab/Die No pt2 (Col 5)',
    'G: (ไม่ใช้ Col 6)',
    'H: tech_dwg_no (Col 7)',
    'I: plan_send_date (Col 8)',
    'J: die_pc_due_date (Col 9)',
    'K: (ไม่ใช้ Col 10)',
    'L: die_finish_plan (Col 11)',
    'M: machine (Col 12)',
    'N: (ไม่ใช้ Col 13)',
    'O: (ไม่ใช้ Col 14)',
    'P: dmk_status (Col 15)',
    'Q: (ไม่ใช้ Col 16)',
    'R: (ไม่ใช้ Col 17)',
    'S: (ไม่ใช้ Col 18)',
    'T: die_type (Col 19)',
    'U: remarks (Col 20)',
];

const IMPORT_DATE_FIELDS = ['plan_send_date', 'die_pc_due_date'];
const IMPORT_INT_FIELDS  = [];

// ── Public: preview ──────────────────────────────────────────────────

/**
 * Read the first N rows (after optional header skip) for preview display.
 * Returns 12 columns (Excel cols 0–11) with descriptive labels.
 *
 * @return array{ headers: string[], rows: string[][], total_rows: int }
 */
function previewFile(string $filePath, bool $skipFirst = true, int $maxRows = 5): array
{
    $allRows  = loadAllRows($filePath);
    $dataRows = $skipFirst ? array_slice($allRows, 1) : $allRows;
    $total    = count($dataRows);

    // Normalise each preview row to exactly 21 cells (cols 0–20, A–U)
    $preview = array_map(function (array $row): array {
        $out = [];
        for ($i = 0; $i < 21; $i++) {
            $out[] = (string) ($row[$i] ?? '');
        }
        return $out;
    }, array_slice($dataRows, 0, $maxRows));

    return [
        'headers'    => IMPORT_PREVIEW_LABELS,
        'rows'       => $preview,
        'total_rows' => $total,
    ];
}

// ── Public: import ───────────────────────────────────────────────────

/**
 * Import / upsert dies from an Excel or CSV file into the database.
 *
 * @return array{ imported: int, updated: int, errors: int, error_list: string[] }
 */
function importFromFile(string $filePath, PDO $pdo, bool $skipFirst = true): array
{
    $allRows = loadAllRows($filePath);

    $imported  = 0;
    $updated   = 0;
    $errors    = 0;
    $errorList = [];

    $checkStmt = $pdo->prepare(
        'SELECT id FROM dies
          WHERE tech_dwg_no = :tech_dwg_no
            AND index_tab   = :index_tab
          LIMIT 1'
    );

    $startIdx = $skipFirst ? 1 : 0;

    for ($i = $startIdx; $i < count($allRows); $i++) {
        $row    = $allRows[$i];
        $lineNo = $i + 1;

        try {
            $record = buildRecord($row);

            // Skip entirely blank rows
            $values = array_filter($record, fn($v) => $v !== '' && $v !== null && $v !== 0);
            if (empty($values)) continue;

            // Upsert: duplicate key = tech_dwg_no + index_tab
            $checkStmt->execute([
                ':tech_dwg_no' => $record['tech_dwg_no'],
                ':index_tab'   => $record['index_tab'],
            ]);
            $existingId = $checkStmt->fetchColumn();

            if ($existingId !== false) {
                doUpdate($pdo, $record, (int) $existingId);
                $updated++;
            } else {
                doInsert($pdo, $record);
                $imported++;
            }
        } catch (Throwable $e) {
            $errors++;
            if (count($errorList) < 10) {   // keep first 10 error messages
                $errorList[] = "Row {$lineNo}: " . $e->getMessage();
            }
        }
    }

    return [
        'imported'   => $imported,
        'updated'    => $updated,
        'errors'     => $errors,
        'error_list' => $errorList,
    ];
}

// ── Internal helpers ─────────────────────────────────────────────────

/** Load rows from XLSX or CSV based on file extension. */
function loadAllRows(string $filePath): array
{
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    if ($ext === 'csv') {
        return readCsvFile($filePath);
    }

    // xlsx (or xls treated as xlsx — modern Excel saves as xlsx even with .xls)
    $reader = new XlsxReader($filePath);
    return $reader->getRows();
}

/** Map a raw row array to a keyed record using IMPORT_COL_MAP + Die No parser. */
function buildRecord(array $row): array
{
    $record = [];
    foreach (IMPORT_COL_MAP as $colIdx => $field) {
        $raw = $row[$colIdx] ?? '';
        $val = trim((string) $raw);

        if (in_array($field, IMPORT_DATE_FIELDS, true)) {
            $record[$field] = parseDate($val);

        } elseif (in_array($field, IMPORT_INT_FIELDS, true)) {
            $record[$field] = (int) $val;

        } else {
            $val = normalizeConstrainedField($field, $val);

            if ($val === null) {
                $record[$field] = null;
            } else {
                if ($val !== '' && !mb_check_encoding($val, 'UTF-8')) {
                    $val = mb_convert_encoding($val, 'UTF-8', 'Windows-874');
                }
                $record[$field] = $val;
            }
        }
    }

    // index_tab comes from col 5 (F) directly via IMPORT_COL_MAP above.
    if (!isset($record['index_tab'])) {
        $record['index_tab'] = '';
    }

    // Col 19 (T) may embed hollow level: "Hollowง่าย", "Hollowกลาง", "Hollowยาก"
    if (($record['die_type'] ?? '') === 'Hollow') {
        $record['hollow_level'] = extractHollowLevel(trim((string) ($row[19] ?? '')));
    }

    return $record;
}

/**
 * Extract hollow_level from a combined die-type string.
 * Handles Thai ("ง่าย","กลาง","ยาก") and English ("easy","medium","hard").
 */
function extractHollowLevel(string $val): ?string
{
    if (mb_stripos($val, 'ง่าย')  !== false || mb_stripos($val, 'easy')   !== false) return 'easy';
    if (mb_stripos($val, 'กลาง')  !== false || mb_stripos($val, 'medium') !== false) return 'medium';
    if (mb_stripos($val, 'ยาก')   !== false || mb_stripos($val, 'hard')   !== false) return 'hard';
    return null;
}

/**
 * Parse a Die No string into [section, index_tab].
 * Rule: everything before the LAST dash = section; after = index_tab.
 *   "PS-0210-2" → ["PS-0210", "2"]
 *   "4012-443"  → ["4012",    "443"]
 *   "5025-144"  → ["5025",    "144"]
 */
function parseDieNo(string $dieNo): array
{
    if ($dieNo === '') return ['', ''];
    $pos = strrpos($dieNo, '-');
    if ($pos === false) return [$dieNo, ''];
    return [substr($dieNo, 0, $pos), substr($dieNo, $pos + 1)];
}

/**
 * Normalize fields that have DB CHECK constraints.
 * Returns the normalized value string, or null if invalid (NULL passes CHECK).
 */
function normalizeConstrainedField(string $field, string $val): ?string
{
    if ($val === '') {
        // Empty string fails CHECK constraints — use null instead
        return match ($field) {
            'die_type', 'hollow_level' => null,
            'plan_status'              => 'normal',
            default                    => '',
        };
    }

    switch ($field) {
        case 'die_type':
            // Match prefix to handle "Hollow ย่าย", "Hollow easy", etc.
            $lower = strtolower($val);
            if (strpos($lower, 'hollow')   !== false) return 'Hollow';
            if (strpos($lower, 'solid')    !== false) return 'Solid';
            if (strpos($lower, 'heatsink') !== false) return 'Heatsink';
            if (strpos($lower, 'heat')     !== false) return 'Heatsink';
            return null;   // unknown value stored as NULL

        case 'hollow_level':
            $map = [
                'easy'      => 'easy',   'ง่าย'     => 'easy',   '1' => 'easy',
                'medium'    => 'medium', 'ปานกลาง'  => 'medium', '2' => 'medium',
                'hard'      => 'hard',   'ยาก'      => 'hard',   '3' => 'hard',
            ];
            return $map[strtolower($val)] ?? null;

        case 'plan_status':
            $map = [
                'normal'   => 'normal',  'ปกติ'  => 'normal',
                'waiting'  => 'waiting', 'wait'   => 'waiting', 'รอ' => 'waiting',
                'hold'     => 'hold',    'หยุด'  => 'hold',
            ];
            return $map[strtolower($val)] ?? 'normal';   // default = 'normal'

        default:
            return $val;
    }
}

/**
 * Parse a date string to Y-m-d.
 * XlsxReader already converts serial dates; this handles string formats.
 */
function parseDate(string $val): string
{
    if ($val === '' || $val === '0') return '';

    // Already Y-m-d (from XlsxReader serial conversion or ISO source)
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) return $val;

    $formats = [
        'd/m/Y',   // 16/04/2026 — Thai / EU
        'm/d/Y',   // 04/16/2026 — US
        'Y/m/d',   // 2026/04/16
        'd-m-Y',   // 16-04-2026
        'd.m.Y',   // 16.04.2026
        'd/m/y',   // 16/04/26
        'm/d/y',   // 04/16/26
    ];

    foreach ($formats as $fmt) {
        $dt   = DateTime::createFromFormat('!' . $fmt, $val);
        $errs = DateTime::getLastErrors();
        if ($dt !== false && ($errs === false || $errs['warning_count'] === 0)) {
            return $dt->format('Y-m-d');
        }
    }

    return $val;   // store as-is if unparseable
}

function doUpdate(PDO $pdo, array $record, int $id): void
{
    $parts = array_map(fn($col) => "{$col} = :{$col}", array_keys($record));
    $sql   = 'UPDATE dies SET ' . implode(', ', $parts) . ' WHERE id = :_id';
    $stmt  = $pdo->prepare($sql);

    foreach ($record as $col => $val) {
        if ($val === null) {
            $stmt->bindValue(':' . $col, null, PDO::PARAM_NULL);
        } elseif (in_array($col, IMPORT_INT_FIELDS, true)) {
            $stmt->bindValue(':' . $col, $val, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':' . $col, $val, PDO::PARAM_STR);
        }
    }
    $stmt->bindValue(':_id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function doInsert(PDO $pdo, array $record): void
{
    $cols = implode(', ', array_keys($record));
    $phs  = ':' . implode(', :', array_keys($record));
    $stmt = $pdo->prepare("INSERT INTO dies ({$cols}) VALUES ({$phs})");

    foreach ($record as $col => $val) {
        if ($val === null) {
            $stmt->bindValue(':' . $col, null, PDO::PARAM_NULL);
        } elseif (in_array($col, IMPORT_INT_FIELDS, true)) {
            $stmt->bindValue(':' . $col, $val, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':' . $col, $val, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
}
