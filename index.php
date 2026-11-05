<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = current_user();
$gt = guest_token();
$recent = [];
if ($user) {
    $recent = q('SELECT * FROM links WHERE user_id = ? ORDER BY id DESC LIMIT 5', [$user['id']])->fetchAll();
} elseif ($gt) {
    $recent = q('SELECT * FROM links WHERE guest_token = ? AND user_id IS NULL ORDER BY id DESC LIMIT 5', [$gt])->fetchAll();
}

page_header('Free URL Shortener', 'home');
?>
<section class="hero hero-premium">
  <div class="hero-copy">
    <div class="eyebrow"><span class="eyebrow-dot"></span> SIMPLE. FAST. FREE.</div>
    <h1>Long links.<br><span class="accent">Shorter journeys.</span></h1>
    <p class="lead">Turn long URLs into clean, shareable links in seconds. No sign-up required to get started.</p>
    <div class="hero-points"><span>✓ Free to use</span><span>✓ No registration</span><span>✓ 30-day guest links</span></div>
  </div>
  <div class="hero-art" aria-hidden="true">
    <div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
    <div class="art-link-card"><span class="art-icon">↗</span><div><b>One simple link</b><small>Ready to share anywhere</small></div><span class="art-check">✓</span></div>
    <div class="art-mini">urlio.rf.gd/<strong>Ab12Xy</strong></div>
    <div class="art-spark spark-one">✦</div><div class="art-spark spark-two">✧</div>
  </div>
</section>

<section class="card shorten-card">
  <div class="shorten-heading"><div><span class="section-kicker">LINK SHORTENER</span><h2>Make every character count.</h2></div><span class="secure-note">⌁ Secure &amp; reliable</span></div>
  <?php render_shorten_form($user); ?>
</section>

<?php if ($recent): ?>
<section class="card">
  <div class="between"><h2><?= $user ? 'Your recent links' : 'Links from this browser' ?></h2>
    <?php if ($user): ?><a href="<?= e(url('dashboard.php')) ?>">View all links →</a><?php endif; ?></div>
  <?php render_links_table($recent, false); ?>
</section>
<?php endif; ?>

<section class="section-intro"><span class="section-kicker">BUILT FOR EVERYDAY SHARING</span><h2>Everything you need. Nothing you don't.</h2><p class="muted">A simple link management experience that gets out of your way.</p></section>
<section class="features">
  <div class="card feature-card"><div class="feature-icon">↗</div><h3>Instant short links</h3><p class="muted">Create a clean, shareable URL in seconds. Guest shortening works without an account.</p></div>
  <div class="card feature-card"><div class="feature-icon">▥</div><h3>Useful analytics</h3><p class="muted">Understand link performance with click counts, visitor insights and activity history.</p></div>
  <div class="card feature-card"><div class="feature-icon">⌑</div><h3>More control</h3><p class="muted">Registered users can manage custom aliases, password protection, expiry and QR codes.</p></div>
</section>
<section class="trust-strip"><div><b>10 links / hour</b><span>Guest creation limit per IP</span></div><div><b>30 days</b><span>Guest link validity</span></div><div><b>Always free</b><span>No account needed to start</span></div></section>
<?php page_footer();
