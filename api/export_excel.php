<?php
/**
 * Export Excel (.xlsx) — DMK Plan for a given week
 *
 * GET /api/export_excel.php?week=19/2026
 *
 * Columns: #, รูป Section (embedded image), เครื่องรีด, เบอร์แม่พิมพ์,
 *          Tech DWG, ทับ, กำหนดเสร็จ, ประเภท, สถานะ DMK, สถานะ2, ส่งจริง, Die No
 * Images are embedded directly into column B cells using PhpSpreadsheet Drawing.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/image_helper.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;

// ── Validate week parameter ──────────────────────────────────────
$week    = trim($_GET['week'] ?? '');
$weekAll = ($week === '' || $week === 'all');

if (!$weekAll) {
    $parts = explode('/', $week, 2);
    if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => 'Invalid week format. Use ww/yyyy or all']);
        exit;
    }
    [$weekNum, $weekYear] = [(int)$parts[0], (int)$parts[1]];
} else {
    $week     = 'all';
    $weekNum  = 0;
    $weekYear = (int)date('Y');
}

// ── Fetch dies (Pending only — exclude completed) ────────────────
try {
    $pdo = getDB();
    if ($weekAll) {
        $stmt = $pdo->query("
            SELECT * FROM dies
            WHERE  (die_pc_actual_date IS NULL OR TRIM(die_pc_actual_date) = '')
            ORDER  BY die_finish_plan ASC, machine ASC, id ASC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM dies
            WHERE  TRIM(die_finish_plan) = :week
              AND  (die_pc_actual_date IS NULL OR TRIM(die_pc_actual_date) = '')
            ORDER  BY machine ASC, CAST(no AS TEXT) ASC, id ASC
        ");
        $stmt->execute([':week' => $week]);
    }
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
    echo json_encode(['error' => $weekAll ? 'No dies found' : "No dies found for week {$week}"]);
    exit;
}

// ── Enrich dies: computed fields + resolve image paths ───────────
foreach ($dies as &$die) {
    $die['die_no']        = trim($die['section'] ?? '') . '-' . trim($die['index_tab'] ?? '');
    $die['die_pc_status'] = !empty($die['die_pc_actual_date']) ? 'Finish' : 'Pending';

    // Use getDieImages — same lookup order as the display API (subfolder first, then flat)
    $imgPaths = findImagePathsForDie(
        trim($die['tech_dwg_no'] ?? ''),
        trim($die['section']     ?? '')
    );
    $die['img_real_paths'] = array_values(array_filter($imgPaths, 'is_readable'));
}
unset($die);

// ── Build Spreadsheet ────────────────────────────────────────────
$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->setTitle('DMK Plan');

// Page setup
$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
$sheet->getPageSetup()->setFitToPage(true);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);

// ── Row 1: Title ─────────────────────────────────────────────────
$titleText = $weekAll ? 'แผนผลิตแม่พิมพ์  ทุก Week' : "แผนผลิตแม่พิมพ์  WEEK {$week}";
$sheet->mergeCells('A1:M1');
$sheet->setCellValue('A1', $titleText);
$sheet->getStyle('A1')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1a3a5c']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(28);

// ── Row 2: Subtitle ───────────────────────────────────────────────
$dateRange   = $weekAll ? '' : calcDateRange($weekNum, $weekYear);
$subtitleParts = array_filter([
    $weekAll ? 'ทุก Week' : "Week {$week}",
    $dateRange,
    'Total: ' . count($dies) . ' dies  (Pending only)',
    'Generated: ' . date('d/m/Y H:i'),
]);
$sheet->mergeCells('A2:M2');
$sheet->setCellValue('A2', implode('   |   ', $subtitleParts));
$sheet->getStyle('A2')->applyFromArray([
    'font'      => ['size' => 9, 'color' => ['rgb' => '444444']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'F0F4F8']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(2)->setRowHeight(18);

// ── Row 3: Column headers ─────────────────────────────────────────
$headers = ['#', 'รูป Section', 'Week', 'เครื่องรีด', 'เบอร์แม่พิมพ์', 'Tech DWG', 'ทับ', 'กำหนดเสร็จ', 'ประเภท', 'สถานะ DMK', 'สถานะ2', 'ส่งจริง', 'Die No'];
$cols    = range('A', 'M');

foreach ($headers as $idx => $hdr) {
    $sheet->setCellValue($cols[$idx] . '3', $hdr);
}
$sheet->getStyle('A3:M3')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 9],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FFC107']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E6A800']]],
]);
$sheet->getRowDimension(3)->setRowHeight(22);

// ── Column widths ─────────────────────────────────────────────────
$colWidths = [
    'A' => 5,   // #
    'B' => 12,  // รูป Section
    'C' => 9,   // Week
    'D' => 11,  // เครื่องรีด
    'E' => 13,  // เบอร์แม่พิมพ์
    'F' => 14,  // Tech DWG
    'G' => 6,   // ทับ
    'H' => 13,  // กำหนดเสร็จ
    'I' => 10,  // ประเภท
    'J' => 11,  // สถานะ DMK
    'K' => 10,  // สถานะ2
    'L' => 13,  // ส่งจริง
    'M' => 14,  // Die No
];
foreach ($colWidths as $col => $w) {
    $sheet->getColumnDimension($col)->setWidth($w);
}

// ── Data rows (start at row 4) ────────────────────────────────────
$IMG_SIZE   = 72;   // px — image display size
$ROW_H_IMG  = 57;   // pts — row height when image present
$ROW_H_NO   = 18;   // pts — row height without image

$hasGd = extension_loaded('gd');

foreach ($dies as $rowIdx => $die) {
    $excelRow = $rowIdx + 4;
    $imgPath  = $die['img_real_paths'][0] ?? null;

    // Row background
    $rowBg = match ($die['plan_status'] ?? '') {
        'waiting' => 'FFF8E1',
        'hold'    => 'FDECEA',
        default   => 'FFFFFF',
    };

    // ── Cell values ───────────────────────────────────────────────
    $sheet->setCellValue("A{$excelRow}", $rowIdx + 1);
    // B = image
    $sheet->setCellValue("C{$excelRow}", $die['die_finish_plan'] ?? '');
    $sheet->setCellValue("D{$excelRow}", $die['machine']         ?? '');
    $sheet->setCellValue("E{$excelRow}", $die['section']         ?? '');
    $sheet->setCellValue("F{$excelRow}", $die['tech_dwg_no']     ?? '');
    $sheet->setCellValue("G{$excelRow}", $die['index_tab']       ?? '');
    $sheet->setCellValue("H{$excelRow}", fmtDateXlsx($die['die_pc_due_date'] ?? ''));
    $sheet->setCellValue("I{$excelRow}", $die['die_type']        ?? '');
    $sheet->setCellValue("J{$excelRow}", $die['dmk_status']      ?? '');
    $sheet->setCellValue("K{$excelRow}", $die['die_pc_status']   ?? '');
    $sheet->setCellValue("L{$excelRow}", fmtDateXlsx($die['die_pc_actual_date'] ?? ''));
    $sheet->setCellValue("M{$excelRow}", $die['die_no']          ?? '');

    // ── Row styles ────────────────────────────────────────────────
    $sheet->getStyle("A{$excelRow}:M{$excelRow}")->applyFromArray([
        'font'      => ['size' => 9],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => $rowBg]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DEE2E6']]],
    ]);
    $sheet->getStyle("A{$excelRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("C{$excelRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("G{$excelRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("I{$excelRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("J{$excelRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("K{$excelRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("E{$excelRow}")->getFont()->setBold(true);
    $sheet->getStyle("M{$excelRow}")->getFont()->setBold(true);
    $sheet->getStyle("M{$excelRow}")->getFont()->getColor()->setRGB('0D9488');

    if (($die['die_pc_status'] ?? '') === 'Finish') {
        $sheet->getStyle("K{$excelRow}")->getFont()->getColor()->setRGB('198754');
        $sheet->getStyle("K{$excelRow}")->getFont()->setBold(true);
    } else {
        $sheet->getStyle("K{$excelRow}")->getFont()->getColor()->setRGB('856404');
    }

    // ── Embed image ───────────────────────────────────────────────
    if ($imgPath !== null) {
        $sheet->getRowDimension($excelRow)->setRowHeight($ROW_H_IMG);
        embedImage($sheet, $imgPath, "B{$excelRow}", $IMG_SIZE, $hasGd);
    } else {
        $sheet->getRowDimension($excelRow)->setRowHeight($ROW_H_NO);
    }
}

// ── Freeze header ─────────────────────────────────────────────────
$sheet->freezePane('A4');

// ── Stream xlsx download ──────────────────────────────────────────
$safeName = $weekAll ? 'DMK_All_Weeks' : sprintf('DMK_WEEK%d_%d', $weekNum, $weekYear);
$filename = $safeName . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

// ══════════════════════════════════════════════════════════════════
// Helpers
// ══════════════════════════════════════════════════════════════════

/**
 * Embed an image into an Excel cell.
 * Tries GD-resize first (best quality), falls back to direct file embed.
 */
function embedImage(
    \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
    string $imgPath,
    string $coordinate,
    int    $sizePx,
    bool   $hasGd
): void {
    // ── Try GD resize (PNG thumbnail) ────────────────────────────
    if ($hasGd) {
        $gdSrc = loadGdImage($imgPath);
        if ($gdSrc !== null) {
            $thumb = imagecreatetruecolor($sizePx, $sizePx);
            // White background (for PNGs with transparency)
            $white = imagecolorallocate($thumb, 255, 255, 255);
            imagefill($thumb, 0, 0, $white);
            imagecopyresampled($thumb, $gdSrc, 0, 0, 0, 0,
                $sizePx, $sizePx, imagesx($gdSrc), imagesy($gdSrc));
            unset($gdSrc);

            $mem = new MemoryDrawing();
            $mem->setImageResource($thumb);
            $mem->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
            $mem->setMimeType(MemoryDrawing::MIMETYPE_PNG);
            $mem->setWidth($sizePx);
            $mem->setHeight($sizePx);
            $mem->setResizeProportional(false);
            $mem->setCoordinates($coordinate);
            $mem->setOffsetX(2);
            $mem->setOffsetY(2);
            $mem->setWorksheet($sheet);
            return;
        }
    }

    // ── Fallback: embed file directly (no resize) ─────────────────
    try {
        $drawing = new Drawing();
        $drawing->setPath($imgPath);
        $drawing->setHeight($sizePx);
        $drawing->setCoordinates($coordinate);
        $drawing->setOffsetX(2);
        $drawing->setOffsetY(2);
        $drawing->setWorksheet($sheet);
    } catch (Throwable) {
        // Silently skip — image format unsupported
    }
}

/**
 * Load a GD image from disk; returns null on failure.
 */
function loadGdImage(string $path): ?\GdImage {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $img = match ($ext) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($path),
        'png'         => @imagecreatefrompng($path),
        'gif'         => @imagecreatefromgif($path),
        'webp'        => @imagecreatefromwebp($path),
        'bmp'         => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($path) : false,
        default       => false,
    };
    return ($img instanceof \GdImage) ? $img : null;
}

function fmtDateXlsx(string $iso): string {
    if ($iso === '') return '';
    $p = explode('-', $iso);
    return count($p) === 3 ? "{$p[2]}/{$p[1]}/{$p[0]}" : $iso;
}

function calcDateRange(int $w, int $y): string {
    if ($w < 1 || $w > 53) return '';
    try {
        $mon = (new DateTime())->setISODate($y, $w, 1);
        $fri = (new DateTime())->setISODate($y, $w, 5);
        [$d1,$m1,$y1] = [(int)$mon->format('j'), (int)$mon->format('n'), (int)$mon->format('Y')];
        [$d2,$m2]     = [(int)$fri->format('j'), (int)$fri->format('n')];
        return $m1 === $m2 ? "{$d1}-{$d2}/{$m1}/{$y1}" : "{$d1}/{$m1}-{$d2}/{$m2}/{$y1}";
    } catch (Exception) { return ''; }
}
