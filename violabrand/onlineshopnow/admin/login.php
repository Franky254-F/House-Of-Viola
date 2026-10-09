<?php
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth_layout.php';
if (!empty($_SESSION['admin_id'])) redirect('admin/index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)');
        $st->execute([$ip]);
        if ((int)$st->fetchColumn() >= 8) {
            $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
        } else {
            $st = db()->prepare('SELECT * FROM admins WHERE username = ?');
            $st->execute([trim($_POST['username'] ?? '')]);
            $a = $st->fetch();
            if ($a && password_verify($_POST['password'] ?? '', $a['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$a['id'];
                $_SESSION['admin_name'] = $a['username'];
                db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
                redirect('admin/index.php');
            }
            db()->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
            $error = 'Wrong username or password.';
        }
    } catch (PDOException $e) {
        $error = 'The store is not installed yet. Open install.php first.';
    }
}

auth_start('Admin sign in', 'Owner sign in', 'Manage your products, orders and banner.', [
    'back' => ['index.php', 'View the store'], 'eyebrow' => 'Admin panel', 'art_title' => 'Run your shop *with ease.*',
    'perks' => ['New orders appear the moment a customer places one', 'Upload your hero banner and products in minutes', 'Everything is yours to edit'],
]);
?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <?php auth_field('username', 'Username', 'text', '', ['autocomplete' => 'username', 'required' => 'required', 'autofocus' => 'autofocus']); ?>
    <?php auth_field('password', 'Password', 'password', '', ['autocomplete' => 'current-password', 'required' => 'required']); ?>
    <button class="btn btn-gold btn-lg btn-block">Sign in</button>
</form>
<?php auth_end(); ?>
