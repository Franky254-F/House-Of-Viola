<?php
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth_layout.php';

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '', 'account.php');
if (customer()) redirect($next);

$error = ''; $ident = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ident = trim((string)($_POST['identity'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');
    $ip = 'c:' . substr($_SERVER['REMOTE_ADDR'] ?? '0', 0, 40);          // "c:" keeps customers separate from the admin lock-out counter
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 8) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        if (strpos($ident, '@') !== false) { $st = db()->prepare('SELECT * FROM customers WHERE email = ?'); $st->execute([strtolower($ident)]); }
        else { $st = db()->prepare('SELECT * FROM customers WHERE phone = ?'); $st->execute([normalize_phone($ident)]); }
        $c = $st->fetch();
        if ($c && password_verify($pass, $c['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['customer_id'] = (int)$c['id'];
            db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
            flash('success', 'Welcome back, ' . first_name($c['name']) . '.');
            redirect($next);
        }
        db()->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
        $error = 'That email, phone number or password is not right.';
    }
}

auth_start('Sign in', 'Welcome back', 'Sign in to see your orders and check out faster.', [
    'tab' => 'login', 'next' => $next, 'back' => ['index.php', 'Back to the store'], 'eyebrow' => 'Your account',
    'perks' => ['Add to cart and order in a few taps', 'See every order and its progress', 'Your name and phone saved for faster checkout'],
]);
?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" autocomplete="on" novalidate>
    <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
    <?php auth_field('identity', 'Email or phone number', 'text', $ident, ['autocomplete' => 'username', 'required' => 'required', 'autofocus' => 'autofocus']); ?>
    <?php auth_field('password', 'Password', 'password', '', ['autocomplete' => 'current-password', 'required' => 'required']); ?>
    <button class="btn btn-gold btn-lg btn-block">Sign in</button>
</form>
<p class="auth-alt">New here? <a class="lnk" href="<?= e(url('register.php?next=' . urlencode($next))) ?>">Create an account</a></p>
<div class="auth-or"><span>or</span></div>
<a class="btn btn-ghost btn-block" href="<?= e(url('index.php')) ?>">Keep browsing the store</a>
<p class="auth-alt" style="margin-top:14px">Shop owner? <a class="lnk" href="<?= e(url('admin/')) ?>">Open the owner sign in</a></p>
<?php auth_end(); ?>
