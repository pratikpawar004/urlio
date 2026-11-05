<?php
declare(strict_types=1);
// Short link handler. Session tabhi start hota hai jab password chahiye.
define('NO_SESSION', true);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer-when-downgrade');

$code = (string)($_GET['c'] ?? '');
if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $code)) message_page('Link not found', 'This short link is invalid.', 404);

$l = q('SELECT * FROM links WHERE code = ?', [$code])->fetch();
if (!$l) message_page('Link not found', 'This short link does not exist or has been deleted.', 404);

$status = link_status($l);
if ($status === 'disabled') message_page('Link disabled', 'This link has been disabled by its owner.', 410);
if ($status === 'expired') message_page('Link expired', 'This link has expired or reached its click limit.', 410);

// Password protected link
if ($l['pass_hash']) {
    start_session();
    $unlocked = !empty($_SESSION['unlocked'][$l['id']]);
    if (!$unlocked) {
        $err = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            if (!rate_limit('pw:' . $l['id'] . ':' . client_ip(), 6, 900)) {
                $err = 'Too many incorrect attempts. Please try again in 15 minutes.';
            } elseif (password_verify((string)($_POST['password'] ?? ''), $l['pass_hash'])) {
                $_SESSION['unlocked'][$l['id']] = true;
                redirect(url($code));
            } else {
                $err = 'Galat password.';
            }
        }
        http_response_code(401);
        page_header('Password-protected link');
        ?>
        <section class="card center narrow">
          <h1>🔒 Password-protected link</h1>
          <p class="muted">Is link ko kholne ke liye password chahiye.</p>
          <?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
          <form method="post" action="<?= e(url($code)) ?>">
            <?= csrf_field() ?>
            <input class="input big" type="password" name="password" placeholder="Password" required autofocus>
            <p><button class="btn big" type="submit">Unlock</button></p>
          </form>
        </section>
        <?php
        page_footer();
        exit;
    }
}

// Click gino (max_clicks race-free: sirf tab badhega jab limit baaki ho)
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$info = parse_ua($ua);
$isHead = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';

if (!$info['bot'] && !$isHead) {
    $n = q('UPDATE links SET clicks = clicks + 1, last_click_at = ? WHERE id = ? AND (max_clicks IS NULL OR clicks < max_clicks)',
        [now(), $l['id']])->rowCount();
    if ($n === 0) message_page('Link expired', 'Is link ki click limit khatam ho chuki hai.', 410);

    $ih = ip_hash(client_ip());
    $seen = q('SELECT 1 FROM clicks WHERE link_id = ? AND ip_hash = ? LIMIT 1', [$l['id'], $ih])->fetch();
    q('INSERT INTO clicks (link_id, clicked_at, ip_hash, device, browser, os, referrer, is_unique) VALUES (?,?,?,?,?,?,?,?)',
        [$l['id'], now(), $ih, $info['device'], $info['browser'], $info['os'], referrer_host(), $seen ? 0 : 1]);
}

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Location: ' . $l['long_url'], true, 302);
exit;
