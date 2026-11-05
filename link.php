<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$code = (string)($_GET['c'] ?? '');
$l = q('SELECT * FROM links WHERE code = ?', [$code])->fetch();
if (!$l || ((int)$l['user_id'] !== (int)$user['id'] && $user['role'] !== 'admin')) {
    message_page('Link not found', 'You do not own this link, or it does not exist.', 404);
}
$id = (int)$l['id'];

// ---- Edit form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $err = null;
    [$target, $err] = check_target((string)($_POST['url'] ?? ''));
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 150) ?: null;
    $exp = parse_dt($_POST['expires_at'] ?? null);
    $max = ($_POST['max_clicks'] ?? '') !== '' ? (int)$_POST['max_clicks'] : null;
    $newpass = (string)($_POST['password'] ?? '');
    if (!$err && $max !== null && $max < 1) $err = 'Max clicks kam se kam 1 ho.';
    if (!$err && $newpass !== '' && strlen($newpass) < 4) $err = 'Password kam se kam 4 characters ka ho.';
    if ($err) {
        flash_set('err', $err);
    } else {
        $hash = $l['pass_hash'];
        if (!empty($_POST['remove_password'])) $hash = null;
        elseif ($newpass !== '') $hash = password_hash($newpass, PASSWORD_DEFAULT);
        q('UPDATE links SET long_url=?, title=?, expires_at=?, max_clicks=?, pass_hash=? WHERE id=?',
            [$target, $title, $exp, $max, $hash, $id]);
        flash_set('ok', 'Link updated successfully.');
    }
    redirect(url('link.php?c=' . urlencode($code)));
}

// ---- Stats
$short = short_url($l['code']);
$since = date('Y-m-d 00:00:00', strtotime('-29 days'));
$byDay = q('SELECT DATE(clicked_at) d, COUNT(*) c, SUM(is_unique) u FROM clicks WHERE link_id = ? AND clicked_at >= ? GROUP BY d', [$id, $since])->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
$labels = $clicks = $uniques = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d M', strtotime($d));
    $clicks[] = (int)($byDay[$d]['c'] ?? 0);
    $uniques[] = (int)($byDay[$d]['u'] ?? 0);
}
function breakdown(int $id, string $col): array
{
    if (!in_array($col, ['device', 'browser', 'os', 'referrer'], true)) return [];
    $rows = q("SELECT $col n, COUNT(*) c FROM clicks WHERE link_id = ? GROUP BY $col ORDER BY c DESC LIMIT 8", [$id])->fetchAll();
    return ['labels' => array_column($rows, 'n'), 'data' => array_map('intval', array_column($rows, 'c'))];
}
$totals = q('SELECT COUNT(*) c, COALESCE(SUM(is_unique),0) u, COALESCE(SUM(clicked_at >= ?),0) w FROM clicks WHERE link_id = ?', [date('Y-m-d H:i:s', strtotime('-7 days')), $id])->fetch();
$charts = ['labels' => $labels, 'clicks' => $clicks, 'uniques' => $uniques,
    'device' => breakdown($id, 'device'), 'browser' => breakdown($id, 'browser'), 'os' => breakdown($id, 'os')];
$refs = breakdown($id, 'referrer');
$recent = q('SELECT * FROM clicks WHERE link_id = ? ORDER BY id DESC LIMIT 15', [$id])->fetchAll();

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
page_header('Stats: ' . $l['code'], 'dash', '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>');
?>
<p><a href="<?= e(url('dashboard.php')) ?>">← Dashboard</a></p>
<section class="card">
  <div class="between">
    <div>
      <h1 class="mono"><?= e(preg_replace('~^https?://~', '', $short)) ?></h1>
      <div class="muted ellipsis" title="<?= e($l['long_url']) ?>">→ <?= e($l['long_url']) ?></div>
      <div><?= status_badge($l) ?> <?php if ($l['title']): ?><span class="muted"><?= e($l['title']) ?></span><?php endif; ?></div>
    </div>
    <div class="actions">
      <button class="btn" type="button" data-copy="<?= e($short) ?>">Copy</button>
      <button class="btn btn-ghost" type="button" data-qr="<?= e($short) ?>">QR code</button>
      <a class="btn btn-ghost" href="<?= e(url('export.php?c=' . urlencode($code))) ?>">Export CSV</a>
    </div>
  </div>
</section>

<section class="stats">
  <div class="stat"><b><?= number_format((int)$l['clicks']) ?></b><span>Total clicks</span></div>
  <div class="stat"><b><?= number_format((int)$totals['u']) ?></b><span>Unique visitors</span></div>
  <div class="stat"><b><?= number_format((int)$totals['w']) ?></b><span>Pichhle 7 din</span></div>
  <div class="stat"><b><?= $l['last_click_at'] ? e(date('d M, H:i', strtotime($l['last_click_at']))) : '—' ?></b><span>Last click</span></div>
</section>

<section class="card"><h2>Clicks (pichhle 30 din)</h2><div class="chartbox"><canvas id="cDay"></canvas></div></section>

<section class="grid3 charts">
  <div class="card"><h3>Device</h3><div class="chartbox sm"><canvas id="cDevice"></canvas></div></div>
  <div class="card"><h3>Browser</h3><div class="chartbox sm"><canvas id="cBrowser"></canvas></div></div>
  <div class="card"><h3>OS</h3><div class="chartbox sm"><canvas id="cOs"></canvas></div></div>
</section>

<section class="grid2 charts">
  <div class="card"><h3>Top referrers</h3>
    <?php if (!$refs['labels']): ?><p class="muted">No data available yet.</p><?php else: ?>
    <table class="table"><tbody><?php foreach ($refs['labels'] as $i => $n): ?>
      <tr><td><?= e($n) ?></td><td class="num strong"><?= (int)$refs['data'][$i] ?></td></tr>
    <?php endforeach; ?></tbody></table><?php endif; ?>
  </div>
  <div class="card"><h3>Recent clicks</h3>
    <?php if (!$recent): ?><p class="muted">No clicks yet.</p><?php else: ?>
    <div class="tablewrap"><table class="table small"><thead><tr><th>Time</th><th>Device</th><th>Browser</th><th>From</th></tr></thead><tbody>
    <?php foreach ($recent as $c): ?>
      <tr><td><?= e(date('d M H:i', strtotime($c['clicked_at']))) ?></td><td><?= e($c['device']) ?></td><td><?= e($c['browser']) ?></td><td><?= e($c['referrer']) ?></td></tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div>
</section>

<section class="card">
  <h2>Edit link</h2>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <div class="grid2">
      <label>Destination URL<input class="input" name="url" value="<?= e($l['long_url']) ?>" required maxlength="2048"></label>
      <label>Title<input class="input" name="title" value="<?= e($l['title']) ?>" maxlength="150"></label>
      <label>Expiry<input class="input" type="datetime-local" name="expires_at" value="<?= $l['expires_at'] ? e(date('Y-m-d\TH:i', strtotime($l['expires_at']))) : '' ?>"></label>
      <label>Max clicks<input class="input" type="number" name="max_clicks" min="1" value="<?= e($l['max_clicks'] ?? '') ?>" placeholder="unlimited"></label>
      <label>New password <small>(<?= $l['pass_hash'] ? 'currently set' : 'not set' ?>)</small><input class="input" type="password" name="password" autocomplete="new-password" placeholder="leave blank to keep current password"></label>
      <?php if ($l['pass_hash']): ?><label class="check"><input type="checkbox" name="remove_password" value="1"> Password hata dein</label><?php endif; ?>
    </div>
    <div class="actions">
      <button class="btn" type="submit">Save</button>
    </div>
  </form>
  <form method="post" action="<?= e(url('actions.php')) ?>" class="inline">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <button class="btn btn-ghost" name="action" value="toggle" type="submit"><?= (int)$l['is_active'] ? 'Disable link' : 'Enable link' ?></button>
    <button class="btn btn-danger" name="action" value="delete" type="submit" onclick="return confirm('Link aur uska sara analytics delete ho jayega. Pakka?')">Delete link</button>
  </form>
</section>
<?php page_footer('<script>window.CHARTS = ' . json_encode($charts, $jsonFlags) . ';</script><script src="' . e(url('assets/js/charts.js')) . '?v=2"></script>');
