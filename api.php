<?php
declare(strict_types=1);
define('NO_SESSION', true);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function jout(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$key = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($key === '' && preg_match('/^Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'] ?? '', $m)) $key = $m[1];
$user = (is_string($key) && preg_match('/^[a-f0-9]{40}$/', $key))
    ? q('SELECT * FROM users WHERE api_key = ? AND is_banned = 0', [$key])->fetch() : null;
if (!$user) jout(401, ['ok' => false, 'error' => 'Invalid ya missing API key (X-API-Key header).']);
if (!rate_limit('api:' . $user['id'], 120, 3600)) jout(429, ['ok' => false, 'error' => 'Rate limit: 120 requests/hour.']);

$action = $_GET['action'] ?? '';
$fmt = fn(array $l) => [
    'code' => $l['code'], 'short_url' => short_url($l['code']), 'long_url' => $l['long_url'],
    'title' => $l['title'], 'clicks' => (int)$l['clicks'], 'status' => link_status($l),
    'expires_at' => $l['expires_at'], 'max_clicks' => $l['max_clicks'] !== null ? (int)$l['max_clicks'] : null,
    'protected' => (bool)$l['pass_hash'], 'created_at' => $l['created_at'],
];

if ($action === 'shorten' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($in)) $in = $_POST;
    if (!rate_limit('create:u' . $user['id'], USER_HOURLY_LIMIT, 3600)) jout(429, ['ok' => false, 'error' => 'The hourly link creation limit has been reached.']);
    [$l, $err] = create_link($in, $user, null);
    $err ? jout(422, ['ok' => false, 'error' => $err]) : jout(201, ['ok' => true] + $fmt($l));
}

if ($action === 'stats') {
    $l = q('SELECT * FROM links WHERE code = ? AND user_id = ?', [(string)($_GET['code'] ?? ''), $user['id']])->fetch();
    if (!$l) jout(404, ['ok' => false, 'error' => 'Link not found.']);
    $by = fn(string $col) => q("SELECT $col n, COUNT(*) c FROM clicks WHERE link_id = ? GROUP BY $col ORDER BY c DESC LIMIT 10", [$l['id']])->fetchAll(PDO::FETCH_KEY_PAIR);
    $u = (int)q('SELECT COALESCE(SUM(is_unique),0) FROM clicks WHERE link_id = ?', [$l['id']])->fetchColumn();
    jout(200, ['ok' => true] + $fmt($l) + ['unique_clicks' => $u, 'devices' => $by('device'), 'browsers' => $by('browser'), 'os' => $by('os'), 'referrers' => $by('referrer')]);
}

if ($action === 'list') {
    $per = 20; $off = (max(1, (int)($_GET['page'] ?? 1)) - 1) * $per;
    $rows = q("SELECT * FROM links WHERE user_id = ? ORDER BY id DESC LIMIT $per OFFSET $off", [$user['id']])->fetchAll();
    jout(200, ['ok' => true, 'links' => array_map($fmt, $rows)]);
}

jout(400, ['ok' => false, 'error' => 'Unknown action. Use: shorten (POST), stats, list.']);
