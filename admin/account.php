<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
$admin = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $row = q('SELECT * FROM admins WHERE id = ?', [$admin['id']])->fetch();
    $current  = (string)($_POST['current'] ?? '');
    $username = trim((string)($_POST['username'] ?? ''));
    $new1 = (string)($_POST['new1'] ?? '');
    $new2 = (string)($_POST['new2'] ?? '');

    if (!password_verify($current, $row['password_hash'])) {
        flash('Your current password is not correct.', 'err');
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $username)) {
        flash('Username: 3–60 letters or numbers.', 'err');
    } elseif ($new1 !== '' && strlen($new1) < 8) {
        flash('The new password must be at least 8 characters.', 'err');
    } elseif ($new1 !== $new2) {
        flash('The two new passwords do not match.', 'err');
    } else {
        q('UPDATE admins SET username = ? WHERE id = ?', [$username, $admin['id']]);
        if ($new1 !== '') {
            q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($new1, PASSWORD_DEFAULT), $admin['id']]);
            session_regenerate_id(true);
        }
        flash('Account updated.');
    }
    redirect('account.php');
}

admin_header('My Account', 'account.php');
?>
<form class="panel form narrow" method="post" autocomplete="off">
  <?= csrf_field() ?>
  <label>Username<input name="username" value="<?= h($admin['username']) ?>" required></label>
  <label>New password <small class="muted">(leave empty to keep the current one)</small><input type="password" name="new1" minlength="8" autocomplete="new-password"></label>
  <label>Repeat new password<input type="password" name="new2" autocomplete="new-password"></label>
  <hr>
  <label>Current password* <small class="muted">needed to save changes</small><input type="password" name="current" required autocomplete="current-password"></label>
  <div class="form-actions"><button class="btn" type="submit">Save</button></div>
</form>
<?php admin_footer();
