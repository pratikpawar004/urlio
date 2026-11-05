<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$admin = require_admin();
$tab = in_array($_GET['tab'] ?? '', ['users', 'links', 'blocked'], true) ? $_GET['tab'] : 'overview';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $a = (string)($_POST['action'] ?? '');
    $uid = (int)($_POST['uid'] ?? 0);
    $target = $uid ? q('SELECT * FROM users WHERE id = ?', [$uid])->fetch() : null;
    $self = $target && (int)$target['id'] === (int)$admin['id'];

    if ($a === 'ban' && $target && !$self) {
        q('UPDATE users SET is_banned = 1 - is_banned WHERE id = ?', [$uid]);
        flash_set('ok', 'User status updated.');
    } elseif ($a === 'role' && $target && !$self) {
        q('UPDATE users SET role = ? WHERE id = ?', [$target['role'] === 'admin' ? 'user' : 'admin', $uid]);
        flash_set('ok', 'User role updated.');
    } elseif ($a === 'reset' && $target && !$self) {
        $tmp = substr(bin2hex(random_bytes(6)), 0, 10);
        q('UPDATE users SET pass_hash = ? WHERE id = ?', [password_hash($tmp, PASSWORD_DEFAULT), $uid]);
        flash_set('ok', 'Temp password for ' . $target['email'] . ': ' . $tmp . '  (user ko batayein, wo account page se badal le)');
    } elseif ($a === 'deluser' && $target && !$self) {
        q('DELETE FROM users WHERE id = ?', [$uid]);
        flash_set('ok', 'User deleted successfully.');
    } elseif ($a === 'block') {
        $d = strtolower(trim((string)($_POST['domain'] ?? '')));
        $d = preg_replace('~^(https?://)?(www\.)?~', '', $d);
        $d = explode('/', $d)[0];
        if (preg_match('/^[a-z0-9.-]+\.[a-z0-9-]{2,}$/', $d)) {
            q('INSERT IGNORE INTO blocked_domains (domain, created_at) VALUES (?, ?)', [$d, now()]);
            // us domain ke maujooda links band kar do
            q('UPDATE links SET is_active = 0 WHERE long_url LIKE ? OR long_url LIKE ?', ['%://' . $d . '/%', '%://www.' . $d . '/%']);
            flash_set('ok', "$d blocked (existing links have also been disabled).");
        } else flash_set('err', 'Please enter a valid domain.');
    } elseif ($a === 'unblock') {
        q('DELETE FROM blocked_domains WHERE domain = ?', [(string)($_POST['domain'] ?? '')]);
        flash_set('ok', 'Domain unblocked.');
    } elseif ($target && $self) {
        flash_set('err', 'This action cannot be performed on your own account.');
    }
    redirect(url('admin.php?tab=' . $tab));
}

page_header('Admin', 'admin');
$tabs = ['overview' => 'Overview', 'users' => 'Users', 'links' => 'Links', 'blocked' => 'Blocked domains'];
echo '<h1>Admin</h1><div class="tabs">';
foreach ($tabs as $k => $n) echo '<a class="' . ($tab === $k ? 'cur' : '') . '" href="' . e(url('admin.php?tab=' . $k)) . '">' . e($n) . '</a>';
echo '</div>';

if ($tab === 'overview') {
    $c = [
        'Users' => q('SELECT COUNT(*) FROM users')->fetchColumn(),
        'Links' => q('SELECT COUNT(*) FROM links')->fetchColumn(),
        'Guest links' => q('SELECT COUNT(*) FROM links WHERE user_id IS NULL')->fetchColumn(),
        'Total clicks' => q('SELECT COALESCE(SUM(clicks),0) FROM links')->fetchColumn(),
        'Clicks today' => q('SELECT COUNT(*) FROM clicks WHERE clicked_at >= ?', [date('Y-m-d 00:00:00')])->fetchColumn(),
        'Blocked domains' => q('SELECT COUNT(*) FROM blocked_domains')->fetchColumn(),
    ];
    echo '<section class="stats">';
    foreach ($c as $n => $v) echo '<div class="stat"><b>' . number_format((int)$v) . '</b><span>' . e($n) . '</span></div>';
    echo '</section>';
    $top = q('SELECT l.*, u.email FROM links l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.clicks DESC LIMIT 10')->fetchAll();
    echo '<section class="card"><h2>Top 10 links</h2>';
    render_links_table($top, false);
    echo '</section>';
}

if ($tab === 'users') {
    $users = q('SELECT u.*, (SELECT COUNT(*) FROM links WHERE user_id = u.id) lc FROM users u ORDER BY u.id DESC LIMIT 200')->fetchAll();
    echo '<section class="card"><div class="tablewrap"><table class="table"><thead><tr><th>User</th><th>Role</th><th class="num">Links</th><th>Joined</th><th></th></tr></thead><tbody>';
    foreach ($users as $u) {
        $self = (int)$u['id'] === (int)$admin['id'];
        echo '<tr><td><b>' . e($u['name']) . '</b><div class="muted small">' . e($u['email']) . '</div></td>';
        echo '<td>' . e($u['role']) . ((int)$u['is_banned'] ? ' <span class="badge b-disabled">banned</span>' : '') . '</td>';
        echo '<td class="num">' . (int)$u['lc'] . '</td><td class="muted small">' . e(date('d M Y', strtotime($u['created_at']))) . '</td><td class="acts">';
        if (!$self) {
            echo '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="uid" value="' . (int)$u['id'] . '">'
                . '<button class="btn btn-sm btn-ghost" name="action" value="ban">' . ((int)$u['is_banned'] ? 'Unban' : 'Ban') . '</button> '
                . '<button class="btn btn-sm btn-ghost" name="action" value="role">' . ($u['role'] === 'admin' ? 'Make user' : 'Make admin') . '</button> '
                . '<button class="btn btn-sm btn-ghost" name="action" value="reset" onclick="return confirm(\'Temp password banega. Continue?\')">Reset pw</button> '
                . '<button class="btn btn-sm btn-danger" name="action" value="deluser" onclick="return confirm(\'User aur uske links delete honge. Pakka?\')">Delete</button></form>';
        } else echo '<span class="muted small">(aap)</span>';
        echo '</td></tr>';
    }
    echo '</tbody></table></div></section>';
}

if ($tab === 'links') {
    $links = q('SELECT l.*, u.email owner FROM links l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 100')->fetchAll();
    echo '<section class="card"><h2>Latest 100 links</h2><div class="tablewrap"><table class="table"><thead><tr><th>Short</th><th>Destination</th><th>Owner</th><th class="num">Clicks</th><th>Status</th><th></th></tr></thead><tbody>';
    foreach ($links as $l) {
        echo '<tr><td class="mono">' . e($l['code']) . '</td><td class="ellipsis" title="' . e($l['long_url']) . '">' . e(mb_strimwidth($l['long_url'], 0, 50, '…')) . '</td>';
        echo '<td class="small">' . e($l['owner'] ?? 'guest') . '</td><td class="num">' . (int)$l['clicks'] . '</td><td>' . status_badge($l) . '</td><td class="acts">';
        echo '<form method="post" action="' . e(url('actions.php')) . '" class="inline">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$l['id'] . '"><input type="hidden" name="back" value="1">'
            . '<button class="btn btn-sm btn-ghost" name="action" value="toggle">' . ((int)$l['is_active'] ? 'Disable' : 'Enable') . '</button> '
            . '<button class="btn btn-sm btn-danger" name="action" value="delete" onclick="return confirm(\'Delete?\')">Delete</button></form></td></tr>';
    }
    echo '</tbody></table></div></section>';
}

if ($tab === 'blocked') {
    $b = q('SELECT * FROM blocked_domains ORDER BY created_at DESC')->fetchAll();
    echo '<section class="card"><h2>Block a domain</h2><p class="muted">Add spam or phishing domains here. New links to these domains will be blocked and existing links will be disabled.</p>';
    echo '<form method="post" class="row-inline">' . csrf_field() . '<input type="hidden" name="action" value="block"><input class="input" name="domain" placeholder="bad-site.com" required><button class="btn" type="submit">Block</button></form></section>';
    echo '<section class="card"><h3>Blocked list</h3>';
    if (!$b) echo '<p class="muted">No blocked domains.</p>';
    foreach ($b as $r) {
        echo '<form method="post" class="between rowline">' . csrf_field() . '<input type="hidden" name="action" value="unblock"><input type="hidden" name="domain" value="' . e($r['domain']) . '"><span class="mono">' . e($r['domain']) . '</span><button class="btn btn-sm btn-ghost">Unblock</button></form>';
    }
    echo '</section>';
}
page_footer();
