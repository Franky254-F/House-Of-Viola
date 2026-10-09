<?php
require __DIR__ . '/_init.php'; require_admin();

$text_keys = ['store_name', 'tagline', 'announcement', 'phone', 'email', 'address', 'currency', 'country_code', 'delivery_note',
              'footer_about', 'flash_title', 'flash_end', 'public_url'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $new = [];
    foreach ($text_keys as $k) $new[$k] = trim((string)($_POST[$k] ?? ''));
    $new['whatsapp']           = normalize_phone((string)($_POST['whatsapp'] ?? ''), preg_replace('/\D+/', '', $new['country_code']) ?: '254');
    $new['delivery_fee']       = (string)(num_or_null($_POST['delivery_fee'] ?? '') ?? 0);
    $new['free_delivery_over'] = (string)(num_or_null($_POST['free_delivery_over'] ?? '') ?? 0);
    $new['accent_color']       = safe_color(trim((string)($_POST['accent_color'] ?? '')), '#d4a853');
    $new['bg_color']           = safe_color(trim((string)($_POST['bg_color'] ?? '')), '#0c0c0d');
    $new['cookie_notice']      = empty($_POST['cookie_notice']) ? '0' : '1';
    // Social media links (any number). Each shows as an icon in the footer of the store.
    $list = [];
    $nets = (array)($_POST['social_net'] ?? []); $names = (array)($_POST['social_name'] ?? []);
    foreach ((array)($_POST['social_url'] ?? []) as $i => $u) {
        $u = trim((string)$u);
        if ($u === '') continue;
        if (!preg_match('#^https?://#i', $u)) $u = 'https://' . $u;
        if (!filter_var($u, FILTER_VALIDATE_URL)) continue;
        $net = (string)($nets[$i] ?? 'other');
        if (!isset(SOCIAL_NETWORKS[$net])) $net = 'other';
        $list[] = ['net' => $net, 'name' => mb_substr(trim((string)($names[$i] ?? '')), 0, 30), 'url' => mb_substr($u, 0, 400)];
        if (count($list) >= 12) break;
    }
    $new['socials'] = json_encode($list, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($new['store_name'] === '') $new['store_name'] = 'My Store';
    if ($new['country_code'] === '') $new['country_code'] = '254';
    foreach (['public_url'] as $k)
        if ($new[$k] !== '' && !preg_match('#^https?://#i', $new[$k])) $new[$k] = 'https://' . $new[$k];

    try {
        $logo = save_image($_FILES['logo'] ?? null, 'site');
        $current = setting('logo');
        if ($logo) { delete_upload($current); $new['logo'] = $logo; }
        elseif (!empty($_POST['remove_logo'])) { delete_upload($current); $new['logo'] = ''; }
        $up = db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
        foreach ($new as $k => $v) $up->execute([$k, $v]);
        flash('success', 'Settings saved.');
        redirect('admin/settings.php');
    } catch (UserError $e) { flash('error', $e->getMessage()); }
}
$s = settings();
admin_header('Store settings', 'settings');
$in = function (string $k, string $label, string $hint = '', string $type = 'text') use ($s) {
    echo '<div class="field"><label for="' . e($k) . '">' . e($label) . ($hint ? ' <small>' . e($hint) . '</small>' : '') . '</label>'
       . '<input id="' . e($k) . '" type="' . e($type) . '" name="' . e($k) . '" value="' . e($s[$k] ?? '') . '"></div>';
};
?>
<form method="post" enctype="multipart/form-data">
<?= csrf_field() ?>
<div class="cols">
<div>
    <section class="box form-box"><h2>Your brand</h2>
        <?php $in('store_name', 'Store name'); $in('tagline', 'Tagline', '(shown on the home banner)'); $in('announcement', 'Top bar messages', '(separate several with | , leave blank to hide)'); ?>
        <div class="field"><label for="logo">Logo <small>(optional, replaces the store name in the header)</small></label>
            <input id="logo" type="file" name="logo" accept="image/*" data-preview="prevLogo">
            <div class="preview" id="prevLogo"><?php if ($s['logo']): ?><img src="<?= e(url($s['logo'])) ?>" alt=""><?php endif; ?></div>
            <?php if ($s['logo']): ?><label class="inline-check"><input type="checkbox" name="remove_logo" value="1"> Remove the current logo</label><?php endif; ?></div>
        <div class="grid2">
            <div class="field"><label for="accent_color">Accent colour <small>(buttons, prices, lines)</small></label><input id="accent_color" type="color" name="accent_color" value="<?= e(safe_color($s['accent_color'], '#d4a853')) ?>"></div>
            <div class="field"><label for="bg_color">Background colour <small>(keep it dark)</small></label><input id="bg_color" type="color" name="bg_color" value="<?= e(safe_color($s['bg_color'], '#0c0c0d')) ?>"></div>
        </div>
        <label class="inline-check"><input type="checkbox" name="cookie_notice" value="1" <?= $s['cookie_notice'] === '1' ? 'checked' : '' ?>> Show the small cookie notice at the bottom of the store</label>
    </section>
    <section class="box form-box"><h2>Sale section</h2>
        <?php $in('flash_title', 'Section title', '(e.g. On sale)'); $in('flash_end', 'Sale ends', '(optional: shows a countdown)', 'datetime-local'); ?>
        <small class="muted">Tick "On sale" on a product to put it in this section.</small>
    </section>
</div>
<div>
    <section class="box form-box"><h2>Store link, WhatsApp and contact</h2>
        <?php $in('public_url', 'Public store link', '(the web address customers use once your shop is online, e.g. https://mybrand.co.ke)', 'text'); ?>
        <?php $in('whatsapp', 'WhatsApp number for orders', '(e.g. 0712345678)', 'tel'); $in('country_code', 'Country code', '(Kenya is 254)'); $in('phone', 'Phone number shown to customers', '', 'tel'); $in('email', 'Email', '', 'email'); $in('address', 'Shop address'); ?>
    </section>
    <section class="box form-box"><h2>Prices and delivery</h2>
        <?php $in('currency', 'Currency label', '(e.g. KSh)'); $in('delivery_fee', 'Delivery fee', '(0 for free delivery)'); $in('free_delivery_over', 'Free delivery when order is over', '(0 to turn off)'); ?>
        <div class="field"><label for="delivery_note">Delivery message</label><textarea id="delivery_note" name="delivery_note" rows="3"><?= e($s['delivery_note']) ?></textarea></div>
    </section>
    <section class="box form-box"><h2>Footer text</h2>
        <div class="field"><label for="footer_about">About your store</label><textarea id="footer_about" name="footer_about" rows="3"><?= e($s['footer_about']) ?></textarea></div>
    </section>
    <section class="box form-box"><h2>Social media</h2>
        <p class="muted" style="margin-top:0">Add the pages you want customers to find. Each one appears as an <b>icon</b> at the bottom of your store. Add as many as you like.</p>
        <div id="socRows">
        <?php
        $rows = social_links();
        if (!$rows) $rows = [['net' => 'facebook', 'name' => '', 'url' => ''], ['net' => 'instagram', 'name' => '', 'url' => ''], ['net' => 'tiktok', 'name' => '', 'url' => '']];
        foreach ($rows as $r): ?>
            <div class="soc-row">
                <select name="social_net[]" aria-label="Network"><?php foreach (SOCIAL_NETWORKS as $k => $label): ?><option value="<?= e($k) ?>" <?= $r['net'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                <input name="social_name[]" value="<?= e($r['name']) ?>" placeholder="Name (only for Other)" maxlength="30" aria-label="Name for Other">
                <input name="social_url[]" value="<?= e($r['url']) ?>" placeholder="https://..." aria-label="Page link">
                <button type="button" class="btn btn-sm btn-danger soc-del" aria-label="Remove">&times;</button>
            </div>
        <?php endforeach; ?>
        </div>
        <template id="socTpl"><div class="soc-row">
            <select name="social_net[]" aria-label="Network"><?php foreach (SOCIAL_NETWORKS as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
            <input name="social_name[]" placeholder="Name (only for Other)" maxlength="30" aria-label="Name for Other">
            <input name="social_url[]" placeholder="https://..." aria-label="Page link">
            <button type="button" class="btn btn-sm btn-danger soc-del" aria-label="Remove">&times;</button>
        </div></template>
        <button type="button" class="btn btn-sm" id="socAdd">+ Add another social page</button>
        <p class="muted" style="margin-bottom:0;font-size:13px">Pick <b>Other</b> for any network not listed (for example Pinterest or Snapchat), type its name, and paste the link. It shows as a round badge with its first letter.</p>
    </section>
</div>
</div>
<div class="form-actions sticky"><button class="btn btn-primary btn-lg">Save settings</button></div>
</form>
<?php admin_footer(); ?>
