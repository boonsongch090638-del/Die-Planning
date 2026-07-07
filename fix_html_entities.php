<?php
/**
 * One-time migration: decode HTML entities stored in the dies table.
 * Run once via browser: http://localhost/PlanningDie/fix_html_entities.php
 * Delete this file after running.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (!isAdmin()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$pdo = getDB();

$textCols = [
    'no', 'reason', 'customer', 'section', 'index_tab', 'tech_dwg_no',
    'machine', 'dmk_status', 'die_type', 'remarks', 'die_finish_plan',
    'plan_send_date', 'die_pc_due_date', 'die_pc_actual_date', 'forecast',
];

$rows = $pdo->query('SELECT * FROM dies')->fetchAll(PDO::FETCH_ASSOC);
$fixed = 0;

$updateStmt = $pdo->prepare(
    'UPDATE dies SET ' .
    implode(', ', array_map(fn($c) => "{$c} = :{$c}", $textCols)) .
    ' WHERE id = :id'
);

foreach ($rows as $row) {
    $changed = false;
    $params  = [':id' => $row['id']];

    foreach ($textCols as $col) {
        $original = $row[$col] ?? '';
        $decoded  = html_entity_decode((string) $original, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $params[':' . $col] = $decoded;
        if ($decoded !== $original) {
            $changed = true;
        }
    }

    if ($changed) {
        $updateStmt->execute($params);
        $fixed++;
    }
}

echo "<pre>Done. Fixed {$fixed} row(s) out of " . count($rows) . " total.\n";
echo "Delete this file after running: fix_html_entities.php</pre>";
