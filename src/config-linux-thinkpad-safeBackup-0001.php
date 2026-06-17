<?php
// config.php
ini_set('session.gc_maxlifetime', 43200);
ini_set('session.cookie_lifetime', 43200);
session_set_cookie_params(43200);
session_start();

define('DEBUG_MODE', false); // 本番運用のときは false にします

define('MAIL_SENDER', 'ttakao@mind-craft.net');

define('DB_HOST', 'mysql401.phy.lolipop.lan'); 
define('DB_USER', 'LA11154208');
define('DB_PASS', 'rm320260613');
define('DB_NAME', 'LA11154208-rm3');
define('APP_VERSION', '3.1');

function get_db_connection() {
    try {
        return new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'DB接続エラー: ' . $e->getMessage()]);
        exit;
    }
}