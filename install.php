<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$lock = __DIR__ . '/includes/installed.lock';
if (is_file($lock)) {
    http_response_code(403);
    exit('Already installed. For security, delete install.php from your server.');
}

$schema = [
"CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  email VARCHAR(190) NOT NULL,
  pass_hash VARCHAR(255) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  is_banned TINYINT(1) NOT NULL DEFAULT 0,
  api_key CHAR(40) NOT NULL,
  created_at DATETIME NOT NULL,
  last_login DATETIME NULL,
  UNIQUE KEY uq_email (email),
  UNIQUE KEY uq_api (api_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS links (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  guest_token CHAR(32) NULL,
  code VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  long_url VARCHAR(2048) NOT NULL,
  title VARCHAR(150) NULL,
  pass_hash VARCHAR(255) NULL,
  expires_at DATETIME NULL,
  max_clicks INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  clicks INT UNSIGNED NOT NULL DEFAULT 0,
  last_click_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  created_ip CHAR(64) NULL,
  UNIQUE KEY uq_code (code),
  KEY idx_user (user_id, id),
  KEY idx_guest (guest_token),
  CONSTRAINT fk_link_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS clicks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  link_id INT UNSIGNED NOT NULL,
  clicked_at DATETIME NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  device VARCHAR(20) NOT NULL,
  browser VARCHAR(20) NOT NULL,
  os VARCHAR(20) NOT NULL,
  referrer VARCHAR(100) NOT NULL,
  is_unique TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_link_time (link_id, clicked_at),
  KEY idx_link_ip (link_id, ip_hash),
  CONSTRAINT fk_click_link FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS rate_limits (
  k CHAR(40) PRIMARY KEY,
  window_start INT UNSIGNED NOT NULL,
  hits INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS blocked_domains (
  domain VARCHAR(190) PRIMARY KEY,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$err = '';
$done = false;
$v = ['name' => '', 'email' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $v['name'] = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 60);
    $v['email'] = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    if ($v['name'] === '' || !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $err = 'Please enter your name and a valid email address.';
    elseif (strlen($pass) < 8) $err = 'Password must be at least 8 characters long.';
    else {
        try {
            foreach ($schema as $sql) db()->exec($sql);
            if (!is_file(__DIR__ . '/includes/secret.key')) {
                file_put_contents(__DIR__ . '/includes/secret.key', bin2hex(random_bytes(32)));
            }
            if (!q('SELECT 1 FROM users WHERE email = ?', [$v['email']])->fetch()) {
                q('INSERT INTO users (name, email, pass_hash, role, api_key, created_at) VALUES (?,?,?,?,?,?)',
                    [$v['name'], $v['email'], password_hash($pass, PASSWORD_DEFAULT), 'admin', bin2hex(random_bytes(20)), now()]);
            }
            @file_put_contents($lock, now());
            $done = true;
        } catch (Throwable $ex) {
            error_log('install: ' . $ex->getMessage());
            $err = 'Installation failed: ' . (APP_DEBUG ? $ex->getMessage() : 'Check your database configuration and permissions.');
        }
    }
}
page_header('Install');
?>
<section class="card narrow">
<?php if ($done): ?>
  <h1>✅ Installation complete</h1>
  <p>Ab <b>install.php file server se delete kar dein</b> (File Manager me).</p>
  <p><a class="btn" href="<?= e(url('login.php')) ?>">Admin login</a></p>
<?php else: ?>
  <h1>Setup</h1>
  <p class="muted">Database tables ban jayenge aur pehla admin account create hoga.</p>
  <?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Admin naam<input class="input" name="name" value="<?= e($v['name']) ?>" required></label>
    <label>Admin email<input class="input" type="email" name="email" value="<?= e($v['email']) ?>" required></label>
    <label>Admin password <small>(min 8)</small><input class="input" type="password" name="password" minlength="8" required autocomplete="new-password"></label>
    <button class="btn big" type="submit">Install</button>
  </form>
<?php endif; ?>
</section>
<?php page_footer();
