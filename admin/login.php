<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

if (current_admin()) redirect('index.php');

const MAX_ATTEMPTS = 5;
const LOCK_SECONDS = 15 * 60;

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ip = client_ip();
    q('DELETE FROM login_attempts WHERE attempted_at < ?', [time() - LOCK_SECONDS]);
    $fails = (int)q('SELECT COUNT(*) FROM login_attempts WHERE ip = ?', [$ip])->fetchColumn();

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($fails >= MAX_ATTEMPTS) {
        $error = 'Too many wrong attempts. Please wait 15 minutes and try again.';
    } else {
        $a = q('SELECT * FROM admins WHERE username = ?', [$username])->fetch();
        if ($a && password_verify($password, $a['password_hash'])) {
            q('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
            if (password_needs_rehash($a['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $a['id']]);
            }
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$a['id'];
            $_SESSION['admin_seen'] = time();
            redirect('index.php');
        }
        q('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, ?)', [$ip, time()]);
        usleep(400000);
        $left = MAX_ATTEMPTS - $fails - 1;
        $error = 'Wrong username or password.' . ($left > 0 && $left <= 2 ? " $left attempt(s) left." : '');
    }
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title>Log in · Bliss Bites Admin</title>
<link rel="icon" href="../images/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="admin.css?v=6"><style>.password-wrap{position:relative;display:block}.password-wrap input{width:100%;box-sizing:border-box;padding-right:48px}.password-toggle{position:absolute;right:5px;top:50%;transform:translateY(-50%);width:38px;height:34px;display:grid;place-items:center;background:transparent;border:0;color:#c2185b;cursor:pointer;border-radius:6px;padding:0}.password-toggle svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}.password-toggle [hidden]{display:none!important}.password-toggle:focus-visible{outline:2px solid #c2185b}</style></head>
<body class="auth-page">
  <form class="auth-card" method="post">
    <div class="auth-logo">Bliss Bites<small>Admin panel</small></div>
    <?php foreach (flash() as [$t, $m]): ?><div class="alert <?= h($t) ?>"><?= h($m) ?></div><?php endforeach; ?>
    <?php if ($error): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>Username<input name="username" value="<?= h($username) ?>" required autofocus autocomplete="username"></label>
    <label for="admin-password">Password</label><div class="password-wrap"><input id="admin-password" type="password" name="password" required autocomplete="current-password"><button type="button" class="password-toggle" id="password-toggle" aria-label="Show password" aria-pressed="false" title="Show password"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/><path id="eye-slash" d="M3 3l18 18" hidden/></svg></button></div>
    <button class="btn block" type="submit">Log in</button>
    <p class="muted center"><a href="../">← Back to website</a></p>
  </form>
<script>const password=document.getElementById('admin-password'),toggle=document.getElementById('password-toggle');toggle.addEventListener('click',()=>{const visible=password.type==='password';password.type=visible?'text':'password';const label=visible?'Hide password':'Show password';toggle.setAttribute('aria-label',label);toggle.setAttribute('aria-pressed',String(visible));toggle.title=label;document.getElementById('eye-slash').toggleAttribute('hidden',!visible)});</script></body></html>
