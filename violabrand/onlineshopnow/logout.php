<?php
require __DIR__ . '/includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    unset($_SESSION['customer_id'], $_SESSION['cart'], $_SESSION['pending_cart']);
    session_regenerate_id(true);
    flash('success', 'You have signed out. See you soon.');
}
redirect('index.php');
