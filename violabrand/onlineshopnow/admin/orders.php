<?php
require __DIR__ . '/_init.php'; require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'status') {
        set_order_status((int)($_POST['id'] ?? 0), (string)($_POST['status'] ?? ''));
        flash('success', 'Order status updated.');
    }
    redirect('admin/orders.php' . (!empty($_POST['back']) ? '?' . ltrim((string)$_POST['back'], '?') : ''));
}

$status = $_GET['status'] ?? ''; $q = trim($_GET['q'] ?? ''); $page = max(1, (int)($_GET['page'] ?? 1)); $per = 25;
$where = ['1=1']; $args = [];
if (in_array($status, ORDER_STATUSES, true)) { $where[] = 'status = ?'; $args[] = $status; }
if ($q !== '') { $where[] = '(order_no LIKE ? OR customer_name LIKE ? OR phone LIKE ?)'; $like = '%' . $q . '%'; array_push($args, $like, $like, $like); }
$w = implode(' AND ', $where);
$st = db()->prepare("SELECT COUNT(*) FROM orders WHERE $w"); $st->execute($args);
$total = (int)$st->fetchColumn(); $pages = max(1, (int)ceil($total / $per)); $page = min($page, $pages);
$st = db()->prepare("SELECT * FROM orders WHERE $w ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($args); $rows = $st->fetchAll();
$counts = db()->query('SELECT status, COUNT(*) n FROM orders GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$back = http_build_query(array_filter(['status' => $status, 'q' => $q, 'page' => $page > 1 ? $page : '']));

admin_header('Orders', 'orders');
?>
<div class="tabs">
    <a href="<?= e(url('admin/orders.php')) ?>" class="<?= $status === '' ? 'on' : '' ?>">All</a>
    <?php foreach (ORDER_STATUSES as $s): ?><a href="?status=<?= e($s) ?>" class="<?= $status === $s ? 'on' : '' ?>"><?= e($s) ?> <small><?= (int)($counts[$s] ?? 0) ?></small></a><?php endforeach; ?>
</div>
<div class="toolbar"><form method="get" class="inline">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <input name="q" value="<?= e($q) ?>" placeholder="Search order no, name or phone" aria-label="Search orders"><button class="btn">Search</button></form></div>
<div class="box"><div class="tbl-wrap"><table class="tbl">
    <tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th></th></tr>
    <?php foreach ($rows as $o):
        $msg = 'Hello ' . $o['customer_name'] . ', this is ' . setting('store_name') . ' about your order ' . $o['order_no'] . '.'; ?>
    <tr>
        <td><a href="<?= e(url('admin/order_view.php?id=' . $o['id'])) ?>"><b><?= e($o['order_no']) ?></b></a><br><small class="muted"><?= e(date('d M Y, H:i', strtotime($o['created_at']))) ?></small></td>
        <td><?= e($o['customer_name']) ?><br><small class="muted"><?= e($o['phone']) ?> | <?= e($o['location']) ?></small></td>
        <td><b><?= e(money($o['total'])) ?></b></td>
        <td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
            <select name="status" class="st-select <?= e(status_class($o['status'])) ?>" onchange="this.form.submit()" aria-label="Order status">
                <?php foreach (ORDER_STATUSES as $s): ?><option <?= $o['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></form></td>
        <td class="actions"><a class="btn btn-sm" href="<?= e(url('admin/order_view.php?id=' . $o['id'])) ?>">View</a>
            <a class="btn btn-sm btn-wa" target="_blank" rel="noopener" href="<?= e(wa_url($o['phone'], $msg)) ?>">WhatsApp</a></td>
    </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="5" class="muted" style="text-align:center;padding:26px">No orders here yet.</td></tr><?php endif; ?>
</table></div></div>
<?= pager($page, $pages, ['status' => $status, 'q' => $q]) ?>
<?php admin_footer(); ?>
