<?php
require __DIR__ . '/_init.php'; require_admin();
$q = fn(string $sql) => db()->query($sql)->fetchColumn();

$today   = (int)$q("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
$new     = (int)$q("SELECT COUNT(*) FROM orders WHERE status = 'New'");
$month   = (float)$q("SELECT COALESCE(SUM(total),0) FROM orders WHERE status <> 'Cancelled' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
$sales   = (float)$q("SELECT COALESCE(SUM(total),0) FROM orders WHERE status = 'Delivered'");
$prodN   = (int)$q('SELECT COUNT(*) FROM products');
$custN   = (int)$q('SELECT COUNT(*) FROM customers');
$recent  = db()->query('SELECT * FROM orders ORDER BY id DESC LIMIT 8')->fetchAll();
$low     = db()->query('SELECT id, name, stock FROM products WHERE active = 1 AND stock <= 5 ORDER BY stock, name LIMIT 8')->fetchAll();
$wa      = normalize_phone(setting('whatsapp'));
$link    = share_url('');
$isLocal = (bool)preg_match('#//(localhost|127\.|\[::1\]|0\.0\.0\.0)#i', $link);
$lan = '';
$hn = @gethostname(); $ip = $hn ? @gethostbyname($hn) : '';
if ($ip && $ip !== $hn && filter_var($ip, FILTER_VALIDATE_IP) && strpos($ip, '127.') !== 0) $lan = 'http://' . $ip . BASE_URL . '/';

admin_header('Dashboard', 'dashboard');
if (!$wa): ?><div class="alert alert-info">Add your WhatsApp number in <a href="<?= e(url('admin/settings.php')) ?>"><b>Store settings</b></a> so customers can send you their orders.</div><?php endif; ?>
<?php if (!setting('hero_image')): ?><div class="alert alert-info">Your home page banner has no picture yet. <a href="<?= e(url('admin/hero.php')) ?>"><b>Upload your hero banner</b></a> to make the first screen shine.</div><?php endif; ?>
<section class="box share-card">
    <div class="box-h"><h2>Your store link</h2></div>
    <p class="muted" style="margin-top:0">Send this link to customers so they can browse and order from your store.</p>
    <div class="share-bar">
        <input id="storeLink" readonly value="<?= e($link) ?>" onclick="this.select()" aria-label="Store link">
        <button type="button" class="btn btn-primary" data-copy="<?= e($link) ?>">Copy link</button>
        <a class="btn btn-wa" target="_blank" rel="noopener" href="<?= e('https://wa.me/?text=' . rawurlencode('Shop with us at ' . setting('store_name') . ': ' . $link)) ?>">Send on WhatsApp</a>
        <a class="btn" target="_blank" href="<?= e($link) ?>">Open store</a>
    </div>
    <?php if ($isLocal): ?>
        <div class="alert alert-info" style="margin:14px 0 0">
            <b>This link only opens on this computer.</b> Customers cannot reach "localhost".
            Once your store is on a web host, type its address in <a href="<?= e(url('admin/settings.php')) ?>"><b>Store settings, Public store link</b></a>
            and this box will show the address to send out.
            <?php if ($lan): ?>For phones or computers on <b>the same Wi-Fi</b> only, you can use:
            <div class="share-bar" style="margin-top:8px"><input readonly value="<?= e($lan) ?>" onclick="this.select()" aria-label="Wi-Fi link"><button type="button" class="btn btn-sm" data-copy="<?= e($lan) ?>">Copy Wi-Fi link</button></div><?php endif; ?>
        </div>
    <?php endif; ?>
</section>
<div class="stats">
    <div class="stat"><small>New orders waiting</small><b><?= $new ?></b></div>
    <div class="stat"><small>Orders today</small><b><?= $today ?></b></div>
    <div class="stat"><small>Sales this month</small><b><?= e(money($month)) ?></b></div>
    <div class="stat"><small>Delivered sales (all time)</small><b><?= e(money($sales)) ?></b></div>
    <div class="stat"><small>Products</small><b><?= $prodN ?></b></div>
    <div class="stat"><small>Customer accounts</small><b><?= $custN ?></b></div>
</div>
<div class="cols">
    <section class="box">
        <div class="box-h"><h2>Latest orders</h2><a href="<?= e(url('admin/orders.php')) ?>">View all</a></div>
        <?php if ($recent): ?>
        <div class="tbl-wrap"><table class="tbl"><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr>
        <?php foreach ($recent as $o): ?>
            <tr><td><a href="<?= e(url('admin/order_view.php?id=' . $o['id'])) ?>"><b><?= e($o['order_no']) ?></b></a><br><small class="muted"><?= e(date('d M, H:i', strtotime($o['created_at']))) ?></small></td>
                <td><?= e($o['customer_name']) ?><br><small class="muted"><?= e($o['location']) ?></small></td>
                <td><?= e(money($o['total'])) ?></td><td><span class="st <?= e(status_class($o['status'])) ?>"><?= e($o['status']) ?></span></td></tr>
        <?php endforeach; ?></table></div>
        <?php else: ?><p class="muted">No orders yet. They appear here the moment a customer places one.</p><?php endif; ?>
    </section>
    <section class="box">
        <div class="box-h"><h2>Running low</h2><a href="<?= e(url('admin/products.php')) ?>">Products</a></div>
        <?php if ($low): ?><table class="tbl"><?php foreach ($low as $p): ?>
            <tr><td><a href="<?= e(url('admin/product_form.php?id=' . $p['id'])) ?>"><?= e($p['name']) ?></a></td><td class="<?= $p['stock'] <= 0 ? 'bad' : 'warn' ?>"><?= $p['stock'] <= 0 ? 'Sold out' : $p['stock'] . ' left' ?></td></tr>
        <?php endforeach; ?></table>
        <?php else: ?><p class="muted">Every product has plenty of stock.</p><?php endif; ?>
    </section>
</div>
<?php admin_footer(); ?>
