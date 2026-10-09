<?php
require __DIR__ . '/includes/functions.php';

$cust = require_customer('checkout.php');
[$items, $subtotal] = cart_items();
if (!$items) { flash('error', 'Your cart is empty.'); redirect('index.php'); }
$fee = delivery_fee($subtotal);
$errors = [];
$old = ['name' => '', 'phone' => '', 'location' => '', 'address' => '', 'notes' => ''];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $old['name'] = $cust['name']; $old['phone'] = $cust['phone']; }   // signed-in customers get their details filled in

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($old as $k => $_) $old[$k] = trim((string)($_POST[$k] ?? ''));
    $phone = normalize_phone($old['phone']);
    if (mb_strlen($old['name']) < 2)    $errors[] = 'Please enter your name.';
    if (strlen($phone) < 10)            $errors[] = 'Please enter a valid phone number, for example 0712 345 678.';
    if ($old['location'] === '')        $errors[] = 'Please tell us your town or delivery location.';
    foreach (['name' => 80, 'location' => 120, 'address' => 255, 'notes' => 500] as $k => $max)
        if (mb_strlen($old[$k]) > $max) $errors[] = 'Some text is too long (' . $k . ').';

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $ids = array_column($items, 'id');
            $st = $pdo->prepare('SELECT id, name, price, stock FROM products WHERE active = 1 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') FOR UPDATE');
            $st->execute($ids);
            $fresh = [];
            foreach ($st as $r) $fresh[(int)$r['id']] = $r;

            // All sizes/colours of one product share its stock, so check the combined quantity.
            $need = [];
            foreach ($items as $it) $need[(int)$it['id']] = ($need[(int)$it['id']] ?? 0) + (int)$it['qty'];
            foreach ($need as $pid => $q) {
                $r = $fresh[$pid] ?? null;
                if (!$r) throw new UserError('An item in your cart is no longer available. Please review your cart.');
                if ((int)$r['stock'] < $q) throw new UserError('Only ' . (int)$r['stock'] . ' of "' . $r['name'] . '" left. Please update your cart.');
            }
            $sub = 0.0; $lines = [];
            foreach ($items as $it) {
                $r = $fresh[(int)$it['id']];
                $lines[] = ['id' => (int)$r['id'], 'name' => $r['name'], 'variant' => $it['variant'], 'price' => (float)$r['price'], 'qty' => (int)$it['qty']];
                $sub += (float)$r['price'] * (int)$it['qty'];
            }
            $fee = delivery_fee($sub);
            $total = $sub + $fee;

            do {
                $orderNo = 'ORD-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
                $dup = $pdo->prepare('SELECT 1 FROM orders WHERE order_no = ?'); $dup->execute([$orderNo]);
            } while ($dup->fetch());
            $token = bin2hex(random_bytes(8));

            $pdo->prepare('INSERT INTO orders (order_no, access_token, customer_id, customer_name, phone, location, address, notes, subtotal, delivery_fee, total, status)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$orderNo, $token, (int)$cust['id'], $old['name'], $phone, $old['location'], $old['address'], $old['notes'], $sub, $fee, $total, 'New']);
            $orderId = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO order_items (order_id, product_id, name, variant, price, qty) VALUES (?,?,?,?,?,?)');
            $dec = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            foreach ($lines as $l) { $ins->execute([$orderId, $l['id'], $l['name'], $l['variant'], $l['price'], $l['qty']]); $dec->execute([$l['qty'], $l['id']]); }
            $pdo->commit();

            unset($_SESSION['cart']);
            redirect('order_success.php?o=' . urlencode($orderNo) . '&t=' . $token);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = ($e instanceof UserError) ? $e->getMessage() : 'We could not save your order. Please try again.';
        }
    }
}

$page_title = 'Checkout | ' . setting('store_name');
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-h">Checkout</h1>
<div class="steps"><span class="done">1. Cart</span><span class="now">2. Your details</span><span>3. Send on WhatsApp</span></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<div class="two-col">
    <form method="post" class="panel" autocomplete="on">
        <?= csrf_field() ?>
        <h2 class="panel-h">Delivery details</h2>

        <div class="field"><label for="name">Full name</label><input id="name" name="name" value="<?= e($old['name']) ?>" maxlength="80" required autocomplete="name"></div>
        <div class="field"><label for="phone">Phone number <small>(we will contact you here)</small></label><input id="phone" name="phone" type="tel" inputmode="tel" value="<?= e($old['phone']) ?>" placeholder="0712 345 678" required autocomplete="tel"></div>
        <div class="field"><label for="location">Town / delivery location</label><input id="location" name="location" value="<?= e($old['location']) ?>" maxlength="120" placeholder="e.g. Nyali, Mombasa" required></div>
        <div class="field"><label for="address">Street, building or landmark <small>(optional)</small></label><input id="address" name="address" value="<?= e($old['address']) ?>" maxlength="255"></div>
        <div class="field"><label for="notes">Notes <small>(optional)</small></label><textarea id="notes" name="notes" maxlength="500" placeholder="Size, colour, delivery time..."><?= e($old['notes']) ?></textarea></div>
        <button class="btn btn-wa btn-lg btn-block">Place order</button>
        <p class="note-box" style="margin-top:14px">Next you will send the order to us on WhatsApp so we can confirm it. You pay on delivery unless we agree otherwise.</p>
    </form>
    <aside class="panel">
        <h2 class="panel-h">Your order</h2>
        <?php foreach ($items as $it): ?>
            <div class="sum-line"><span><?= e($it['name']) ?><?= $it['variant'] !== '' ? ' <small class="muted">(' . e($it['variant']) . ')</small>' : '' ?> &times; <?= (int)$it['qty'] ?></span><span><?= e(money($it['line'])) ?></span></div>
        <?php endforeach; ?>
        <div class="sum-line" style="border-top:1px solid var(--line);margin-top:6px"><span>Subtotal</span><span><?= e(money($subtotal)) ?></span></div>
        <div class="sum-line"><span>Delivery</span><span><?= $fee > 0 ? e(money($fee)) : 'Free' ?></span></div>
        <div class="sum-line total"><span>Total</span><span><?= e(money($subtotal + $fee)) ?></span></div>
        <a class="lnk" href="<?= e(url('cart.php')) ?>">Edit cart</a>
    </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
