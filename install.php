<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/schema.php';

$done = false;
$error = '';
$v = ['driver' => 'mysql', 'host' => 'localhost', 'name' => '', 'user' => '', 'username' => 'admin'];

if (is_installed()) {
    $already = true;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v = array_merge($v, array_map(fn($x) => trim((string)$x), $_POST));
    $pass1 = (string)($_POST['admin_pass'] ?? '');
    $pass2 = (string)($_POST['admin_pass2'] ?? '');
    try {
        if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $v['username'])) throw new RuntimeException('Admin username: 3–60 letters or numbers.');
        if (strlen($pass1) < 8) throw new RuntimeException('Admin password must be at least 8 characters.');
        if ($pass1 !== $pass2) throw new RuntimeException('The two admin passwords do not match.');
        if (!is_writable(__DIR__)) throw new RuntimeException('The website folder is not writable, so config.php cannot be created.');

        if ($v['driver'] === 'sqlite') {
            @mkdir(__DIR__ . '/data', 0755, true);
            $cfg = ['driver' => 'sqlite', 'path' => __DIR__ . '/data/blissbites.sqlite'];
        } else {
            if ($v['name'] === '' || $v['user'] === '') throw new RuntimeException('Please fill in the database name and user.');
            $cfg = ['driver' => 'mysql', 'host' => $v['host'] ?: 'localhost', 'port' => 3306,
                    'name' => $v['name'], 'user' => $v['user'], 'pass' => (string)($_POST['pass'] ?? '')];
        }

        try { $pdo = make_pdo($cfg); }
        catch (PDOException $e) { throw new RuntimeException('Could not connect to the database. Check the details. (' . $e->getMessage() . ')'); }

        create_schema($pdo, $cfg['driver']);
        seed_content($pdo);
        $pdo->prepare('DELETE FROM admins')->execute();
        $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')
            ->execute([$v['username'], password_hash($pass1, PASSWORD_DEFAULT)]);

        @mkdir(__DIR__ . '/uploads', 0755, true);
        $php = "<?php\n// Created by install.php – keep this file private.\nreturn " . var_export($cfg, true) . ";\n";
        if (file_put_contents(__DIR__ . '/config.php', $php) === false) throw new RuntimeException('Could not write config.php.');
        @chmod(__DIR__ . '/config.php', 0640);
        $done = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title>Set up Bliss Bites</title><link rel="stylesheet" href="admin/admin.css"></head>
<body class="auth-page">
<div class="auth-card wide">
  <div class="auth-logo">Bliss Bites<small>Website setup</small></div>

<?php if (!empty($already)): ?>
  <p>This website is already set up. <a href="admin/">Go to the admin panel</a>.</p>
  <p class="muted">For safety you can delete <code>install.php</code> from your hosting.</p>

<?php elseif ($done): ?>
  <div class="alert ok">All done! Your website and admin panel are ready.</div>
  <p><a class="btn" href="admin/">Open the admin panel →</a></p>
  <p class="muted">For safety, delete <code>install.php</code> from your hosting now (File Manager → public_html).</p>

<?php else: ?>
  <?php if ($error): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <h3>1. Database</h3>
    <p class="muted">On Hostinger: hPanel → Databases → MySQL Databases. Create a database and user there, then copy the details here.</p>
    <label class="radio"><input type="radio" name="driver" value="mysql" <?= $v['driver'] !== 'sqlite' ? 'checked' : '' ?>> MySQL (Hostinger)</label>
    <label class="radio"><input type="radio" name="driver" value="sqlite" <?= $v['driver'] === 'sqlite' ? 'checked' : '' ?>> Simple file database (for testing only)</label>
    <div class="grid2">
      <label>Database host<input name="host" value="<?= h($v['host']) ?>"></label>
      <label>Database name<input name="name" value="<?= h($v['name']) ?>" placeholder="u123456789_bliss"></label>
      <label>Database user<input name="user" value="<?= h($v['user']) ?>" placeholder="u123456789_admin"></label>
      <label>Database password<input type="password" name="pass"></label>
    </div>

    <h3>2. Your admin login</h3>
    <div class="grid2">
      <label>Username<input name="username" value="<?= h($v['username']) ?>" required></label>
      <span></span>
      <label>Password (min 8 characters)<input type="password" name="admin_pass" required minlength="8"></label>
      <label>Repeat password<input type="password" name="admin_pass2" required minlength="8"></label>
    </div>
    <button class="btn" type="submit">Set up website</button>
  </form>
<?php endif; ?>
</div>
</body></html>
