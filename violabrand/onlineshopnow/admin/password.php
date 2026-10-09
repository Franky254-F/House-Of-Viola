<?php
require __DIR__ . '/_init.php'; require_admin();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = db()->prepare('SELECT * FROM admins WHERE id = ?'); $st->execute([$_SESSION['admin_id']]); $a = $st->fetch();
    $user = trim($_POST['username'] ?? ''); $new = (string)($_POST['new_password'] ?? ''); $new2 = (string)($_POST['new_password2'] ?? '');
    if (!$a || !password_verify((string)($_POST['current_password'] ?? ''), $a['password_hash'])) $errors[] = 'Your current password is not correct.';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $user)) $errors[] = 'Username must be 3-40 letters or numbers.';
    if ($new !== '' && strlen($new) < 8) $errors[] = 'The new password must be at least 8 characters.';
    if ($new !== $new2) $errors[] = 'The two new passwords do not match.';
    if (!$errors) {
        try {
            $hash = $new !== '' ? password_hash($new, PASSWORD_DEFAULT) : $a['password_hash'];
            db()->prepare('UPDATE admins SET username = ?, password_hash = ? WHERE id = ?')->execute([$user, $hash, $a['id']]);
            $_SESSION['admin_name'] = $user;
            flash('success', 'Account updated.');
            redirect('admin/password.php');
        } catch (PDOException $e) { $errors[] = 'That username is already taken.'; }
    }
}
admin_header('My account', 'account');
foreach ($errors as $er) echo '<div class="alert alert-error">' . e($er) . '</div>';
?>
<form method="post" class="box form-box" style="max-width:520px">
    <?= csrf_field() ?>
    <div class="field"><label for="username">Username</label><input id="username" name="username" value="<?= e($_POST['username'] ?? admin_user()) ?>" required></div>
    <div class="field"><label for="new_password">New password <small>(leave blank to keep the current one)</small></label><input id="new_password" type="password" name="new_password" autocomplete="new-password"></div>
    <div class="field"><label for="new_password2">Repeat new password</label><input id="new_password2" type="password" name="new_password2" autocomplete="new-password"></div>
    <div class="field"><label for="current_password">Current password <small>(needed to save any change)</small></label><input id="current_password" type="password" name="current_password" autocomplete="current-password" required></div>
    <button class="btn btn-primary">Save changes</button>
</form>
<?php admin_footer(); ?>
