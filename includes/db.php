<?php
/**
 * Database connection and schema initialization.
 * SQLite via PDO — db/die_planning.db
 */

// Resolve deployment base path dynamically (handles /boonsong/PlanningDie/ on server)
if (!defined('BASE_URL')) {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $pos = strpos($scriptName, '/PlanningDie');
    $basePath = ($pos !== false) ? substr($scriptName, 0, $pos + strlen('/PlanningDie')) : '/PlanningDie';
    define('BASE_URL', $basePath);
}

define('DB_PATH', __DIR__ . '/../db/die_planning.db');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('sqlite:' . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA foreign_keys=ON');
            initSchema($pdo);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

function initSchema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dies (
            id                  INTEGER PRIMARY KEY AUTOINCREMENT,
            no                  TEXT,
            reason              TEXT,
            customer            TEXT,
            section             TEXT,
            index_tab           TEXT,
            tech_dwg_no         TEXT,
            plan_send_date      TEXT,
            die_pc_due_date     TEXT,
            die_pc_actual_date  TEXT,
            die_finish_plan     TEXT,
            machine             TEXT,
            die_count           INTEGER DEFAULT 0,
            dmk_status          TEXT,
            forecast            TEXT,
            die_type            TEXT CHECK(die_type IN ('Solid','Hollow','Heatsink')),
            hollow_level        TEXT CHECK(hollow_level IN ('easy','medium','hard')),
            plan_status         TEXT DEFAULT 'normal' CHECK(plan_status IN ('normal','waiting','hold')),
            remarks             TEXT,
            created_at          TEXT DEFAULT (datetime('now','localtime')),
            updated_at          TEXT DEFAULT (datetime('now','localtime'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_settings (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            api_name        TEXT NOT NULL,
            api_url         TEXT NOT NULL,
            api_key         TEXT,
            headers         TEXT DEFAULT '{}',
            field_mapping   TEXT DEFAULT '{}',
            is_active       INTEGER DEFAULT 1,
            created_at      TEXT DEFAULT (datetime('now','localtime'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sync_log (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            synced_at   TEXT DEFAULT (datetime('now','localtime')),
            total       INTEGER DEFAULT 0,
            success     INTEGER DEFAULT 0,
            failed      INTEGER DEFAULT 0,
            details     TEXT DEFAULT '[]'
        )
    ");

    // Trigger: keep updated_at current on dies updates
    $pdo->exec("
        CREATE TRIGGER IF NOT EXISTS dies_updated_at
        AFTER UPDATE ON dies
        FOR EACH ROW
        BEGIN
            UPDATE dies SET updated_at = datetime('now','localtime') WHERE id = OLD.id;
        END
    ");

    // Insert default API settings on first run
    $hasSettings = (int) $pdo->query("SELECT COUNT(*) FROM api_settings")->fetchColumn();
    if ($hasSettings === 0) {
        $pdo->prepare("
            INSERT INTO api_settings (api_name, api_url, api_key, headers, field_mapping, is_active)
            VALUES (:name, :url, '', '{}', :fm, 1)
        ")->execute([
            ':name' => 'ALM DMK Status API',
            ':url'  => 'http://almdc.alumetgroup.com/alm/api/v1/alm_getDiemagStatus/',
            ':fm'   => json_encode(['dmk_status' => 'DieMagOrderProcessName']),
        ]);
    }
}
