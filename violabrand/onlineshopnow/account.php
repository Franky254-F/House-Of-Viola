<?php
require __DIR__ . '/includes/functions.php';
$me = require_customer('account.php');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'profile') {
        $name = trim((string)($_POST['name'] ?? '')); $phone = normalize_phone((string)($_POST['phone'] ?? '')); $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) $errors[] = 'Please enter your name.';
        if (strlen($phone) < 10 || strlen($phone) > 15)    $errors[] = 'Please enter a valid phone number.';
        if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120)) $errors[] = 'That email address does not look right.';
        if (!$errors) {
            $dup = db()->prepare('SELECT id FROM customers WHERE id <> ? AND (phone = ? OR (? <> \'\' AND email = ?))');
            $dup->execute([$me['id'], $phone, $email, $email]);
            if ($dup->fetch()) $errors[] = 'Another account already uses that phone number or email.';
        }
        if (!$errors) {
            db()->prepare('UPDATE customers SET name = ?, phone = ?, email = ? WHERE id = ?')->execute([$name, $phone, $email !== '' ? $email : null, $me['id']]);
            flash('success', 'Your details were saved.'); redirect('account.php');
        }
    } elseif ($act === 'password') {
        $st = db()->prepare('SELECT password_hash FROM customers WHERE id = ?'); $st->execute([$me['id']]);
        $new = (string)($_POST['new_password'] ?? '');
        if (!password_verify((string)($_POST['current_password'] ?? ''), (string)$st->fetchColumn())) $errors[] = 'Your current password is not correct.';
        elseif (strlen($new) < 8)                      $errors[] = 'The new password must be at least 8 characters.';
        elseif ($new !== (string)($_POST['new_password2'] ?? '')) $errors[] = 'The two new passwords do not match.';
        else {
            db()->prepare('UPDATE customers SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash('success', 'Your password was changed.'); redirect('account.php');
        }
    }
}

$st = db()->prepare('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 50');
$st->execute([$me['id']]);
$orders = $st->fetchAll();
$itemsBy = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $q = db()->prepare('SELECT * FROM order_items WHERE order_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id');
    $q->execute($ids);
    foreach ($q as $it) $itemsBy[(int)$it['order_id']][] = $it;
}

$page_title = 'My account | ' . setting('store_name');
require __DIR__ . '/includes/header.php';
?>
<div class="acct-head">
    <div>
        <p class="eyebrow" style="margin-bottom:10px"><span class="gl"></span>My account</p>
        <h1 class="page-h">Hello, <?= e(first_name($me['name'])) ?></h1>
        <p class="muted" style="margin:0">Member since <?= e(date('F Y', strtotime($me['created_at']))) ?></p>
    </div>
    <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="btn btn-ghost">Sign out</button></form>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>

<div class="acct-grid">
    <section>
        <h2 class="panel-h">My orders</h2>
        <?php if (!$orders): ?>
            <div class="empty"><p>You have not placed an order while signed in yet.</p><a class="btn btn-gold" href="<?= e(url('category.php')) ?>">Start shopping</a></div>
        <?php else: foreach ($orders as $o): ?>
            <details class="order-card">
                <summary>
                    <span><b><?= e($o['order_no']) ?></b><br><small class="muted"><?= e(date('d M Y, H:i', strtotime($o['created_at']))) ?></small></span>
                    <span class="chip-s s-<?= e(strtolower($o['status'])) ?>"><?= e($o['status']) ?></span>
                    <span class="gold-t"><?= e(money($o['total'])) ?></span>
                </summary>
                <div class="order-body">
                    <?php foreach ($itemsBy[(int)$o['id']] ?? [] as $it): ?>
                        <div class="sum-line"><span><?= e($it['name']) ?><?= $it['variant'] !== '' ? ' <small class="muted">(' . e($it['variant']) . ')</small>' : '' ?> &times; <?= (int)$it['qty'] ?></span><span><?= e(money($it['price'] * $it['qty'])) ?></span></div>
                    <?php endforeach; ?>
                    <div class="sum-line"><span>Delivery</span><span><?= $o['delivery_fee'] > 0 ? e(money($o['delivery_fee'])) : 'Free' ?></span></div>
                    <div class="sum-line total"><span>Total</span><span><?= e(money($o['total'])) ?></span></div>
                    <p class="muted" style="margin:10px 0 0;font-size:14px">Deliver to: <?= e($o['location']) ?><?= $o['address'] !== '' ? ', ' . e($o['address']) : '' ?></p>
                </div>
            </details>
        <?php endforeach; endif; ?>
    </section>

    <aside>
        <form method="post" class="panel" style="margin-bottom:20px">
            <?= csrf_field() ?><input type="hidden" name="action" value="profile">
            <h2 class="panel-h">My details</h2>
            <div class="field"><label for="name">Full name</label><input id="name" name="name" value="<?= e($_POST['name'] ?? $me['name']) ?>" required maxlength="80"></div>
            <div class="field"><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" value="<?= e($_POST['phone'] ?? $me['phone']) ?>" required></div>
            <div class="field"><label for="email">Email <small>(optional)</small></label><input id="email" name="email" type="email" value="<?= e($_POST['email'] ?? ($me['email'] ?? '')) ?>" maxlength="120"></div>
            <button class="btn btn-gold btn-block">Save details</button>
        </form>
        <form method="post" class="panel">
            <?= csrf_field() ?><input type="hidden" name="action" value="password">
            <h2 class="panel-h">Change password</h2>
            <div class="field"><label for="cp">Current password</label><input id="cp" name="current_password" type="password" autocomplete="current-password" required></div>
            <div class="field"><label for="np">New password</label><input id="np" name="new_password" type="password" minlength="8" autocomplete="new-password" required></div>
            <div class="field"><label for="np2">Repeat new password</label><input id="np2" name="new_password2" type="password" minlength="8" autocomplete="new-password" required></div>
            <button class="btn btn-ghost btn-block">Change password</button>
        </form>
    </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
