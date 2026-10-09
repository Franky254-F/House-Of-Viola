<?php
require __DIR__ . '/_init.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
if (empty($_SESSION['admin_id'])) { http_response_code(401); echo '{"error":"auth"}'; exit; }
$r = db()->query("SELECT (SELECT COALESCE(MAX(id),0) FROM orders) AS latest, (SELECT COUNT(*) FROM orders WHERE status='New') AS new_count")->fetch();
echo json_encode(['latest' => (int)$r['latest'], 'new' => (int)$r['new_count']]);
