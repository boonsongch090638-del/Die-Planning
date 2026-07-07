<?php
/**
 * Export ZIP — DMK Plan for a given week
 *
 * GET /api/export_zip.php?week=16/2026
 *
 * ZIP structure:
 *   DMK_WEEK16_2026/
 *   ├── plan.html        ← self-contained HTML table, inline CSS, relative image paths
 *   └── images/
 *       ├── A80008-001_1.jpg
 *       └── ...
 *
 * Streams the ZIP directly; no file is written to the server permanently.
 * Requires PHP ZipArchive extension (bundled with XAMPP by default).
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/image_helper.php';

// ── Validate ────────────────────────────────────────────────────
$week = trim($_GET['week'] ?? '');

if ($week === '') {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'week parameter required (e.g. ?week=16/2026)']);
    exit;
}

if (!class_exists('ZipArchive')) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'ZipArchive extension is not available on this server.']);
    exit;
}

$parts = explode('/', $week, 2);
if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid week format. Use ww/yyyy, e.g. 16/2026']);
    exit;
}
[$weekNum, $weekYear] = [(int)$parts[0], (int)$parts[1]];

// ── Names ───────────────────────────────────────────────────────
$safeName = sprintf('DMK_WEEK%d_%d', $weekNum, $weekYear);   // DMK_WEEK16_2026
$folder   = $safeName;                                         // ZIP root folder
$zipName  = $safeName . '.zip';                               // download filename

// ── Fetch dies ──────────────────────────────────────────────────
try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT *
        FROM   dies
        WHERE  die_finish_plan = :week
        ORDER  BY machine ASC, CAST(no AS TEXT) ASC, id ASC
    ");
    $stmt->execute([':week' => $week]);
    $dies = $stmt->fetchAll();
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

if (empty($dies)) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['error' => "No dies found for week {$week}"]);
    exit;
}

// ── Enrich dies with computed fields + image paths ──────────────
foreach ($dies as &$die) {
    $die['die_no']        = trim($die['section'] ?? '') . '-' . trim($die['index_tab'] ?? '');
    $die['die_pc_status'] = !empty($die['die_pc_actual_date']) ? 'Finish' : 'Pending';
    $imgInfos             = getDieImages(trim($die['tech_dwg_no'] ?? ''), trim($die['section'] ?? ''));
    $die['img_paths']     = array_column($imgInfos, 'path');
    $die['img_names']     = array_column($imgInfos, 'filename');
}
unset($die);

// ── Build ZIP in temp directory ─────────────────────────────────
$tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $safeName . '_' . time() . '.zip';

$zip = new ZipArchive();
if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Could not create ZIP archive.']);
    exit;
}

// 1. plan.html — self-contained HTML table
$dateRange = calcWeekDateRange($week);
$zip->addFromString("{$folder}/plan.html", buildPlanHtml($dies, $week, $dateRange));

// 2. images/ — one file per matching image across all dies
$imgDir    = realpath(IMG_DIR) ?: IMG_DIR;
$addedImgs = 0;

foreach ($dies as $die) {
    foreach ($die['img_paths'] as $absPath) {
        if (is_file($absPath) && is_readable($absPath)) {
            $zip->addFile($absPath, "{$folder}/images/" . basename($absPath));
            $addedImgs++;
        }
    }
}

$zip->close();

// ── Stream download ─────────────────────────────────────────────
header('Content-Type: application/zip');
header("Content-Disposition: attachment; filename=\"{$zipName}\"");
header('Content-Length: ' . filesize($tmpPath));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($tmpPath);
@unlink($tmpPath);
exit;

// ════════════════════════════════════════════════════════════════
// Helper functions
// ════════════════════════════════════════════════════════════════

/**
 * Calculate Monday–Friday date range for an ISO week string "ww/yyyy".
 * Returns e.g. "13-17/4/2026" or "28/4-2/5/2026" if it spans a month.
 */
function calcWeekDateRange(string $weekKey): string {
    if (!str_contains($weekKey, '/')) return '';
    [$w, $y] = array_map('intval', explode('/', $weekKey, 2));
    if ($w < 1 || $w > 53 || $y < 2000 || $y > 2100) return '';
    try {
        $mon = (new DateTime())->setISODate($y, $w, 1);
        $fri = (new DateTime())->setISODate($y, $w, 5);
        [$d1,$m1,$y1] = [(int)$mon->format('j'), (int)$mon->format('n'), (int)$mon->format('Y')];
        [$d2,$m2]     = [(int)$fri->format('j'), (int)$fri->format('n')];
        return $m1 === $m2 ? "{$d1}-{$d2}/{$m1}/{$y1}" : "{$d1}/{$m1}-{$d2}/{$m2}/{$y1}";
    } catch (Exception) { return ''; }
}

/**
 * Format ISO date "2026-04-16" → "16/04/2026".
 */
function fmtDateHtml(?string $iso): string {
    if (!$iso) return '';
    $p = explode('-', $iso);
    return count($p) === 3 ? "{$p[2]}/{$p[1]}/{$p[0]}" : $iso;
}

/**
 * Inline HTML badge <span>.
 */
function badge(string $text, string $bg, string $fg = '#fff'): string {
    $t = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return "<span style=\"display:inline-block;padding:2px 9px;border-radius:12px;"
         . "font-size:10px;font-weight:700;background:{$bg};color:{$fg}\">{$t}</span>";
}

function dmkBadgeHtml(string $v): string {
    if ($v === '') return '<span style="color:#aaa">—</span>';
    return match ($v) {
        'W/C'          => badge($v, '#0d6efd'),
        'CNC'          => badge($v, '#ffc107', '#333'),
        'กัดเช็ค'     => badge($v, '#fd7e14'),
        'ชุบแข็ง'     => badge($v, '#6f42c1'),
        'กลึงเหวี่ยง' => badge($v, '#0dcaf0', '#333'),
        'กลึง'         => badge($v, '#6c757d'),
        default        => badge($v, '#6c757d'),
    };
}

function typeBadgeHtml(string $v): string {
    if ($v === '') return '—';
    return match ($v) {
        'Solid'    => badge($v, '#198754'),
        'Hollow'   => badge($v, '#ffc107', '#333'),
        'Heatsink' => badge($v, '#0d6efd'),
        default    => badge($v, '#6c757d'),
    };
}

function pcBadgeHtml(string $v): string {
    return $v === 'Finish'
        ? badge('Finish',  '#198754')
        : badge('Pending', '#ffc107', '#333');
}

function daysHtml(string $dueDate): string {
    if ($dueDate === '') return '<span style="color:#aaa">—</span>';
    $days = (int) round((strtotime($dueDate) - strtotime('today')) / 86400);
    if ($days < 0) return "<span style=\"color:#dc3545;font-weight:700\">{$days}d</span>";
    if ($days <= 3) return "<span style=\"color:#fd7e14;font-weight:600\">{$days}d</span>";
    return "<span style=\"color:#198754\">{$days}d</span>";
}

/**
 * Build the complete self-contained plan.html content.
 * Images are referenced as relative paths: images/filename.jpg
 */
function buildPlanHtml(array $dies, string $week, string $dateRange): string {
    $esc       = fn(mixed $s): string => htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $total     = count($dies);
    $generated = date('d/m/Y H:i:s');
    $title     = "แผนผลิตแม่พิมพ์ WEEK {$week}";

    // ── CSS (NOWDOC — no PHP interpolation) ──────────────────────
    $css = <<<'CSS'
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Sarabun', Tahoma, Arial, sans-serif;
    font-size: 12px;
    color: #212529;
    background: #fff;
    padding: 16px;
}
.doc-header {
    border-bottom: 3px solid #343a40;
    padding-bottom: 12px;
    margin-bottom: 16px;
}
.doc-header h1 { font-size: 22px; font-weight: 800; color: #212529; }
.doc-header .sub { font-size: 13px; color: #555; margin-top: 4px; }
.doc-header .meta {
    display: flex; gap: 24px;
    font-size: 11px; color: #888; margin-top: 6px;
}
table { width: 100%; border-collapse: collapse; }
thead tr { background: #ffc107; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
thead th {
    padding: 6px 8px;
    font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .03em;
    border: 1px solid #e6a800;
    white-space: nowrap; color: #333;
}
tbody td {
    padding: 4px 8px;
    border: 1px solid #dee2e6;
    vertical-align: middle;
}
tbody tr:nth-child(even) { background: #f9f9fa; }
.row-waiting { background: #fff8e1 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.row-hold    { background: #fdecea !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
tfoot tr {
    background: #343a40; color: #fff; font-weight: 700;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
tfoot td { padding: 6px 8px; border: 1px solid #212529; }
.thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 3px; border: 1px solid #ddd; display: block; }
.no-thumb {
    width: 60px; height: 60px;
    background: #f5f5f5; border: 1px dashed #ccc;
    border-radius: 3px; display: flex;
    align-items: center; justify-content: center;
    font-size: 10px; color: #bbb;
}
.tc { text-align: center; }
.doc-footer { margin-top: 14px; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 8px; }
@media print {
    body { padding: 0; font-size: 10px; }
    .thumb { width: 45px; height: 45px; }
    .no-thumb { width: 45px; height: 45px; }
}
CSS;

    // ── Table rows ────────────────────────────────────────────────
    $rows = '';
    foreach ($dies as $i => $die) {
        $rowCls = match ($die['plan_status'] ?? '') {
            'waiting' => ' class="row-waiting"',
            'hold'    => ' class="row-hold"',
            default   => '',
        };

        // Thumbnail — uses relative path inside ZIP
        if (!empty($die['img_names'])) {
            $firstImg = $die['img_names'][0];
            // rawurlencode only the filename, keep it readable in most cases
            $relSrc   = 'images/' . rawurlencode($firstImg);
            $imgCell  = "<img src=\"{$relSrc}\" alt=\"\" class=\"thumb\">";
        } else {
            $imgCell = '<div class="no-thumb">No Image</div>';
        }

        $rows .= "<tr{$rowCls}>"
            . "<td class=\"tc\">" . ($i + 1) . "</td>"
            . "<td class=\"tc\">{$imgCell}</td>"
            . "<td>"  . $esc($die['machine'])                . "</td>"
            . "<td style=\"font-weight:700;color:#0d6efd\">" . $esc($die['no'])          . "</td>"
            . "<td class=\"tc\">"                            . $esc($die['index_tab'])   . "</td>"
            . "<td>"  . fmtDateHtml($die['die_pc_due_date']) . "</td>"
            . "<td class=\"tc\">" . typeBadgeHtml($die['die_type'] ?? '') . "</td>"
            . "<td class=\"tc\">" . $esc($die['die_count'] ?? '—')        . "</td>"
            . "<td class=\"tc\">" . daysHtml($die['die_pc_due_date'] ?? '') . "</td>"
            . "<td class=\"tc\">" . dmkBadgeHtml($die['dmk_status'] ?? '') . "</td>"
            . "<td class=\"tc\">" . pcBadgeHtml($die['die_pc_status'])     . "</td>"
            . "<td>"  . fmtDateHtml($die['die_pc_actual_date']) . "</td>"
            . "<td style=\"color:#0dcaf0;font-weight:600\">" . $esc($die['die_no'])  . "</td>"
            . "<td>"  . $esc($die['forecast'])   . "</td>"
            . "<td>"  . $esc($die['remarks'])    . "</td>"
            . "</tr>\n";
    }

    // ── Assemble HTML ─────────────────────────────────────────────
    $escTitle  = $esc($title);
    $escRange  = $esc($dateRange);
    $escGen    = $esc($generated);

    return <<<HTML
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$escTitle}</title>
    <style>{$css}</style>
</head>
<body>

<div class="doc-header">
    <h1>{$escTitle}</h1>
    <p class="sub">Date Range: {$escRange}</p>
    <div class="meta">
        <span>Total Dies: <strong>{$total}</strong></span>
        <span>Generated: {$escGen}</span>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>รูป Section</th>
            <th>เครื่องรีด</th>
            <th>เบอร์แม่พิมพ์</th>
            <th>ทับ</th>
            <th>กำหนดเสร็จ</th>
            <th>ประเภท</th>
            <th>รู</th>
            <th>วัน</th>
            <th>สถานะ DMK</th>
            <th>สถานะ PC</th>
            <th>ส่งจริง</th>
            <th>Die No</th>
            <th>Forecast</th>
            <th>หมายเหตุ</th>
        </tr>
    </thead>
    <tbody>
{$rows}    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">รวม / Total</td>
            <td colspan="13">{$total} dies &nbsp;|&nbsp; Images: included in images/ folder</td>
        </tr>
    </tfoot>
</table>

<div class="doc-footer">
    Exported from Die Planning System &nbsp;·&nbsp; {$escGen}
</div>

</body>
</html>
HTML;
}
