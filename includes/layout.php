<?php
declare(strict_types=1);

function page_header(string $title, string $active = '', string $extraHead = ''): void
{
    $u = current_user();
    $nav = function (string $key, string $href, string $label) use ($active): string {
        return '<a href="' . e(url($href)) . '"' . ($active === $key ? ' class="active"' : '') . '>' . e($label) . '</a>';
    };
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
<meta name="description" content="Create clean, memorable short links. Track clicks and manage every destination in one place.">
<link rel="icon" type="image/png" href="<?= e(url('assets/img/logo1.png')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>?v=3">
<?= $extraHead ?>
</head>
<body>
<header class="topbar">
  <div class="wrap bar">
   <a class="brand" href="/">
    <span class="brand-mark">U</span>
    <span class="brand-name">URLIO</span>
</a>
    <button class="burger" type="button" aria-label="Menu" onclick="document.body.classList.toggle('navopen')">☰</button>
    <nav class="nav">
      <?= $nav('home', '', 'Home') ?>
      <?php if ($u): ?>
        <?= $nav('dash', 'dashboard.php', 'Dashboard') ?>
        <?= $nav('account', 'account.php', 'Account') ?>
        <?php if ($u['role'] === 'admin') echo $nav('admin', 'admin.php', 'Admin'); ?>
        <form method="post" action="<?= e(url('logout.php')) ?>" class="inline"><?= csrf_field() ?><button class="linkbtn" type="submit">Logout (<?= e(explode(' ', $u['name'])[0]) ?>)</button></form>
      <?php else: ?>
        <?= $nav('login', 'login.php', 'Login') ?>
        <a class="btn btn-sm" href="<?= e(url('register.php')) ?>">Sign up</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="wrap">
<?php foreach (flash_get() as [$t, $m]): ?>
  <div class="alert alert-<?= e($t) ?>"><?= e($m) ?></div>
<?php endforeach;
}

function page_footer(string $extraScripts = ''): void
{
    ?>
</main>
<footer class="footer">
  <div class="wrap">&copy; <?= date('Y') ?> URLIO. Simple links. Smarter sharing.</div>
</footer>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>window.APP = {csrf: <?= json_encode(csrf_token()) ?>, shortenUrl: <?= json_encode(url('actions.php')) ?>};</script>
<script src="<?= e(url('assets/js/app.js')) ?>?v=3"></script>
<?= $extraScripts ?>
</body>
</html>
<?php
}

/** Compact information page used by go.php */
function message_page(string $title, string $msg, int $status = 200): void
{
    http_response_code($status);
    page_header($title);
    echo '<section class="card center narrow"><h1>' . e($title) . '</h1><p class="muted">' . e($msg) . '</p>'
        . '<p><a class="btn" href="' . e(url('')) . '/">Back to home</a></p></section>';
    page_footer();
    exit;
}

function render_shorten_form(?array $user, string $id = 'shortenForm'): void
{
    ?>
<form id="<?= e($id) ?>" class="shorten" method="post" action="<?= e(url('actions.php')) ?>" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <div class="row-inline">
    <input type="text" name="url" class="input big" placeholder="Paste your long URL here (https://example.com)" required maxlength="2048" inputmode="url">
    <button class="btn big" type="submit">Shorten</button>
  </div>
  <?php if ($user): ?>
  <details class="adv">
    <summary>Advanced options</summary>
    <div class="grid2">
      <label>Custom alias <small>(3–32 characters)</small>
        <div class="prefix"><span><?= e(preg_replace('~^https?://~', '', base_url())) ?>/</span><input type="text" name="alias" maxlength="32" pattern="[A-Za-z0-9_-]{3,32}" placeholder="my-link"></div></label>
      <label>Title <small>(private label)</small><input type="text" name="title" maxlength="150" placeholder="Campaign landing page"></label>
      <label>Password protect <small>(optional)</small><input type="password" name="password" minlength="4" maxlength="100" autocomplete="new-password"></label>
      <label>Expiry date &amp; time<input type="datetime-local" name="expires_at"></label>
      <label>Max clicks <small>(disable after this limit)</small><input type="number" name="max_clicks" min="1" max="100000000" placeholder="Unlimited"></label>
    </div>
    <p class="muted small">UTM builder (optional) — parameters are added to your destination URL:</p>
    <div class="grid3">
      <input type="text" name="utm_source" placeholder="utm_source (newsletter)">
      <input type="text" name="utm_medium" placeholder="utm_medium (email)">
      <input type="text" name="utm_campaign" placeholder="utm_campaign (launch)">
    </div>
  </details>
  <?php else: ?>
  <p class="muted small hint">Guest links stay active for <?= (int)GUEST_LINK_DAYS ?> days. No account required. <a href="<?= e(url('register.php')) ?>">Create a free account</a> to keep your links and unlock custom aliases, password protection and analytics.</p>
  <?php endif; ?>
</form>
<div id="result"></div>
<?php
}

function render_result_card(array $l): string
{
    $s = short_url($l['code']);
    ob_start(); ?>
<div class="card result">
  <div class="muted small">Your short link is ready</div>
  <div class="row-inline">
    <input class="input big mono" readonly value="<?= e($s) ?>" onclick="this.select()">
    <button class="btn big" type="button" data-copy="<?= e($s) ?>">Copy</button>
  </div>
  <div class="actions">
    <button class="btn btn-ghost" type="button" data-qr="<?= e($s) ?>">QR code</button>
    <a class="btn btn-ghost" href="<?= e($s) ?>" target="_blank" rel="noopener">Open</a>
    <a class="btn btn-ghost" href="https://wa.me/?text=<?= e(rawurlencode($s)) ?>" target="_blank" rel="noopener">WhatsApp</a>
    <?php if ($l['user_id']): ?><a class="btn btn-ghost" href="<?= e(url('link.php?c=' . $l['code'])) ?>">Stats</a><?php endif; ?>
  </div>
  <div class="muted small ellipsis" title="<?= e($l['long_url']) ?>">→ <?= e($l['long_url']) ?></div>
</div>
<?php
    return (string)ob_get_clean();
}

function status_badge(array $l): string
{
    $st = link_status($l);
    $out = '<span class="badge b-' . $st . '">' . ($st === 'active' ? 'Active' : ($st === 'expired' ? 'Expired' : 'Disabled')) . '</span>';
    if ($l['pass_hash']) $out .= ' <span class="badge b-lock" title="Password protected">🔒</span>';
    return $out;
}

/** Links table (dashboard + home) */
function render_links_table(array $links, bool $actions = true): void
{
    if (!$links) { echo '<p class="muted">No links yet.</p>'; return; }
    ?>
<div class="tablewrap"><table class="table">
  <thead><tr><th>Short link</th><th>Destination</th><th class="num">Clicks</th><th>Status</th><th>Created</th><?php if ($actions): ?><th></th><?php endif; ?></tr></thead>
  <tbody>
  <?php foreach ($links as $l): $s = short_url($l['code']); ?>
    <tr>
      <td data-label="Short">
        <a class="mono strong" href="<?= e($s) ?>" target="_blank" rel="noopener"><?= e(preg_replace('~^https?://~', '', $s)) ?></a>
        <?php if ($l['title']): ?><div class="muted small"><?= e($l['title']) ?></div><?php endif; ?>
        <div class="mini"><button type="button" class="linkbtn" data-copy="<?= e($s) ?>">Copy</button> · <button type="button" class="linkbtn" data-qr="<?= e($s) ?>">QR</button></div>
      </td>
      <td data-label="To" class="ellipsis" title="<?= e($l['long_url']) ?>"><?= e(host_of($l['long_url'])) ?><span class="muted small"><?= e(mb_strimwidth((string)parse_url($l['long_url'], PHP_URL_PATH), 0, 24, '…')) ?></span></td>
      <td data-label="Clicks" class="num strong"><?= number_format((int)$l['clicks']) ?></td>
      <td data-label="Status"><?= status_badge($l) ?><?php if ($l['expires_at']): ?><div class="muted small">until <?= e(date('d M, H:i', strtotime($l['expires_at']))) ?></div><?php endif; ?></td>
      <td data-label="Created" class="muted small"><?= e(date('d M Y', strtotime($l['created_at']))) ?></td>
      <?php if ($actions): ?>
      <td class="acts">
        <?php if ($l['user_id']): ?><a class="btn btn-sm btn-ghost" href="<?= e(url('link.php?c=' . $l['code'])) ?>">Stats</a><?php endif; ?>
        <form method="post" action="<?= e(url('actions.php')) ?>" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
          <button class="btn btn-sm btn-ghost" name="action" value="toggle" type="submit"><?= (int)$l['is_active'] ? 'Disable' : 'Enable' ?></button>
          <button class="btn btn-sm btn-danger" name="action" value="delete" type="submit" onclick="return confirm('This link and its analytics will be permanently deleted. Continue?')">Delete</button>
        </form>
      </td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php
}
