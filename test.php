<?php
// Quick diagnostic — visit http://localhost/PlanningDie/test.php
// DELETE this file after debugging is done.
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<h2>PHP & SQLite Diagnostic</h2><pre>';

// 1. PHP version
echo 'PHP version : ' . PHP_VERSION . "\n";

// 2. Extensions
echo 'pdo         : ' . (extension_loaded('pdo')        ? 'OK' : 'MISSING') . "\n";
echo 'pdo_sqlite  : ' . (extension_loaded('pdo_sqlite') ? 'OK' : 'MISSING') . "\n";
echo 'sqlite3     : ' . (extension_loaded('sqlite3')    ? 'OK' : 'MISSING') . "\n";

// 3. DB file path
$dbPath = __DIR__ . '/db/die_planning.db';
echo "\nDB path     : $dbPath\n";
echo 'DB exists   : ' . (file_exists($dbPath) ? 'YES' : 'NO')   . "\n";
echo 'DB writable : ' . (is_writable(dirname($dbPath)) ? 'YES' : 'NO') . "\n";

// 4. Try connecting
if (extension_loaded('pdo_sqlite')) {
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode=WAL');
        echo "\nDB connect  : OK\n";

        // List tables
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        echo 'Tables      : ' . (empty($tables) ? '(none yet)' : implode(', ', $tables)) . "\n";

        // Count dies
        if (in_array('dies', $tables)) {
            $count = $pdo->query("SELECT COUNT(*) FROM dies")->fetchColumn();
            echo 'Dies rows   : ' . $count . "\n";
        }
    } catch (Exception $e) {
        echo "\nDB ERROR    : " . $e->getMessage() . "\n";
    }
} else {
    echo "\npdo_sqlite is NOT loaded — enable it in php.ini!\n";
    echo "Open XAMPP → Config → php.ini, find the line:\n";
    echo ";extension=pdo_sqlite\n";
    echo "Remove the semicolon, save, restart Apache.\n";
}

echo '</pre>';
