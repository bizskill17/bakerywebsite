<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require dirname(__DIR__) . '/inc/instagram.php';
require_login();

if (setting('cron_key') === '') { save_setting('cron_key', bin2hex(random_bytes(16))); settings(true); }

function report_sync(array $r): void {
    if (isset($r['error']))       flash($r['error'], 'err');
    elseif (isset($r['skipped'])) flash('Sync skipped: ' . $r['skipped'] . '.', 'warn');
    else flash("Synced with Instagram: {$r['photos']} photo(s) and {$r['reels']} reel(s) on your website"
             . ($r['added'] ? ", {$r['added']} new" : '') . ($r['removed'] ? ", {$r['removed']} removed" : '') . '.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    switch ($_POST['action'] ?? '') {
        case 'save':
            $tok = preg_replace('/\s+/', '', (string)($_POST['token'] ?? ''));
            if ($tok !== '') {
                save_setting('instagram_token', $tok);
                save_setting('instagram_token_refreshed_at', (string)time());
                save_setting('instagram_token_expires_at', '');
            }
            save_setting('instagram_sync', isset($_POST['enabled']) ? '1' : '0');
            settings(true);
            if (isset($_POST['enabled']) && ig_token() !== '') { report_sync(ig_sync(0)); }
            else flash(isset($_POST['enabled']) ? 'Saved. Paste your access token to start syncing.' : 'Instagram sync switched off – your website shows the photos from Gallery and Baking Stories.');
            break;
        case 'sync':
            report_sync(ig_sync(0));
            break;
        case 'remove_token':
            foreach (['instagram_token', 'instagram_token_refreshed_at', 'instagram_token_expires_at'] as $k) save_setting($k, '');
            save_setting('instagram_sync', '0');
            flash('Access token removed and sync switched off.');
            break;
    }
    redirect('instagram.php');
}

$s       = settings(true);
$token   = ig_token();
$enabled = ($s['instagram_sync'] ?? '') === '1';
$counts  = ig_counts();
$items   = q('SELECT * FROM instagram_media ORDER BY sort_order')->fetchAll();
$when    = fn($ts) => $ts ? date('d M Y, g:i a', (int)$ts) : '—';
$cronCmd = '/usr/bin/php ' . ROOT . '/cron/instagram-sync.php';
$https   = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$cronUrl = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'yourdomain.com') . '/cron/instagram-sync.php?key=' . $s['cron_key'];

admin_header('Instagram Sync', 'instagram.php');
?>
<div class="stats">
  <div class="stat <?= $enabled && $token ? 'hot' : '' ?>"><b><?= $enabled && $token ? 'ON' : 'OFF' ?></b><span>Instagram sync</span></div>
  <div class="stat"><b><?= $counts['photos'] ?></b><span>Photos synced</span></div>
  <div class="stat"><b><?= $counts['reels'] ?></b><span>Reels synced</span></div>
  <div class="stat"><b style="font-size:1.1rem;line-height:2"><?= $when($s['instagram_last_ok'] ?? '') ?></b><span>Last successful sync</span></div>
</div>

<?php if (!empty($s['instagram_last_error'])): ?>
  <?php $partial = ($s['instagram_last_ok'] ?? '') !== '' && ($s['instagram_last_ok'] ?? '') === ($s['instagram_last_sync'] ?? ''); ?>
  <div class="alert <?= $partial ? 'warn' : 'err' ?>"><b><?= $partial ? 'Note:' : 'Last sync problem:' ?></b> <?= h($s['instagram_last_error']) ?></div>
<?php endif; ?>

<section class="panel">
  <div class="panel-head">
    <h2>Settings</h2>
    <?php if ($token): ?>
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="sync"><button class="btn" type="submit">⟳ Sync now</button></form>
    <?php endif; ?>
  </div>
  <form method="post" class="form" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <label class="check"><input type="checkbox" name="enabled" <?= $enabled ? 'checked' : '' ?>> Show my latest Instagram posts on the website (checks every 5 minutes)</label>
    <label>Instagram access token
      <small class="muted"><?= $token ? 'Saved (ends in …' . h(substr($token, -6)) . '). Paste a new one only to replace it.' : 'Paste the long-lived token from your Meta app (see “How to get your token” below).' ?></small>
      <textarea name="token" rows="2" placeholder="<?= $token ? 'Leave empty to keep the saved token' : 'IGAA…' ?>"></textarea>
    </label>
    <p class="muted" style="margin:-6px 0 14px">
      Token last renewed: <?= $when($s['instagram_token_refreshed_at'] ?? '') ?>
      <?php if (!empty($s['instagram_token_expires_at'])): ?> · valid until <?= h(date('d M Y', (int)$s['instagram_token_expires_at'])) ?><?php endif; ?>
      · renewed automatically every week.
    </p>
    <div class="form-actions">
      <button class="btn" type="submit">Save</button>
    </div>
  </form>
  <?php if ($token): ?>
    <form method="post" data-confirm="Remove the Instagram token and switch sync off?" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="remove_token"><button class="btn ghost sm danger" type="submit">Remove token</button></form>
  <?php endif; ?>
</section>

<?php if ($items): ?>
<section class="panel">
  <h2>On your website now</h2>
  <p class="muted">Photos fill the Instagram Gallery (first 5 shown); reels fill Watch Our Baking Stories (first 10). Delete or archive a post on Instagram and it disappears here at the next sync.</p>
  <div class="photo-grid">
    <?php foreach ($items as $it): ?>
      <a class="photo-card" href="<?= h($it['permalink'] ?: '#') ?>" target="_blank" rel="noopener" style="text-decoration:none;color:inherit">
        <img src="<?= img($it['image']) ?>" alt="">
        <span class="status <?= $it['kind'] === 'reel' ? 's-confirmed' : 's-delivered' ?>"><?= $it['kind'] === 'reel' ? 'Reel → Baking Stories' : 'Photo → Gallery' ?></span>
        <small class="muted"><?= h(mb_strimwidth((string)$it['caption'], 0, 80, '…')) ?></small>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="panel">
  <h2>Keep it updating every 5 minutes (recommended)</h2>
  <p class="muted">Your website already syncs when people visit it. To keep it fresh even when nobody visits, add a Cron Job in Hostinger: <b>hPanel → Advanced → Cron Jobs</b> → choose <b>Custom</b>, paste this command and set it to run <b>every 5 minutes</b> (*/5 * * * *):</p>
  <pre class="code"><?= h($cronCmd) ?></pre>
  <p class="muted">Alternative (any online cron service): open this private link every 5 minutes. Don't share it.</p>
  <pre class="code"><?= h($cronUrl) ?></pre>
</section>

<section class="panel">
  <h2>How to get your token (one time, ~15 minutes)</h2>
  <ol class="howto">
    <li>In the Instagram app: <b>Settings → Account type and tools → Switch to professional account</b> (Business or Creator). It's free.</li>
    <li>Go to <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">developers.facebook.com/apps</a> → <b>Create app</b> → use case <b>“Manage messaging &amp; content on Instagram”</b>.</li>
    <li>In the app: <b>Instagram → API setup with Instagram login</b> → <b>Generate access tokens</b> → <b>Add account</b> and log in with your bakery's Instagram.</li>
    <li>Click <b>Generate token</b> next to your account, copy it and paste it above. Tick the box and click <b>Save</b>.</li>
  </ol>
  <p class="muted">Your Meta app can stay in Development mode – it only needs to read your own account.</p>
</section>
<?php admin_footer();
