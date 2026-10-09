<?php
require __DIR__ . '/_init.php'; require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$st = db()->prepare('SELECT * FROM orders WHERE id = ?'); $st->execute([$id]); $o = $st->fetch();
if (!$o) { flash('error', 'Order not found.'); redirect('admin/orders.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'status') { set_order_status($id, (string)($_POST['status'] ?? '')); flash('success', 'Order status updated.'); }
    elseif ($act === 'delete') {
        // Deleting a live order gives its stock back first.
        if ($o['status'] !== 'Cancelled') set_order_status($id, 'Cancelled');
        db()->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
        flash('success', 'Order deleted.'); redirect('admin/orders.php');
    }
    redirect('admin/order_view.php?id=' . $id);
}
$it = db()->prepare('SELECT * FROM order_items WHERE order_id = ?'); $it->execute([$id]); $items = $it->fetchAll();
$msg = 'Hello ' . $o['customer_name'] . ', this is ' . setting('store_name') . ' about your order ' . $o['order_no'] . '.';

admin_header('Order ' . $o['order_no'], 'orders');
?>
<div class="cols">
<section class="box">
    <div class="box-h"><h2>Items</h2><span class="st <?= e(status_class($o['status'])) ?>"><?= e($o['status']) ?></span></div>
    <div class="tbl-wrap"><table class="tbl"><tr><th>Item</th><th>Price</th><th>Qty</th><th>Total</th></tr>
    <?php foreach ($items as $i): ?><tr><td><?= e($i['name']) ?><?= $i['variant'] !== '' ? '<br><small class="muted">' . e($i['variant']) . '</small>' : '' ?></td><td><?= e(money($i['price'])) ?></td><td><?= (int)$i['qty'] ?></td><td><?= e(money($i['price'] * $i['qty'])) ?></td></tr><?php endforeach; ?>
    <tr><td colspan="3" style="text-align:right">Subtotal</td><td><?= e(money($o['subtotal'])) ?></td></tr>
    <tr><td colspan="3" style="text-align:right">Delivery</td><td><?= $o['delivery_fee'] > 0 ? e(money($o['delivery_fee'])) : 'Free' ?></td></tr>
    <tr><td colspan="3" style="text-align:right"><b>Total</b></td><td><b><?= e(money($o['total'])) ?></b></td></tr></table></div>
</section>
<section>
    <div class="box form-box">
        <h2>Customer</h2>
        <p style="margin:0 0 4px"><b><?= e($o['customer_name']) ?></b></p>
        <p style="margin:0 0 4px">📞 <a href="tel:<?= e($o['phone']) ?>"><?= e($o['phone']) ?></a></p>
        <p style="margin:0 0 4px">📍 <?= e($o['location']) ?></p>
        <?php if ($o['address'] !== ''): ?><p style="margin:0 0 4px"><?= e($o['address']) ?></p><?php endif; ?>
        <?php if ($o['notes'] !== ''): ?><p class="note">Note: <?= e($o['notes']) ?></p><?php endif; ?>
        <p class="muted" style="margin:8px 0">Placed <?= e(date('d M Y, H:i', strtotime($o['created_at']))) ?></p>
        <a class="btn btn-wa btn-block" target="_blank" rel="noopener" href="<?= e(wa_url($o['phone'], $msg)) ?>">Message customer on WhatsApp</a>
    </div>
    <div class="box form-box">
        <h2>Update status</h2>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $id ?>">
            <div class="field"><select name="status" aria-label="Status"><?php foreach (ORDER_STATUSES as $s): ?><option <?= $o['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select>
            <small class="muted">Cancelling an order puts its items back in stock.</small></div>
            <button class="btn btn-primary">Save status</button>
            <button type="button" class="btn" onclick="window.print()">Print</button>
        </form>
        <form method="post" data-confirm="Delete this order permanently?" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-sm btn-danger">Delete order</button></form>
    </div>
</section>
</div>
<?php admin_footer(); ?>
