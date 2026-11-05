<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

if (current_user()) redirect(url('dashboard.php'));
$err = '';
$v = ['name' => '', 'email' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $v['name'] = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 60);
    $v['email'] = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    if (!empty($_POST['website'])) {                 // honeypot (bots bharte hain)
        redirect(url(''));
    } elseif (!rate_limit('register:' . client_ip(), 5, 3600)) {
        $err = 'Too many sign-up attempts from this IP. Please try again later.';
    } elseif ($v['name'] === '') {
        $err = 'Please enter your name.';
    } elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 190) {
        $err = 'Please enter a valid email address.';
    } elseif (strlen($pass) < 8) {
        $err = 'Password must be at least 8 characters long.';
    } elseif ($pass !== (string)($_POST['password2'] ?? '')) {
        $err = 'The passwords do not match.';
    } elseif (q('SELECT 1 FROM users WHERE email = ?', [$v['email']])->fetch()) {
        $err = 'An account with this email address already exists.';
    } else {
        q('INSERT INTO users (name, email, pass_hash, role, api_key, created_at) VALUES (?,?,?,?,?,?)',
            [$v['name'], $v['email'], password_hash($pass, PASSWORD_DEFAULT), 'user', bin2hex(random_bytes(20)), now()]);
        $u = q('SELECT * FROM users WHERE id = ?', [db()->lastInsertId()])->fetch();
        login_user($u);
        flash_set('ok', 'Your account has been created and your guest links have been transferred.');
        redirect(url('dashboard.php'));
    }
}
page_header('Sign up', 'login');
?>
<section class="card narrow">
  <h1>Create your free account</h1>
  <?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
    <label>Name<input class="input" name="name" value="<?= e($v['name']) ?>" required maxlength="60"></label>
    <label>Email<input class="input" type="email" name="email" value="<?= e($v['email']) ?>" required></label>
    <label>Password <small>(min 8)</small><input class="input" type="password" name="password" required minlength="8" autocomplete="new-password"></label>
    <label>Confirm password<input class="input" type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
    <button class="btn big" type="submit">Create account</button>
  </form>
  <p class="muted small">Already have an account? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
</section>
<?php page_footer();
