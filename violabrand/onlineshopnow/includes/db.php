<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(db_dsn(), DB_USER, DB_PASS, db_pdo_options());
        } catch (PDOException $e) {
            http_response_code(503);
            $install = BASE_URL . '/install.php';
            echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
               . '<body style="font-family:system-ui,sans-serif;max-width:560px;margin:12vh auto;padding:0 18px;line-height:1.5">'
               . '<h2>The store database is not ready</h2>'
               . '<p>Make sure <b>Apache</b> and <b>MySQL</b> are both started in the XAMPP Control Panel, then run the installer once:</p>'
               . '<p><a href="' . htmlspecialchars($install) . '">Open the installer</a></p>'
               . '<p style="color:#666;font-size:14px">If you changed the MySQL password, update it in <code>config.php</code>.</p></body>';
            exit;
        }
    }
    return $pdo;
}
