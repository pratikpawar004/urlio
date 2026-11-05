<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

if (current_user()) redirect(url('dashboard.php'));
$err = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    $ip = client_ip();
    if (!rate_limit('login-ip:' . $ip, 15, 900) || !rate_limit('login-em:' . $email, 6, 900)) {
        $err = 'Too many attempts. Please try again in 15 minutes.';
    } else {
        $u = q('SELECT * FROM users WHERE email = ?', [$email])->fetch();
        // user na mile tab bhi hash verify karte hain (timing se email guess na ho)
        $hash = $u['pass_hash'] ?? '$2y$10$usesomesillystringforsaltandhashsomethingabcdefghijklmnopq';
        if (password_verify($pass, $hash) && $u) {
            if ((int)$u['is_banned']) {
                $err = 'This account has been suspended.';
            } else {
                if (password_needs_rehash($u['pass_hash'], PASSWORD_DEFAULT)) {
                    q('UPDATE users SET pass_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
                }
                login_user($u);
                redirect(safe_next($_GET['next'] ?? null));
            }
        } else {
            $err = 'The email or password is incorrect.';
        }
    }
}
page_header('Log in', 'login');
?>
<section class="card narrow">
  <h1>Log in</h1>
  <?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Email<input class="input" type="email" name="email" value="<?= e($email) ?>" required autofocus></label>
    <label>Password<input class="input" type="password" name="password" required></label>
    <button class="btn big" type="submit">Log in</button>
  </form>
  <p class="muted small">New to URLIO? <a href="<?= e(url('register.php')) ?>">Sign up</a>. Forgot your password? Contact the site administrator for assistance.</p>
</section>
<?php page_footer();
