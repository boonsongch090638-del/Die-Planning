<?php
/**
 * Update-by-Die-No Import Library — Die Planning
 *
 * Updates plan_send_date, die_pc_due_date, die_finish_plan, remarks
 * using Die No (section-index_tab) as the lookup key.
 * Only non-empty cells are written; empty cells leave the DB value unchanged.
 *
 * Column format (0-based):
 *   0 = Die No          (e.g. "4012-443", "SF-X102-4")
 *   1 = วันที่จ่ายแบบ   → plan_send_date
 *   2 = วันที่ต้องส่ง DPC → die_pc_due_date
 *   3 = Die Finish Plan → die_finish_plan  (e.g. "18/2026")
 *   4 = หมายเหตุ        → remarks
 */

require_once __DIR__ . '/../includes/XlsxReader.php';

const DIENO_PREVIEW_LABELS = [
    'A: Die No',
    'B: วันที่จ่ายแบบ',
    'C: วันที่ต้องส่ง DPC',
    'D: Die Finish Plan (wk/yyyy)',
    'E: หมายเหตุ',
];

// ── Public: preview ──────────────────────────────────────────────────

function previewFileByDieNo(string $filePath, bool $skipFirst = true, int $maxRows = 5): array
{
    $allRows  = loadAllRowsDieNo($filePath);
    $dataRows = $skipFirst ? array_slice($allRows, 1) : $allRows;
    $total    = count($dataRows);

    $preview = array_map(function (array $row): array {
        $out = [];
        for ($i = 0; $i < 5; $i++) {
            $out[] = (string) ($row[$i] ?? '');
        }
        return $out;
    }, array_slice($dataRows, 0, $maxRows));

    return [
        'headers'    => DIENO_PREVIEW_LABELS,
        'rows'       => $preview,
        'total_rows' => $total,
    ];
}

// ── Public: import ───────────────────────────────────────────────────

function importFromFileByDieNo(string $filePath, PDO $pdo, bool $skipFirst = true): array
{
    $allRows = loadAllRowsDieNo($filePath);

    $updated   = 0;
    $notFound  = 0;
    $errors    = 0;
    $errorList = [];

    $findStmt = $pdo->prepare(
        'SELECT id FROM dies WHERE section = :section AND index_tab = :index_tab LIMIT 1'
    );

    $startIdx = $skipFirst ? 1 : 0;

    for ($i = $startIdx; $i < count($allRows); $i++) {
        $row    = $allRows[$i];
        $lineNo = $i + 1;

        try {
            $dieNo      = trim((string) ($row[0] ?? ''));
            $sendDate   = parseDateDieNo(trim((string) ($row[1] ?? '')));
            $dueDate    = parseDateDieNo(trim((string) ($row[2] ?? '')));
            $finishPlan = trim((string) ($row[3] ?? ''));
            $remarks    = trim((string) ($row[4] ?? ''));

            if ($dieNo === '') continue;

            [$section, $indexTab] = parseDieNoParts($dieNo);

            $findStmt->execute([':section' => $section, ':index_tab' => $indexTab]);
            $existingId = $findStmt->fetchColumn();

            if ($existingId === false) {
                $notFound++;
                if (count($errorList) < 20) {
                    $errorList[] = "Row {$lineNo}: Die No \"{$dieNo}\" ไม่พบในฐานข้อมูล";
                }
                continue;
            }

            // Only update fields that have values in the spreadsheet
            $record = [];
            if ($sendDate   !== '') {
                $record['plan_send_date']  = $sendDate;
            }
            if ($dueDate    !== '') {
                $record['die_pc_due_date'] = $dueDate;
            }
            if ($finishPlan !== '') {
                $record['die_finish_plan'] = $finishPlan;
            }
            if ($remarks    !== '') {
                $record['remarks'] = $remarks;
            }

            if (empty($record)) continue;

            $parts = array_map(fn(string $col): string => "{$col} = :{$col}", array_keys($record));
            $sql   = 'UPDATE dies SET ' . implode(', ', $parts) . ' WHERE id = :_id';
            $stmt  = $pdo->prepare($sql);

            foreach ($record as $col => $val) {
                $stmt->bindValue(':' . $col, $val, PDO::PARAM_STR);
            }
            $stmt->bindValue(':_id', (int) $existingId, PDO::PARAM_INT);
            $stmt->execute();
            $updated++;

        } catch (Throwable $e) {
            $errors++;
            if (count($errorList) < 20) {
                $errorList[] = "Row {$lineNo}: " . $e->getMessage();
            }
        }
    }

    return [
        'imported'   => 0,
        'updated'    => $updated,
        'not_found'  => $notFound,
        'errors'     => $errors,
        'error_list' => $errorList,
    ];
}

// ═══════════════════════════════════════════════════════════════════
// Actual-Date Import (2 columns: Die No, ส่งจริง)
// ═══════════════════════════════════════════════════════════════════

const ACTUAL_PREVIEW_LABELS = [
    'A: Die No',
    'B: ส่งจริง (วันที่ส่งจริง)',
];

function previewFileActualDate(string $filePath, bool $skipFirst = true, int $maxRows = 5): array
{
    $allRows  = loadAllRowsDieNo($filePath);
    $dataRows = $skipFirst ? array_slice($allRows, 1) : $allRows;
    $total    = count($dataRows);

    $preview = array_map(function (array $row): array {
        return [
            (string) ($row[0] ?? ''),
            (string) ($row[1] ?? ''),
        ];
    }, array_slice($dataRows, 0, $maxRows));

    return [
        'headers'    => ACTUAL_PREVIEW_LABELS,
        'rows'       => $preview,
        'total_rows' => $total,
    ];
}

function importFromFileActualDate(string $filePath, PDO $pdo, bool $skipFirst = true): array
{
    $allRows = loadAllRowsDieNo($filePath);

    $updated   = 0;
    $notFound  = 0;
    $skipped   = 0;
    $errors    = 0;
    $errorList = [];

    $findStmt = $pdo->prepare(
        'SELECT id FROM dies WHERE section = :section AND index_tab = :index_tab LIMIT 1'
    );
    $updateStmt = $pdo->prepare(
        'UPDATE dies SET die_pc_actual_date = :date WHERE id = :id'
    );

    $startIdx = $skipFirst ? 1 : 0;

    for ($i = $startIdx; $i < count($allRows); $i++) {
        $row    = $allRows[$i];
        $lineNo = $i + 1;

        try {
            $dieNo      = trim((string) ($row[0] ?? ''));
            $actualDate = parseDateDieNo(trim((string) ($row[1] ?? '')));

            if ($dieNo === '') continue;

            // Skip rows with no date — keep existing status unchanged
            if ($actualDate === '') {
                $skipped++;
                continue;
            }

            [$section, $indexTab] = parseDieNoParts($dieNo);

            $findStmt->execute([':section' => $section, ':index_tab' => $indexTab]);
            $existingId = $findStmt->fetchColumn();

            if ($existingId === false) {
                $notFound++;
                if (count($errorList) < 20) {
                    $errorList[] = "Row {$lineNo}: Die No \"{$dieNo}\" ไม่พบในฐานข้อมูล";
                }
                continue;
            }

            $updateStmt->execute([':date' => $actualDate, ':id' => (int) $existingId]);
            $updated++;

        } catch (Throwable $e) {
            $errors++;
            if (count($errorList) < 20) {
                $errorList[] = "Row {$lineNo}: " . $e->getMessage();
            }
        }
    }

    return [
        'updated'    => $updated,
        'not_found'  => $notFound,
        'skipped'    => $skipped,
        'errors'     => $errors,
        'error_list' => $errorList,
    ];
}

// ── Internal helpers ─────────────────────────────────────────────────

function loadAllRowsDieNo(string $filePath): array
{
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    if ($ext === 'csv') {
        $rows = [];
        if (($fh = fopen($filePath, 'r')) === false) return $rows;
        while (($row = fgetcsv($fh)) !== false) {
            $rows[] = $row;
        }
        fclose($fh);
        return $rows;
    }

    $reader = new XlsxReader($filePath);
    return $reader->getRows();
}

/**
 * Split "4012-443" → ["4012", "443"]
 * Split "SF-X102-4" → ["SF-X102", "4"]  (splits at LAST dash)
 */
function parseDieNoParts(string $dieNo): array
{
    if ($dieNo === '') return ['', ''];
    $pos = strrpos($dieNo, '-');
    if ($pos === false) return [$dieNo, ''];
    return [substr($dieNo, 0, $pos), substr($dieNo, $pos + 1)];
}

/**
 * Parse date string to Y-m-d.
 * Handles Thai/EU (d/m/Y), US (m/d/Y), ISO (Y-m-d), serial (from XlsxReader).
 */
function parseDateDieNo(string $val): string
{
    if ($val === '' || $val === '0') return '';

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) return $val;

    $formats = ['d/m/Y', 'm/d/Y', 'Y/m/d', 'd-m-Y', 'd.m.Y', 'd/m/y', 'm/d/y'];

    foreach ($formats as $fmt) {
        $dt   = DateTime::createFromFormat('!' . $fmt, $val);
        $errs = DateTime::getLastErrors();
        if ($dt !== false && ($errs === false || $errs['warning_count'] === 0)) {
            return $dt->format('Y-m-d');
        }
    }

    return $val;
}
