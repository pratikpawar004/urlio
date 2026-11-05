<?php
declare(strict_types=1);

require __DIR__ . '/config.php';


/* PHP 7.4 compatibility (InfinityFree ka PHP version pata na ho to bhi chale) */
if (!function_exists('str_contains')) { function str_contains(string $h, string $n): bool { return $n === '' || strpos($h, $n) !== false; } }
if (!function_exists('str_starts_with')) { function str_starts_with(string $h, string $n): bool { return strncmp($h, $n, strlen($n)) === 0; } }
if (!function_exists('str_ends_with')) { function str_ends_with(string $h, string $n): bool { return $n === '' || substr($h, -strlen($n)) === $n; } }

date_default_timezone_set(APP_TZ);
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

define('IS_HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'));

if (!defined('NO_SESSION')) {
    start_session();
}

function start_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) return;
    session_name('sid');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => IS_HTTPS,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

/* ------------------------------------------------------------------ DB */

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $e) {
        error_log('DB connect: ' . $e->getMessage());
        http_response_code(500);
        exit('Unable to connect to the database. Check the database settings in includes/config.php.'
            . (APP_DEBUG ? ' [' . $e->getMessage() . ']' : ''));
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function now(): string { return date('Y-m-d H:i:s'); }

/* ------------------------------------------------------------- helpers */

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function base_path(): string
{
    $d = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return $d === '/' || $d === '.' ? '' : rtrim($d, '/');
}

function base_url(): string
{
    if (APP_URL !== '') return rtrim(APP_URL, '/');
    return (IS_HTTPS ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path();
}

function url(string $p = ''): string { return base_path() . '/' . ltrim($p, '/'); }
function short_url(string $code): string { return base_url() . '/' . $code; }

function redirect(string $to)
{
    header('Location: ' . $to);
    exit;
}

function flash_set(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flash_get(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verify(): void
{
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        exit('Your session has expired. Refresh the page and try again.');
    }
}

function is_private_ip(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    // Proxy ke peeche ho tabhi forwarded header par bharosa karo
    if (is_private_ip($ip)) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $cand = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($cand, FILTER_VALIDATE_IP)) return $cand;
            }
        }
    }
    return $ip;
}

function app_secret(): string
{
    static $s = null;
    if ($s === null) {
        $f = __DIR__ . '/secret.key';
        $s = is_file($f) ? trim((string)file_get_contents($f)) : hash('sha256', DB_PASS . DB_NAME);
    }
    return $s;
}
function ip_hash(string $ip): string { return hash('sha256', $ip . app_secret()); }

/** true = allowed, false = limit cross ho gayi */
function rate_limit(string $key, int $max, int $window): bool
{
    $key = substr(hash('sha256', $key), 0, 40);
    $t = time();
    $row = q('SELECT window_start, hits FROM rate_limits WHERE k = ?', [$key])->fetch();
    if (!$row || $t - (int)$row['window_start'] >= $window) {
        q('REPLACE INTO rate_limits (k, window_start, hits) VALUES (?, ?, 1)', [$key, $t]);
        if (random_int(1, 100) === 1) q('DELETE FROM rate_limits WHERE window_start < ?', [$t - 86400]);
        return true;
    }
    if ((int)$row['hits'] >= $max) return false;
    q('UPDATE rate_limits SET hits = hits + 1 WHERE k = ?', [$key]);
    return true;
}

/* ---------------------------------------------------------------- auth */

function current_user(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $row = q('SELECT * FROM users WHERE id = ?', [$_SESSION['uid']])->fetch();
        if ($row && !(int)$row['is_banned']) $u = $row;
        else unset($_SESSION['uid']);
    }
    return $u;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash_set('warn', 'Please log in to continue.');
        redirect(url('login.php?next=' . urlencode(basename($_SERVER['SCRIPT_NAME']))));
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') { http_response_code(403); exit('Access denied.'); }
    return $u;
}

function safe_next(?string $n): string
{
    if ($n && preg_match('~^[a-z0-9_./?=&-]+$~i', $n) && !str_starts_with($n, '/') && !str_contains($n, '..')) {
        return url($n);
    }
    return url('dashboard.php');
}

function guest_token(bool $create = false): ?string
{
    $t = $_COOKIE['gt'] ?? '';
    if (is_string($t) && preg_match('/^[a-f0-9]{32}$/', $t)) return $t;
    if (!$create) return null;
    $t = bin2hex(random_bytes(16));
    setcookie('gt', $t, ['expires' => time() + 86400 * 365, 'path' => '/', 'secure' => IS_HTTPS,
        'httponly' => true, 'samesite' => 'Lax']);
    $_COOKIE['gt'] = $t;
    return $t;
}

function login_user(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    unset($_SESSION['csrf']);
    q('UPDATE users SET last_login = ? WHERE id = ?', [now(), $u['id']]);
    // guest ke links account me jod do (aur permanent kar do)
    if ($gt = guest_token()) {
        q('UPDATE links SET user_id = ?, guest_token = NULL, expires_at = NULL WHERE guest_token = ? AND user_id IS NULL',
            [$u['id'], $gt]);
    }
}

/* ------------------------------------------------------ URL validation */

const RESERVED_CODES = ['login', 'logout', 'register', 'dashboard', 'admin', 'api', 'assets', 'includes',
    'install', 'link', 'actions', 'account', 'export', 'index', 'go', 'about', 'help', 'terms', 'privacy',
    'contact', 'static', 'robots', 'favicon'];

function valid_alias(string $a): bool
{
    return (bool)preg_match('/^[A-Za-z0-9_-]{3,32}$/', $a) && !in_array(strtolower($a), RESERVED_CODES, true);
}

/** @return array{0:?string,1:?string} [normalized url, error] */
function check_target(string $raw): array
{
    $u = trim($raw);
    if ($u === '') return [null, 'Please enter a URL.'];
    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $u)) {
        if (preg_match('~^(javascript|data|file|vbscript|mailto|tel):~i', $u)) return [null, 'Sirf http/https links allowed hain.'];
        $u = 'https://' . $u;
    }
    if (strlen($u) > 2048) return [null, 'URL bahut lamba hai (max 2048 characters).'];
    if (preg_match('/[\s<>"\']/', $u)) return [null, 'URL me galat characters hain.'];
    $p = parse_url($u);
    if (!$p || empty($p['host']) || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) {
        return [null, 'Please enter a valid HTTP or HTTPS URL.'];
    }
    if (isset($p['user']) || isset($p['pass'])) return [null, 'URLs containing embedded usernames or passwords are not allowed.'];
    $host = strtolower(trim($p['host'], '[]'));
    if ($host === 'localhost' || preg_match('/\.(local|internal|localhost|lan)$/', $host)) return [null, 'Local addresses are not allowed.'];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (is_private_ip($host)) return [null, 'Private IP addresses are not allowed.'];
    } elseif (!preg_match('/^(?:[a-z0-9\x80-\xff](?:[a-z0-9\x80-\xff-]{0,61}[a-z0-9\x80-\xff])?\.)+[a-z\x80-\xff0-9-]{2,}$/i', $host)) {
        return [null, 'The domain name does not appear to be valid.'];
    }
    $mine = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    if ($mine !== '' && ($host === $mine || str_ends_with($host, '.' . $mine))) return [null, 'You cannot shorten a link from this website.'];
    // blocked domains (parent domains bhi check hote hain)
    $parts = explode('.', $host);
    $cands = [];
    for ($i = 0; $i < count($parts) - 1; $i++) $cands[] = implode('.', array_slice($parts, $i));
    if ($cands) {
        $in = implode(',', array_fill(0, count($cands), '?'));
        if (q("SELECT 1 FROM blocked_domains WHERE domain IN ($in) LIMIT 1", $cands)->fetch()) {
            return [null, 'Yeh domain block hai.'];
        }
    }
    return [$u, null];
}

function add_utm(string $url, array $in): string
{
    $utm = [];
    foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $k) {
        $v = trim((string)($in[$k] ?? ''));
        if ($v !== '') $utm[$k] = mb_substr($v, 0, 100);
    }
    if (!$utm) return $url;
    $frag = '';
    if (($i = strpos($url, '#')) !== false) { $frag = substr($url, $i); $url = substr($url, 0, $i); }
    return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($utm) . $frag;
}

function parse_dt(?string $s): ?string
{
    $s = trim((string)$s);
    if ($s === '') return null;
    $t = strtotime($s);
    return $t ? date('Y-m-d H:i:s', $t) : null;
}

function gen_code(int $len = 6): string
{
    $abc = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $s = '';
    for ($i = 0; $i < $len; $i++) $s .= $abc[random_int(0, 61)];
    return $s;
}

/**
 * Link banata hai. $in keys: url, alias, title, password, expires_at, max_clicks, utm_*
 * @return array{0:?array,1:?string} [link row, error]
 */
function create_link(array $in, ?array $user, ?string $gt): array
{
    [$target, $err] = check_target((string)($in['url'] ?? ''));
    if ($err) return [null, $err];
    $target = add_utm($target, $in);
    if (strlen($target) > 2048) return [null, 'The URL is too long after adding UTM parameters.'];

    $alias = trim((string)($in['alias'] ?? ''));
    $title = mb_substr(trim((string)($in['title'] ?? '')), 0, 150) ?: null;
    $pass = (string)($in['password'] ?? '');
    $exp = parse_dt($in['expires_at'] ?? null);
    $max = ($in['max_clicks'] ?? '') !== '' ? (int)$in['max_clicks'] : null;

    if ($user) {
        if ($alias !== '') {
            if (!valid_alias($alias)) return [null, 'Alias 3-32 characters ka ho (a-z, A-Z, 0-9, - _) aur reserved word na ho.'];
            if (q('SELECT 1 FROM links WHERE code = ?', [$alias])->fetch()) return [null, 'Yeh alias pehle se le liya gaya hai.'];
        }
        if ($exp && strtotime($exp) <= time()) return [null, 'Expiry future ki date honi chahiye.'];
        if ($max !== null && $max < 1) return [null, 'Max clicks kam se kam 1 hona chahiye.'];
        if ($pass !== '' && strlen($pass) < 4) return [null, 'Link password kam se kam 4 characters ka ho.'];
        $cnt = (int)q('SELECT COUNT(*) FROM links WHERE user_id = ?', [$user['id']])->fetchColumn();
        if ($cnt >= MAX_LINKS_PER_USER) return [null, 'Your link limit (' . MAX_LINKS_PER_USER . ') has been reached.'];
    } else {
        $alias = ''; $pass = ''; $max = null; $title = null;
        $exp = date('Y-m-d H:i:s', time() + 86400 * GUEST_LINK_DAYS);
    }

    $hash = $pass !== '' ? password_hash($pass, PASSWORD_DEFAULT) : null;
    for ($try = 0; $try < 6; $try++) {
        $code = $alias !== '' ? $alias : gen_code($try < 3 ? 6 : 7);
        if (in_array(strtolower($code), RESERVED_CODES, true)) continue;
        try {
            q('INSERT INTO links (user_id, guest_token, code, long_url, title, pass_hash, expires_at, max_clicks, created_at, created_ip)
               VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$user['id'] ?? null, $user ? null : $gt, $code, $target, $title, $hash, $exp, $max, now(), ip_hash(client_ip())]);
            $id = (int)db()->lastInsertId();
            return [q('SELECT * FROM links WHERE id = ?', [$id])->fetch(), null];
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            if ($alias !== '') return [null, 'Yeh alias pehle se le liya gaya hai.'];
        }
    }
    return [null, 'A short code could not be generated. Please try again.'];
}

function link_status(array $l): string
{
    if (!(int)$l['is_active']) return 'disabled';
    if ($l['expires_at'] && strtotime($l['expires_at']) <= time()) return 'expired';
    if ($l['max_clicks'] !== null && (int)$l['clicks'] >= (int)$l['max_clicks']) return 'expired';
    return 'active';
}

/* ------------------------------------------------------- UA / analytics */

function parse_ua(string $ua): array
{
    $bot = (bool)preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|curl|wget|python|java\/|go-http|headless|lighthouse|whatsapp|telegram|discord|embedly|pingdom|uptime|fetch|scanner|httpclient/i', $ua);
    $device = preg_match('/ipad|tablet|kindle|silk/i', $ua) ? 'Tablet'
        : (preg_match('/mobi|android|iphone|ipod/i', $ua) ? 'Mobile' : 'Desktop');
    $browser = 'Other';
    foreach (['Edge' => '/edg(e|a|ios)?\//i', 'Opera' => '/opr\/|opera/i', 'Samsung' => '/samsungbrowser/i',
        'Firefox' => '/firefox|fxios/i', 'Chrome' => '/chrome|crios/i', 'Safari' => '/safari/i',
        'IE' => '/msie|trident/i'] as $n => $re) {
        if (preg_match($re, $ua)) { $browser = $n; break; }
    }
    $os = 'Other';
    foreach (['Android' => '/android/i', 'iOS' => '/iphone|ipad|ipod/i', 'Windows' => '/windows/i',
        'macOS' => '/macintosh|mac os/i', 'ChromeOS' => '/cros/i', 'Linux' => '/linux|x11/i'] as $n => $re) {
        if (preg_match($re, $ua)) { $os = $n; break; }
    }
    return compact('bot', 'device', 'browser', 'os');
}

function referrer_host(): string
{
    $r = $_SERVER['HTTP_REFERER'] ?? '';
    $h = $r ? parse_url($r, PHP_URL_HOST) : null;
    return $h ? mb_substr(strtolower(preg_replace('/^www\./', '', $h)), 0, 100) : 'Direct';
}

function host_of(string $u): string
{
    return preg_replace('/^www\./', '', (string)parse_url($u, PHP_URL_HOST));
}
