<?php
require __DIR__ . '/includes/functions.php';

$order = null; $items = []; $error = '';
$no = trim($_POST['order_no'] ?? ''); $ph = trim($_POST['phone'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $digits = preg_replace('/\D+/', '', $ph);
    if ($no === '' || strlen($digits) < 9) {
        $error = 'Enter your order number and the phone number you used.';
    } else {
        $st = db()->prepare('SELECT * FROM orders WHERE order_no = ?'); $st->execute([strtoupper($no)]);
        $o = $st->fetch();
        // Compare the last 9 digits, so 0712..., 254712... and +254712... all match.
        if ($o && substr(preg_replace('/\D+/', '', $o['phone']), -9) === substr($digits, -9)) {
            $order = $o;
            $it = db()->prepare('SELECT * FROM order_items WHERE order_id = ?'); $it->execute([$o['id']]); $items = $it->fetchAll();
        } else {
            $error = 'We could not find an order with those details. Check the order number and phone number.';
        }
    }
}
$page_title = 'Track my order | ' . setting('store_name');
require __DIR__ . '/includes/header.php';
$flow = ['New', 'Confirmed', 'Dispatched', 'Delivered'];
?>
<div style="max-width:640px;margin:0 auto">
    <h1 class="page-h">Track my order</h1>
    <form method="post" class="panel">
        <?= csrf_field() ?>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <div class="field"><label for="order_no">Order number</label><input id="order_no" name="order_no" value="<?= e($no) ?>" placeholder="ORD-240101-AB12C" required></div>
        <div class="field"><label for="phone">Phone number used on the order</label><input id="phone" name="phone" type="tel" value="<?= e($ph) ?>" placeholder="0712 345 678" required></div>
        <button class="btn btn-primary">Check status</button>
    </form>
    <?php if ($order): ?>
    <div class="panel" style="margin-top:14px">
        <h2 class="panel-h"><?= e($order['order_no']) ?></h2>
        <?php if ($order['status'] === 'Cancelled'): ?>
            <div class="alert alert-error">This order was cancelled. Contact us on WhatsApp if you need help.</div>
        <?php else:
            $at = array_search($order['status'], $flow, true); ?>
            <div class="steps"><?php foreach ($flow as $i => $s): ?><span class="<?= $i < $at ? 'done' : ($i === $at ? 'now' : '') ?>"><?= e($s) ?></span><?php endforeach; ?></div>
        <?php endif; ?>
        <?php foreach ($items as $i): ?><div class="sum-line"><span><?= e($i['name']) ?><?= $i['variant'] !== '' ? ' <small class="muted">(' . e($i['variant']) . ')</small>' : '' ?> &times; <?= (int)$i['qty'] ?></span><span><?= e(money($i['price'] * $i['qty'])) ?></span></div><?php endforeach; ?>
        <div class="sum-line total"><span>Total</span><span><?= e(money($order['total'])) ?></span></div>
        <p style="color:var(--muted);margin:0">Placed <?= e(date('d M Y, H:i', strtotime($order['created_at']))) ?></p>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
