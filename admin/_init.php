<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

const SESSION_IDLE_LIMIT = 8 * 3600; // log out after 8 hours of no activity

function current_admin(): ?array {
    if (empty($_SESSION['admin_id'])) return null;
    if (time() - ($_SESSION['admin_seen'] ?? 0) > SESSION_IDLE_LIMIT) { unset($_SESSION['admin_id']); return null; }
    $_SESSION['admin_seen'] = time();
    $a = q('SELECT id, username FROM admins WHERE id = ?', [$_SESSION['admin_id']])->fetch();
    return $a ?: null;
}

function require_login(): array {
    $a = current_admin();
    if (!$a) redirect('login.php');
    return $a;
}

/** Shows the image path from inside /admin (paths are stored relative to the site root). */
function img(string $rel): string { return h('../' . ltrim($rel, '/')); }

/** "Pankaj Jain" -> "PJ", "nutan" -> "NU" */
function initials(string $name): string {
    $parts = preg_split('/[\s._-]+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) return '?';
    $ini = count($parts) > 1 ? mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1) : mb_substr($parts[0], 0, 2);
    return mb_strtoupper($ini);
}

function new_enquiry_count(): int {
    return (int)q("SELECT COUNT(*) FROM enquiries WHERE status = 'new'")->fetchColumn();
}

function admin_header(string $title, string $active): void {
    $admin = current_admin();
    $newCount = new_enquiry_count();
    $nav = [
        'index.php'     => ['Dashboard', 'M3 12l9-8 9 8M5 10v10h5v-6h4v6h5V10'],
        'enquiries.php' => ['Enquiries', 'M4 5h16v11H8l-4 4zM8 9h8M8 12h5'],
        'menu.php'      => ['Menu Items', 'M5 12h14l-2 8H7zM4.5 12a3 3 0 0 1 2-4.6A5.5 5.5 0 0 1 17.5 7.4a3 3 0 0 1 2 4.6'],
        'gallery.php'   => ['Gallery', 'M4 5h16v14H4zM4 15l5-5 4 4 3-3 4 4M15 9.5a1.5 1.5 0 1 0 0-.1'],
        'stories.php'   => ['Baking Stories', 'M3 9h18v11H3zM10 12.5v5l4.2-2.5zM3 9l-.6-3.2 16.7-3 .6 3.2'],
        'instagram.php' => ['Instagram Sync', 'M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4zM12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM17.5 6.5h.01'],
        'settings.php'  => ['Website Content', 'M4 6h16M4 12h16M4 18h10'],
        'account.php'   => ['My Account', 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0'],
    ];
    ?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title><?= h($title) ?> · Bliss Bites Admin</title>
<link rel="icon" href="../images/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="admin.css?v=5"></head>
<body>
<?php readfile(dirname(__DIR__) . '/inc/icons.svg'); ?>
<header class="topbar">
  <button class="nav-toggle" aria-label="Menu" onclick="document.body.classList.toggle('nav-open')">☰</button>
  <div class="topbar-right">
    <a class="btn ghost sm" href="../" target="_blank">View website ↗</a>
  </div>
</header>
<div class="layout">
  <nav class="sidenav">
    <a class="side-logo" href="index.php" aria-label="Bliss Bites – dashboard">
      <span class="a-logo-art"><svg class="spark s1"><use href="#i-sparkle"/></svg><svg class="spark s2"><use href="#i-sparkle"/></svg><svg class="spark s3"><use href="#i-sparkle"/></svg><svg class="muffin"><use href="#i-muffin"/></svg></span>
      <span class="a-logo-word">Bliss Bites</span>
      <span class="a-logo-by">by Nutan Jain</span>
    </a>
    <?php foreach ($nav as $file => [$label, $path]): ?>
      <a href="<?= $file ?>" class="<?= $active === $file ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="<?= $path ?>" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"/></svg>
        <?= h($label) ?>
        <?php if ($file === 'enquiries.php' && $newCount): ?><span class="pill"><?= $newCount ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
    <div class="user-box">
      <div class="user-row">
        <span class="avatar" aria-hidden="true"><?= h(initials($admin['username'] ?? '')) ?></span>
        <span class="user-meta"><b><?= h($admin['username'] ?? '') ?></b><small>Administrator</small></span>
      </div>
      <a class="btn ghost sm logout" href="logout.php">
        <svg viewBox="0 0 24 24"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 8l-4 4 4 4M6 12h10" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Log out
      </a>
    </div>
  </nav>
  <main class="content">
    <h1><?= h($title) ?></h1>
    <?php foreach (flash() as [$type, $msg]): ?><div class="alert <?= h($type) ?>"><?= h($msg) ?></div><?php endforeach; ?>
<?php
}

function admin_footer(): void {
    ?>
  </main>
</div>
<script>
// Confirm before destructive actions
document.addEventListener('submit', e => {
  const m = e.target.dataset.confirm;
  if (m && !confirm(m)) e.preventDefault();
});
// Live preview of chosen image
document.addEventListener('change', e => {
  const inp = e.target;
  if (inp.type !== 'file' || !inp.dataset.preview || !inp.files[0]) return;
  const img = document.getElementById(inp.dataset.preview);
  if (img) { img.src = URL.createObjectURL(inp.files[0]); img.hidden = false; }
});
</script>
</body></html>
<?php
}

/** Small POST form button, e.g. delete / move */
function action_button(string $action, int $id, string $label, string $class = 'ghost sm', string $confirm = '', array $extra = []): string {
    $html = '<form method="post" class="inline"' . ($confirm ? ' data-confirm="' . h($confirm) . '"' : '') . '>' . csrf_field()
          . '<input type="hidden" name="action" value="' . h($action) . '"><input type="hidden" name="id" value="' . $id . '">';
    foreach ($extra as $k => $v) $html .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    return $html . '<button class="btn ' . h($class) . '" type="submit" title="' . h($label) . '">' . $label . '</button></form>';
}
