<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$uid = (int)$user['id'];

$search = trim((string)($_GET['s'] ?? ''));
$page = max(1, (int)($_GET['p'] ?? 1));
$per = 15;

$where = 'user_id = ?';
$params = [$uid];
if ($search !== '') {
    $where .= ' AND (code LIKE ? OR long_url LIKE ? OR title LIKE ?)';
    $like = '%' . addcslashes($search, '%_\\') . '%';
    array_push($params, $like, $like, $like);
}
$total = (int)q("SELECT COUNT(*) FROM links WHERE $where", $params)->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$off = ($page - 1) * $per;
$links = q("SELECT * FROM links WHERE $where ORDER BY id DESC LIMIT $per OFFSET $off", $params)->fetchAll();

$s = q('SELECT COUNT(*) c, COALESCE(SUM(clicks),0) k, SUM(is_active=1 AND (expires_at IS NULL OR expires_at > ?) AND (max_clicks IS NULL OR clicks < max_clicks)) a FROM links WHERE user_id = ?', [now(), $uid])->fetch();
$today = (int)q('SELECT COUNT(*) FROM clicks cl JOIN links l ON l.id = cl.link_id WHERE l.user_id = ? AND cl.clicked_at >= ?', [$uid, date('Y-m-d 00:00:00')])->fetchColumn();

page_header('Dashboard', 'dash');
?>
<h1>Dashboard</h1>
<section class="stats">
  <div class="stat"><b><?= number_format((int)$s['c']) ?></b><span>Total links</span></div>
  <div class="stat"><b><?= number_format((int)$s['k']) ?></b><span>Total clicks</span></div>
  <div class="stat"><b><?= number_format($today) ?></b><span>Clicks today</span></div>
  <div class="stat"><b><?= number_format((int)$s['a']) ?></b><span>Active links</span></div>
</section>

<section class="card"><h2>Create a link</h2><?php render_shorten_form($user); ?></section>

<section class="card">
  <details>
    <summary><b>Bulk shortening</b> <span class="muted small">(one URL per line, maximum <?= (int)BULK_MAX ?>)</span></summary>
    <form method="post" action="<?= e(url('actions.php')) ?>" class="stack">
      <?= csrf_field() ?><input type="hidden" name="action" value="bulk">
      <textarea class="input" name="urls" rows="5" placeholder="https://example.com/long-1&#10;https://example.com/long-2" required></textarea>
      <div><button class="btn" type="submit">Shorten all</button></div>
    </form>
  </details>
</section>

<section class="card">
  <div class="between">
    <h2>Your links <span class="muted small">(<?= number_format($total) ?>)</span></h2>
    <div class="row-inline">
      <form method="get" class="row-inline"><input class="input" name="s" value="<?= e($search) ?>" placeholder="Search…"><button class="btn btn-ghost" type="submit">Search</button></form>
      <a class="btn btn-ghost" href="<?= e(url('export.php')) ?>">CSV</a>
    </div>
  </div>
  <?php render_links_table($links); ?>
  <?php if ($pages > 1): ?>
    <div class="pager">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a class="<?= $i === $page ? 'cur' : '' ?>" href="?p=<?= $i ?>&s=<?= urlencode($search) ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</section>
<?php page_footer();
