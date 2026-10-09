</div>
</main>
<?php
$wa_number = normalize_phone(setting('whatsapp'));
$socials   = social_links();
$home      = share_url('');
$mail      = trim(setting('email'));
[$mailHref, $mailNew] = $mail !== '' ? email_link($mail) : ['', false];
?>
<footer class="site-footer">
    <div class="container foot-top">
        <div class="foot-brand"><?= e(setting('store_name')) ?></div>
        <div class="foot-share">
            <p>Share the shop with a friend</p>
            <div class="share-bar">
                <input type="text" readonly value="<?= e($home) ?>" aria-label="Store link" onclick="this.select()">
                <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($home) ?>">Copy link</button>
                <a class="btn btn-wa btn-sm" target="_blank" rel="noopener" href="<?= e('https://wa.me/?text=' . rawurlencode('Have a look at ' . setting('store_name') . ': ' . $home)) ?>">WhatsApp</a>
            </div>
        </div>
    </div>

    <div class="container foot-grid">
        <div class="foot-col foot-col-about">
            <h4>About</h4>
            <p><?= e(setting('footer_about')) ?></p>
            <h4 class="foot-contact-h">Contact</h4>
            <?php if ($wa_number): ?><a href="<?= e(wa_url($wa_number)) ?>" target="_blank" rel="noopener">WhatsApp +<?= e($wa_number) ?></a><?php endif; ?>
            <?php if (setting('phone') !== ''): ?><a href="tel:<?= e(setting('phone')) ?>"><?= e(setting('phone')) ?></a><?php endif; ?>
            <?php if ($mail !== ''): ?><a class="foot-mail" href="<?= e($mailHref) ?>" <?= $mailNew ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><?= e($mail) ?></a><?php endif; ?>
            <?php if (setting('address') !== ''): ?><p class="foot-note"><?= e(setting('address')) ?></p><?php endif; ?>
        </div>
        <div class="foot-col">
            <h4>Shop</h4>
            <?php foreach ($cats ?? [] as $c): ?><a href="<?= e(url('category.php?c=' . urlencode($c['slug']))) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
            <a href="<?= e(url('category.php')) ?>">Everything</a>
        </div>
        <div class="foot-col">
            <h4>Help</h4>
            <a href="<?= e(url('track.php')) ?>">Track my order</a>
            <a href="<?= e(url('cart.php')) ?>">My cart</a>
            <a href="<?= e(url('account.php')) ?>">My account</a>
            <p class="foot-note"><?= e(setting('delivery_note')) ?></p>
        </div>
    </div>

    <?php if ($socials): ?>
    <div class="container foot-social">
        <span class="foot-social-h">Follow us</span>
        <ul class="soc">
            <?php foreach ($socials as $s): $lab = social_label($s); ?>
            <li><a class="soc-a" href="<?= e($s['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($lab) ?>" title="<?= e($lab) ?>"><?= social_icon($s) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="foot-bottom"><div class="container foot-bottom-in"><span>&copy; <?= date('Y') ?> <?= e(setting('store_name')) ?></span><a href="<?= e(url('admin/')) ?>">Owner login</a></div></div>
</footer>

<?php if (setting('cookie_notice', '1') === '1'): ?>
<div class="cookie" id="cookieBar" hidden>
    <p>We use a cookie to keep your cart working while you shop.</p>
    <button type="button" class="btn btn-gold btn-sm" id="cookieOk">Got it</button>
</div>
<?php endif; ?>

<?php if ($wa_number): ?>
<a class="wa-float" href="<?= e(wa_url($wa_number, 'Hello ' . setting('store_name') . ', I have a question.')) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
    <svg width="30" height="30" viewBox="0 0 32 32" fill="#fff"><path d="M16.003 3C9.373 3 4 8.373 4 15c0 2.36.69 4.56 1.87 6.42L4 29l7.76-1.83A11.94 11.94 0 0 0 16.003 27C22.63 27 28 21.627 28 15S22.63 3 16.003 3zm0 21.9c-1.78 0-3.44-.5-4.85-1.37l-.35-.21-4.6 1.09 1.14-4.47-.23-.37A9.86 9.86 0 0 1 6.1 15c0-5.46 4.44-9.9 9.9-9.9S25.9 9.54 25.9 15s-4.44 9.9-9.9 9.9zm5.43-7.41c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.47-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.14-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.08 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35z"/></svg>
</a>
<?php endif; ?>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
