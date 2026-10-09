<?php
require __DIR__ . '/_init.php'; require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0); $act = $_POST['action'] ?? '';
    if ($act === 'reset') {
        // A temporary password the owner can pass on to a customer who forgot theirs.
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789'; $tmp = '';
        for ($i = 0; $i < 8; $i++) $tmp .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $st = db()->prepare('UPDATE customers SET password_hash = ? WHERE id = ?');
        $st->execute([password_hash($tmp, PASSWORD_DEFAULT), $id]);
        flash('success', 'New temporary password: ' . $tmp . '  (tell the customer to change it in My account)');
    } elseif ($act === 'delete') {
        db()->prepare('UPDATE orders SET customer_id = NULL WHERE customer_id = ?')->execute([$id]);
        db()->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);
        flash('success', 'Customer account deleted. Their past orders are kept.');
    }
    redirect('admin/customers.php');
}

$q = trim($_GET['q'] ?? ''); $page = max(1, (int)($_GET['page'] ?? 1)); $per = 25;
$where = '1=1'; $args = [];
if ($q !== '') { $where = '(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)'; $like = '%' . $q . '%'; $args = [$like, $like, $like]; }
$st = db()->prepare("SELECT COUNT(*) FROM customers c WHERE $where"); $st->execute($args);
$total = (int)$st->fetchColumn(); $pages = max(1, (int)ceil($total / $per)); $page = min($page, $pages);
$st = db()->prepare("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS n_orders,
                     (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.customer_id = c.id AND o.status <> 'Cancelled') AS spent
                     FROM customers c WHERE $where ORDER BY c.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($args); $rows = $st->fetchAll();

admin_header('Customers', 'customers');
?>
<p class="muted" style="margin-top:0">People who created an account on your store. Customers must sign in or sign up before they can add items to the cart.</p>
<div class="toolbar"><form method="get" class="inline">
    <input name="q" value="<?= e($q) ?>" placeholder="Search name, phone or email" aria-label="Search customers"><button class="btn">Search</button></form>
    <span class="muted"><?= $total ?> customer<?= $total === 1 ? '' : 's' ?></span></div>
<div class="box"><div class="tbl-wrap"><table class="tbl">
    <tr><th>Customer</th><th>Contact</th><th>Orders</th><th>Joined</th><th></th></tr>
    <?php foreach ($rows as $c): ?>
    <tr>
        <td><b><?= e($c['name']) ?></b></td>
        <td><a href="tel:<?= e($c['phone']) ?>"><?= e($c['phone']) ?></a><?= $c['email'] ? '<br><small class="muted">' . e($c['email']) . '</small>' : '' ?></td>
        <td><?= (int)$c['n_orders'] ?><?= $c['n_orders'] ? '<br><small class="muted">' . e(money($c['spent'])) . '</small>' : '' ?></td>
        <td><small class="muted"><?= e(date('d M Y', strtotime($c['created_at']))) ?></small></td>
        <td class="actions">
            <a class="btn btn-sm btn-wa" target="_blank" rel="noopener" href="<?= e(wa_url($c['phone'], 'Hello ' . first_name($c['name']) . ', this is ' . setting('store_name') . '.')) ?>">WhatsApp</a>
            <form method="post" class="inline" data-confirm="Create a new temporary password for this customer?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm" name="action" value="reset">Reset password</button></form>
            <form method="post" class="inline" data-confirm="Delete this customer account? Their past orders are kept."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-danger" name="action" value="delete">Delete</button></form>
        </td>
    </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="5" class="muted" style="text-align:center;padding:26px">No customer accounts yet. They appear here when someone signs up.</td></tr><?php endif; ?>
</table></div></div>
<?= pager($page, $pages, ['q' => $q]) ?>
<?php admin_footer(); ?>
