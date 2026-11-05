<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $a = (string)($_POST['action'] ?? '');
    if ($a === 'profile') {
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 60);
        if ($name === '') flash_set('err', 'Name is required.');
        else { q('UPDATE users SET name = ? WHERE id = ?', [$name, $user['id']]); flash_set('ok', 'Profile updated successfully.'); }
    } elseif ($a === 'password') {
        $new = (string)($_POST['new'] ?? '');
        if (!rate_limit('chpw:' . $user['id'], 5, 900)) flash_set('err', 'Please try again later.');
        elseif (!password_verify((string)($_POST['old'] ?? ''), $user['pass_hash'])) flash_set('err', 'The current password is incorrect.');
        elseif (strlen($new) < 8) flash_set('err', 'The new password must be at least 8 characters long.');
        else {
            q('UPDATE users SET pass_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            flash_set('ok', 'Password updated successfully.');
        }
    } elseif ($a === 'apikey') {
        q('UPDATE users SET api_key = ? WHERE id = ?', [bin2hex(random_bytes(20)), $user['id']]);
        flash_set('ok', 'A new API key has been generated. The previous key is no longer valid.');
    } elseif ($a === 'delete') {
        if (!password_verify((string)($_POST['pw'] ?? ''), $user['pass_hash'])) {
            flash_set('err', 'Incorrect password.');
        } elseif ($user['role'] === 'admin' && (int)q("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() < 2) {
            flash_set('err', 'The last administrator account cannot be deleted.');
        } else {
            q('DELETE FROM users WHERE id = ?', [$user['id']]);
            $_SESSION = []; session_destroy();
            start_session(); flash_set('ok', 'Account deleted successfully.');
            redirect(url(''));
        }
    }
    redirect(url('account.php'));
}

$base = base_url();
page_header('Account', 'account');
?>
<h1>Account</h1>
<section class="grid2">
  <div class="card">
    <h2>Profile</h2>
    <p class="muted">Email: <b><?= e($user['email']) ?></b></p>
    <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <label>Name<input class="input" name="name" value="<?= e($user['name']) ?>" maxlength="60" required></label>
      <div><button class="btn" type="submit">Save</button></div></form>
  </div>
  <div class="card">
    <h2>Change password</h2>
    <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <label>Current password<input class="input" type="password" name="old" required autocomplete="current-password"></label>
      <label>New password<input class="input" type="password" name="new" required minlength="8" autocomplete="new-password"></label>
      <div><button class="btn" type="submit">Update</button></div></form>
  </div>
</section>

<section class="card">
  <h2>API</h2>
  <p class="muted">Use this key to create links from your apps and scripts. Never share it with anyone.</p>
  <input class="input mono" readonly value="<?= e($user['api_key']) ?>" onclick="this.select()">
  <pre class="code">curl -X POST "<?= e($base) ?>/api.php?action=shorten" \
  -H "X-API-Key: <?= e($user['api_key']) ?>" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com/bahut/lamba/link","alias":"mera-link"}'</pre>
  <p class="muted small">Endpoints: <code>shorten</code> (POST), <code>stats&amp;code=xyz</code> (GET), <code>list&amp;page=1</code> (GET).</p>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="apikey">
    <button class="btn btn-ghost" type="submit" onclick="return confirm('Your previous key will stop working. Continue?')">Regenerate key</button></form>
</section>

<section class="card danger-zone">
  <h2>Delete account</h2>
  <p class="muted">All of your links and analytics will be permanently deleted.</p>
  <form method="post" class="row-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete">
    <input class="input" type="password" name="pw" placeholder="Confirm password" required>
    <button class="btn btn-danger" type="submit" onclick="return confirm('Pakka delete karna hai?')">Delete account</button></form>
</section>
<?php page_footer();
