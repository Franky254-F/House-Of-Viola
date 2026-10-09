<?php
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth_layout.php';

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '', 'account.php');
if (customer()) redirect($next);

$errors = []; $v = ['name' => '', 'phone' => '', 'email' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($v as $k => $_) $v[$k] = trim((string)($_POST[$k] ?? ''));
    $pass = (string)($_POST['password'] ?? ''); $pass2 = (string)($_POST['password2'] ?? '');
    $phone = normalize_phone($v['phone']);
    $email = strtolower($v['email']);

    // Gentle limit on sign-ups from one address
    $ip = 'r:' . substr($_SERVER['REMOTE_ADDR'] ?? '0', 0, 40);
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 10) $errors[] = 'Too many sign-up attempts. Please wait a few minutes.';

    if (mb_strlen($v['name']) < 2 || mb_strlen($v['name']) > 80) $errors[] = 'Please enter your name.';
    if (strlen($phone) < 10 || strlen($phone) > 15)              $errors[] = 'Please enter a valid phone number, for example 0712 345 678.';
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120)) $errors[] = 'That email address does not look right (or leave it blank).';
    if (strlen($pass) < 8)  $errors[] = 'Choose a password of at least 8 characters.';
    if ($pass !== $pass2)   $errors[] = 'The two passwords do not match.';

    if (!$errors) {
        db()->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
        $dup = db()->prepare('SELECT phone, email FROM customers WHERE phone = ? OR (? <> \'\' AND email = ?)');
        $dup->execute([$phone, $email, $email]);
        if ($row = $dup->fetch()) $errors[] = ($row['phone'] === $phone) ? 'An account with that phone number already exists. Try signing in.' : 'An account with that email already exists. Try signing in.';
    }
    if (!$errors) {
        try {
            db()->prepare('INSERT INTO customers (name, email, phone, password_hash) VALUES (?,?,?,?)')
                ->execute([$v['name'], $email !== '' ? $email : null, $phone, password_hash($pass, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['customer_id'] = (int)db()->lastInsertId();
            flash('success', 'Welcome, ' . first_name($v['name']) . '. Your account is ready.');
            redirect($next);
        } catch (PDOException $e) {
            $errors[] = 'We could not create the account. That phone number or email may already be registered.';
        }
    }
}

auth_start('Create account', 'Create your account', 'It takes less than a minute.', [
    'tab' => 'register', 'next' => $next, 'back' => ['index.php', 'Back to the store'], 'eyebrow' => 'Join us',
    'perks' => ['Track every order from placed to delivered', 'Faster checkout with your details saved', 'No spam. Only things about your orders'],
]);
?>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" autocomplete="on" novalidate>
    <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
    <?php auth_field('name', 'Full name', 'text', $v['name'], ['autocomplete' => 'name', 'required' => 'required', 'maxlength' => '80']); ?>
    <?php auth_field('phone', 'Phone number', 'tel', $v['phone'], ['autocomplete' => 'tel', 'required' => 'required', 'inputmode' => 'tel']); ?>
    <?php auth_field('email', 'Email (optional)', 'email', $v['email'], ['autocomplete' => 'email', 'maxlength' => '120']); ?>
    <?php auth_field('password', 'Password (8+ characters)', 'password', '', ['autocomplete' => 'new-password', 'required' => 'required', 'minlength' => '8']); ?>
    <div class="meter" id="pwMeter" data-s="0" aria-hidden="true"><span></span><span></span><span></span><span></span><small></small></div>
    <?php auth_field('password2', 'Repeat password', 'password', '', ['autocomplete' => 'new-password', 'required' => 'required']); ?>
    <button class="btn btn-gold btn-lg btn-block">Create account</button>
</form>
<p class="auth-alt">Already have an account? <a class="lnk" href="<?= e(url('login.php?next=' . urlencode($next))) ?>">Sign in</a></p>
<?php auth_end(); ?>
