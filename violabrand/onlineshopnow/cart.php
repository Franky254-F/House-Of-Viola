<?php
require __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'add' || $action === 'buy') {
        $args = [$id, (int)($_POST['qty'] ?? 1), (string)($_POST['size'] ?? ''), (string)($_POST['color'] ?? '')];
        if (!customer()) {
            // Not signed in: remember the choice, send them to sign in / create an account, then add it for them.
            $_SESSION['pending_cart'] = ['action' => $action, 'args' => $args];
            flash('info', 'Please sign in or create an account to add items to your cart. We have saved your choice.');
            redirect('login.php?next=' . urlencode('cart.php?resume=1'));
        }
        [$ok, $msg, $page] = cart_add(...$args);
        if (!$ok) { flash('error', $msg); redirect($page); }
        if ($action === 'buy') redirect('checkout.php');
        flash('success', $msg);
        back('cart.php');
    }
    if ($action === 'update') {
        foreach (($_POST['qty'] ?? []) as $key => $q) {
            if (!is_string($key) || !isset($_SESSION['cart'][$key])) continue;
            $q = (int)$q;
            if ($q <= 0) unset($_SESSION['cart'][$key]); else $_SESSION['cart'][$key]['qty'] = min($q, 99);
        }
        flash('success', 'Cart updated.');
    } elseif ($action === 'remove') { unset($_SESSION['cart'][(string)($_POST['key'] ?? '')]); }
    elseif ($action === 'clear')    { unset($_SESSION['cart']); }
    redirect('cart.php');
}

// Coming back from sign in / sign up: add the item the visitor chose before they were asked to sign in.
if (isset($_GET['resume'])) {
    $pend = $_SESSION['pending_cart'] ?? null;
    unset($_SESSION['pending_cart']);
    if (customer() && is_array($pend) && isset($pend['args'])) {
        [$ok, $msg, $page] = cart_add(...$pend['args']);
        if (!$ok) { flash('error', $msg); redirect($page); }
        if (($pend['action'] ?? '') === 'buy') redirect('checkout.php');
        flash('success', $msg);
    }
    redirect('cart.php');
}

[$items, $subtotal] = cart_items();
$fee = delivery_fee($subtotal);
$page_title = 'My cart | ' . setting('store_name');
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-h">Your cart</h1>
<?php if (!$items): ?>
    <div class="empty"><p>Your cart is empty.</p>
        <?php if (!customer()): ?><p>Sign in or create a free account to add items to your cart.</p>
            <a class="btn btn-gold" href="<?= e(url('login.php')) ?>">Sign in</a> <a class="btn btn-ghost" href="<?= e(url('register.php')) ?>">Create account</a>
        <?php else: ?><a class="btn btn-gold" href="<?= e(url('index.php')) ?>">Start shopping</a><?php endif; ?></div>
<?php else: ?>
<div class="two-col">
    <form method="post" class="panel">
        <?= csrf_field() ?><input type="hidden" name="action" value="update">
        <?php foreach ($items as $it): ?>
        <div class="cart-row">
            <a href="<?= e(url('product.php?p=' . urlencode($it['slug']))) ?>"><img src="<?= e(img_url($it['image'])) ?>" alt=""></a>
            <div>
                <a href="<?= e(url('product.php?p=' . urlencode($it['slug']))) ?>"><b><?= e($it['name']) ?></b></a>
                <?php if ($it['variant'] !== ''): ?><div class="variant"><?= e($it['variant']) ?></div><?php endif; ?>
                <div class="muted"><?= e(money($it['price'])) ?> each</div>
                <div class="qty" style="width:max-content;margin-top:6px">
                    <button type="button" data-d="-" aria-label="Less">&minus;</button>
                    <input type="number" name="qty[<?= e($it['key']) ?>]" value="<?= (int)$it['qty'] ?>" min="0" max="<?= min((int)$it['stock'], 99) ?>" aria-label="Quantity">
                    <button type="button" data-d="+" aria-label="More">+</button>
                </div>
            </div>
            <div class="line-tot"><?= e(money($it['line'])) ?><br>
                <button class="link-del" type="submit" form="rm-<?= e($it['key']) ?>">Remove</button></div>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
            <button class="btn btn-ghost">Update quantities</button>
            <a class="btn btn-ghost" href="<?= e(url('index.php')) ?>">Keep shopping</a>
        </div>
    </form>
    <?php foreach ($items as $it): ?>
        <form id="rm-<?= e($it['key']) ?>" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="key" value="<?= e($it['key']) ?>"></form>
    <?php endforeach; ?>

    <aside class="panel">
        <h2 class="panel-h">Order summary</h2>
        <div class="sum-line"><span>Subtotal</span><span><?= e(money($subtotal)) ?></span></div>
        <div class="sum-line"><span>Delivery</span><span><?= $fee > 0 ? e(money($fee)) : 'Free' ?></span></div>
        <div class="sum-line total"><span>Total</span><span><?= e(money($subtotal + $fee)) ?></span></div>
        <a class="btn btn-wa btn-lg btn-block" style="margin-top:14px" href="<?= e(url('checkout.php')) ?>">Checkout</a>
        <p class="note-box" style="margin-top:14px"><?= e(setting('delivery_note')) ?></p>
    </aside>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
