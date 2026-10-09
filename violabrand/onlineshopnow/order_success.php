<?php
require __DIR__ . '/includes/functions.php';

$st = db()->prepare('SELECT * FROM orders WHERE order_no = ? AND access_token = ?');
$st->execute([$_GET['o'] ?? '', $_GET['t'] ?? '']);
$o = $st->fetch();
if (!$o) { http_response_code(404); flash('error', 'Order not found.'); redirect('index.php'); }
$it = db()->prepare('SELECT * FROM order_items WHERE order_id = ?'); $it->execute([$o['id']]);
$items = $it->fetchAll();

$wa = normalize_phone(setting('whatsapp'));
$wa_link = $wa ? wa_url($wa, order_message($o, $items)) : '';
$page_title = 'Order ' . $o['order_no'] . ' | ' . setting('store_name');
require __DIR__ . '/includes/header.php';
?>
<div class="panel success-box" style="max-width:640px;margin:0 auto">
    <div class="tick">✓</div>
    <h1 class="page-h">Order received</h1>
    <div class="order-no"><?= e($o['order_no']) ?></div>
    <p style="color:var(--muted)">Your order is saved. <b>Send it on WhatsApp</b> so we can confirm it and arrange delivery.</p>
    <?php if ($wa_link): ?>
        <a id="waAuto" class="btn btn-wa btn-lg btn-block" href="<?= e($wa_link) ?>">Send order on WhatsApp</a>
        <p style="font-size:13px;color:var(--muted)">WhatsApp opens automatically in a moment. If it does not, tap the green button.</p>
    <?php else: ?>
        <div class="alert alert-info">We will call <b><?= e($o['phone']) ?></b> to confirm your order.</div>
    <?php endif; ?>

    <div style="text-align:left;margin-top:18px">
        <?php foreach ($items as $i): ?>
            <div class="sum-line"><span><?= e($i['name']) ?><?= $i['variant'] !== '' ? ' <small class="muted">(' . e($i['variant']) . ')</small>' : '' ?> &times; <?= (int)$i['qty'] ?></span><span><?= e(money($i['price'] * $i['qty'])) ?></span></div>
        <?php endforeach; ?>
        <div class="sum-line"><span>Delivery</span><span><?= $o['delivery_fee'] > 0 ? e(money($o['delivery_fee'])) : 'Free' ?></span></div>
        <div class="sum-line total"><span>Total</span><span><?= e(money($o['total'])) ?></span></div>
        <p style="color:var(--muted);margin-bottom:0">Deliver to: <?= e($o['customer_name']) ?>, <?= e($o['phone']) ?>, <?= e($o['location']) ?></p>
    </div>
    <p style="margin-top:18px"><?php if (customer() && (int)$o['customer_id'] === (int)customer()['id']): ?><a class="lnk" href="<?= e(url('account.php')) ?>">View in My orders</a><?php else: ?><a class="lnk" href="<?= e(url('track.php')) ?>">Track this order</a><?php endif; ?> &nbsp;&nbsp; <a class="lnk" href="<?= e(url('index.php')) ?>">Continue shopping</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
