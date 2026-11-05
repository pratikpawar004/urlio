<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$code = (string)($_GET['c'] ?? '');

function csv_safe($v): string
{
    $v = (string)$v;
    // Excel formula injection se bachav
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

header('Content-Type: text/csv; charset=utf-8');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");   // Excel ke liye UTF-8 BOM

if ($code !== '') {
    $l = q('SELECT * FROM links WHERE code = ?', [$code])->fetch();
    if (!$l || ((int)$l['user_id'] !== (int)$user['id'] && $user['role'] !== 'admin')) { http_response_code(404); exit('Not found'); }
    header('Content-Disposition: attachment; filename="clicks-' . preg_replace('/[^A-Za-z0-9_-]/', '', $code) . '.csv"');
    fputcsv($out, ['time', 'device', 'browser', 'os', 'referrer', 'unique']);
    $st = q('SELECT * FROM clicks WHERE link_id = ? ORDER BY id DESC LIMIT 50000', [$l['id']]);
    while ($c = $st->fetch()) fputcsv($out, [$c['clicked_at'], $c['device'], $c['browser'], $c['os'], csv_safe($c['referrer']), $c['is_unique'] ? 'yes' : 'no']);
} else {
    header('Content-Disposition: attachment; filename="my-links.csv"');
    fputcsv($out, ['short_url', 'destination', 'title', 'clicks', 'status', 'created', 'expires']);
    $st = q('SELECT * FROM links WHERE user_id = ? ORDER BY id DESC', [$user['id']]);
    while ($l = $st->fetch()) fputcsv($out, [short_url($l['code']), csv_safe($l['long_url']), csv_safe($l['title']), $l['clicks'], link_status($l), $l['created_at'], $l['expires_at']]);
}
