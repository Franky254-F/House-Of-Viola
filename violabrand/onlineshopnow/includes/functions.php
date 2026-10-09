<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('shop_' . substr(md5(__DIR__), 0, 8));   // unique per project, so XAMPP sites never share a login
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}

/** Adds tables/columns introduced by newer versions to a database installed earlier. Runs once per visit session. */
function ensure_schema(): void {
    if (!empty($_SESSION['schema_v3'])) return;
    try {
        $pdo = db();
        $add = function (string $table, string $col, string $def) use ($pdo) {
            if (!$pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($col))->fetch())
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
        };
        $add('products', 'sizes', "VARCHAR(255) NOT NULL DEFAULT ''");
        $add('products', 'colors', "VARCHAR(255) NOT NULL DEFAULT ''");
        $add('order_items', 'variant', "VARCHAR(120) NOT NULL DEFAULT ''");
        $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(80) NOT NULL,
            email VARCHAR(120) NULL UNIQUE,
            phone VARCHAR(20) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $add('orders', 'customer_id', 'INT NULL');
        $_SESSION['schema_v3'] = 1;
    } catch (Throwable $e) { /* not installed yet: the installer creates everything */ }
}

/** An error whose message is safe to show to the visitor. */
class UserError extends Exception {}

const ORDER_STATUSES = ['New', 'Confirmed', 'Dispatched', 'Delivered', 'Cancelled'];
/** Social networks the owner can add. "other" lets them type any name (shown as its first letter). */
const SOCIAL_NETWORKS = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'x' => 'X (Twitter)',
    'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'other' => 'Other (type a name)'];
ensure_schema();

// ------------------------------------------------------------ basics ----
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string {
    if (preg_match('#^https?://#i', $path)) return $path;      // pictures kept on an image service are full web addresses
    return BASE_URL . '/' . ltrim($path, '/');
}
function full_url(string $path = ''): string {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($path);
}
/** The address customers should use. Uses the "Public store link" from settings when set (needed once the shop is online). */
function public_base(): string {
    $p = trim(setting('public_url'));
    if ($p !== '') {
        if (!preg_match('#^https?://#i', $p)) $p = 'https://' . $p;
        return rtrim($p, '/');
    }
    return rtrim(full_url(''), '/');
}
function share_url(string $path = ''): string { return public_base() . '/' . ltrim($path, '/'); }
function redirect(string $path): void {
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}
function back(string $fallback): void {
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '') ) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function show_flashes(): void {
    foreach ($_SESSION['flash'] ?? [] as [$type, $msg]) {
        echo '<div class="alert alert-' . e($type) . '">' . e($msg) . '</div>';
    }
    unset($_SESSION['flash']);
}

// -------------------------------------------------------------- CSRF ----
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

// ---------------------------------------------------------- settings ----
function default_settings(): array {
    return [
        'store_name' => 'Online Shop Now', 'tagline' => 'Clothes, shoes and bags you will love',
        'whatsapp' => '', 'country_code' => '254', 'phone' => '', 'email' => '', 'address' => '',
        'currency' => 'KSh', 'announcement' => 'Order in minutes, confirm on WhatsApp | New styles every week | Delivery countrywide',
        'accent_color' => '#d4a853', 'bg_color' => '#0c0c0d', 'public_url' => '', 'cookie_notice' => '1',
        'hero_image' => '', 'hero_eyebrow' => 'New collection', 'hero_title' => 'Dress well. *Order easy.*',
        'hero_text' => 'Clothes, shoes and bags for every day and every occasion. Pick your size, order here, and confirm with us on WhatsApp.',
        'hero_btn1_text' => 'Shop the collection', 'hero_btn1_link' => 'category.php',
        'hero_btn2_text' => 'On sale', 'hero_btn2_link' => 'category.php?flash=1', 'hero_overlay' => '55',
        'delivery_fee' => '200', 'free_delivery_over' => '5000',
        'delivery_note' => 'Delivery in 1 to 3 working days. We confirm every order on WhatsApp before dispatch.',
        'footer_about' => 'Everyday clothes, shoes and bags, picked with care. Order online, confirm on WhatsApp, pay when it arrives.',
        'facebook' => '', 'instagram' => '', 'tiktok' => '', 'socials' => '', 'logo' => '',
        'flash_title' => 'On sale', 'flash_end' => '',
    ];
}
function settings(): array {
    static $s = null;
    if ($s === null) {
        $s = [];
        try { foreach (db()->query('SELECT k, v FROM settings') as $r) $s[$r['k']] = $r['v']; } catch (Throwable $e) {}
        $s += default_settings();
    }
    return $s;
}
function setting(string $k, string $d = ''): string { $s = settings(); return (string)($s[$k] ?? $d); }
function safe_color(string $c, string $fallback): string { return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : $fallback; }
/** Dark text on light colours, white text on dark colours (so buttons stay readable whatever accent is chosen). */
function on_color(string $hex): string {
    $h = ltrim($hex, '#');
    $l = (0.299 * hexdec(substr($h, 0, 2)) + 0.587 * hexdec(substr($h, 2, 2)) + 0.114 * hexdec(substr($h, 4, 2))) / 255;
    return $l > 0.55 ? '#17120a' : '#ffffff';
}
/** "Dress well. *Order easy.*"  ->  safe HTML with the starred words in italic gold. */
function hero_title_html(string $t): string {
    return preg_replace('/\*(.+?)\*/u', '<em>$1</em>', e($t));
}
function hero_link(string $l): string {
    $l = trim($l);
    if ($l === '') return url('category.php');
    return preg_match('#^(https?://|/)#i', $l) ? $l : url($l);
}

// ----------------------------------------------------------- helpers ----
function money($n): string {
    $n = (float)$n;
    return setting('currency', 'KSh') . ' ' . number_format($n, floor($n) == $n ? 0 : 2);
}
function normalize_phone(string $raw, string $cc = ''): string {
    $cc = $cc !== '' ? $cc : setting('country_code', '254');
    $d = preg_replace('/\D+/', '', $raw);
    if ($d === '') return '';
    if (strpos($d, '00') === 0) $d = substr($d, 2);
    if (strpos($d, $cc) === 0 && strlen($d) >= strlen($cc) + 9) return $d;
    if ($d[0] === '0') return $cc . substr($d, 1);
    if (strlen($d) <= 9) return $cc . $d;
    return $d;
}
function wa_url(string $number, string $text = ''): string {
    return 'https://wa.me/' . $number . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}
function slugify(string $s): string {
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? $s : 'item';
}
function unique_slug(string $table, string $base, int $ignoreId = 0): string {
    $slug = slugify($base); $try = $slug; $i = 2;
    while (true) {
        $st = db()->prepare("SELECT id FROM `$table` WHERE slug = ? AND id <> ?");
        $st->execute([$try, $ignoreId]);
        if (!$st->fetch()) return $try;
        $try = $slug . '-' . $i++;
    }
}
function discount_pct(array $p): int {
    return ($p['old_price'] && $p['old_price'] > $p['price']) ? (int)round(($p['old_price'] - $p['price']) / $p['old_price'] * 100) : 0;
}
function img_url(?string $path): string { return $path ? url($path) : url('assets/img/placeholder.svg'); }
function pager(int $page, int $pages, array $params = []): string {
    if ($pages <= 1) return '';
    unset($params['page']);
    $out = '<nav class="pager">';
    for ($i = 1; $i <= $pages; $i++) {
        $out .= '<a class="' . ($i === $page ? 'on' : '') . '" href="?' . e(http_build_query($params + ['page' => $i])) . '">' . $i . '</a>';
    }
    return $out . '</nav>';
}
function status_class(string $s): string { return 'st-' . strtolower($s); }
/** "S, M, L" typed by the owner  ->  ['S','M','L'] */
function product_options(?string $csv): array {
    $out = [];
    foreach (explode(',', (string)$csv) as $v) {
        $v = trim(preg_replace('/\s+/', ' ', $v));
        if ($v !== '' && mb_strlen($v) <= 20 && !in_array($v, $out, true)) $out[] = $v;
        if (count($out) >= 30) break;
    }
    return $out;
}
function variant_label(string $size, string $color): string {
    $parts = [];
    if ($size !== '')  $parts[] = 'Size ' . $size;
    if ($color !== '') $parts[] = $color;
    return implode(', ', $parts);
}

// --------------------------------------------------------- customers ----
/** The signed-in customer (or null). */
function customer(): ?array {
    static $c = false;
    if ($c === false) {
        $c = null;
        if (!empty($_SESSION['customer_id'])) {
            $st = db()->prepare('SELECT id, name, email, phone, created_at FROM customers WHERE id = ?');
            $st->execute([(int)$_SESSION['customer_id']]);
            $c = $st->fetch() ?: null;
            if (!$c) unset($_SESSION['customer_id']);
        }
    }
    return $c;
}
function require_customer(string $next = 'account.php'): array {
    $c = customer();
    if (!$c) { flash('info', 'Please sign in to continue.'); redirect('login.php?next=' . urlencode($next)); }
    return $c;
}
/** Only ever redirect to one of our own pages (no slashes, no web addresses). */
function safe_next(?string $n, string $default = 'account.php'): string {
    $n = (string)$n;
    return preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%.-]*)?$/', $n) ? $n : $default;
}
function first_name(string $full): string { $p = preg_split('/\s+/u', trim($full)); return $p[0] ?? $full; }

// ------------------------------------------------------------- brand ----
/** "Online Shop Now" -> "OS" for the emblem shown when no logo has been uploaded. */
function monogram(string $name): string {
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$words) return 'S';
    if (count($words) === 1) return mb_strtoupper(mb_substr($words[0], 0, 2));
    return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
}
/** Logo (or a monogram emblem) AND the shop name, always together. */
function brand_html(string $cls = ''): string {
    $name = setting('store_name');
    $mark = setting('logo') !== ''
        ? '<img class="brand-logo" src="' . e(url(setting('logo'))) . '" alt="">'
        : '<span class="brand-mark" aria-hidden="true"><span class="brand-mono">' . e(monogram($name)) . '</span></span>';
    return '<a class="brand ' . e($cls) . '" href="' . e(url('index.php')) . '" aria-label="' . e($name) . ' home">' . $mark . '<span class="brand-name">' . e($name) . '</span></a>';
}
function theme_css(): string {
    $gold = safe_color(setting('accent_color'), '#d4a853');
    return '<style>:root{--gold:' . $gold . ';--bg:' . safe_color(setting('bg_color'), '#0c0c0d') . ';--on-gold:' . on_color($gold) . '}</style>';
}

// ------------------------------------------------------ social + email ----
/** Social links for the footer: [['net'=>..., 'name'=>..., 'url'=>...], ...] (newer list first, else the old three fields). */
function social_links(): array {
    $raw = setting('socials');
    $list = [];
    if ($raw !== '') {
        $d = json_decode($raw, true);
        if (is_array($d)) foreach ($d as $x) {
            $u = is_array($x) && isset($x['url']) && is_string($x['url']) ? $x['url'] : '';
            if (!preg_match('#^https?://#i', $u)) continue;
            $net = (string)($x['net'] ?? 'other');
            $list[] = ['net' => isset(SOCIAL_NETWORKS[$net]) ? $net : 'other', 'name' => (string)($x['name'] ?? ''), 'url' => $u];
        }
        return $list;
    }
    foreach (['facebook', 'instagram', 'tiktok'] as $k) {
        $u = trim(setting($k));
        if ($u !== '') $list[] = ['net' => $k, 'name' => '', 'url' => preg_match('#^https?://#i', $u) ? $u : 'https://' . $u];
    }
    return $list;
}
function social_label(array $s): string {
    if ($s['net'] === 'other') return $s['name'] !== '' ? $s['name'] : 'Link';
    return SOCIAL_NETWORKS[$s['net']] ?? 'Link';
}
/** Inline icon (no internet needed). Unknown networks show the first letter of the name the owner typed. */
function social_icon(array $s): string {
    $svg = fn(string $inner) => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
    switch ($s['net']) {
        case 'facebook':  return $svg('<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>');
        case 'instagram': return $svg('<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r=".9" fill="currentColor" stroke="none"/>');
        case 'tiktok':    return $svg('<path d="M14 3v12.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 3c.5 2.8 2.4 4.5 5.5 4.8"/>');
        case 'x':         return $svg('<path d="M4 4l16 16M20 4L4 20"/>');
        case 'youtube':   return $svg('<path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"/>');
        case 'linkedin':  return $svg('<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/>');
        case 'whatsapp':  return $svg('<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>');
        case 'telegram':  return $svg('<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>');
    }
    return '<span class="soc-letter" aria-hidden="true">' . e(mb_strtoupper(mb_substr(social_label($s), 0, 1))) . '</span>';
}
/** Link that takes a customer to the owner's mailbox. Common webmail opens in a new tab; otherwise their mail app opens. Returns [href, newTab]. */
function email_link(string $email): array {
    $to = rawurlencode($email);
    $dom = strtolower((string)substr((string)strrchr($email, '@'), 1));
    $subject = rawurlencode('Enquiry from ' . setting('store_name') . ' website');
    if (in_array($dom, ['gmail.com', 'googlemail.com'], true))
        return ['https://mail.google.com/mail/?view=cm&fs=1&to=' . $to . '&su=' . $subject, true];
    if (in_array($dom, ['outlook.com', 'hotmail.com', 'live.com', 'msn.com'], true))
        return ['https://outlook.live.com/mail/0/deeplink/compose?to=' . $to . '&subject=' . $subject, true];
    if (in_array($dom, ['yahoo.com', 'ymail.com'], true))
        return ['https://compose.mail.yahoo.com/?to=' . $to . '&subject=' . $subject, true];
    return ['mailto:' . $email . '?subject=' . $subject, false];
}

// ------------------------------------------------- online picture storage ----
// Hosts such as Render erase files saved on the server whenever it restarts. If CLOUDINARY_URL is set
// (cloudinary://API_KEY:API_SECRET@CLOUD_NAME) pictures are kept at Cloudinary instead.
function cloud_config(): ?array {
    $u = getenv('CLOUDINARY_URL');
    if (!$u || !preg_match('#^cloudinary://([^:]+):([^@]+)@(.+)$#', trim($u), $m)) return null;
    return ['key' => $m[1], 'secret' => $m[2], 'cloud' => $m[3]];
}
function cloud_sign(array $params, string $secret): string {
    ksort($params);
    $pairs = [];
    foreach ($params as $k => $v) $pairs[] = $k . '=' . $v;
    return sha1(implode('&', $pairs) . $secret);
}
function cloud_upload(string $file, string $folder): string {
    $c = cloud_config();
    if (!$c || !function_exists('curl_init')) throw new UserError('Picture storage is not set up correctly.');
    $p = ['folder' => 'onlineshopnow/' . $folder, 'timestamp' => time()];
    $ch = curl_init('https://api.cloudinary.com/v1_1/' . rawurlencode($c['cloud']) . '/image/upload');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90,
        CURLOPT_POSTFIELDS => $p + ['file' => new CURLFile($file), 'api_key' => $c['key'], 'signature' => cloud_sign($p, $c['secret'])],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $j = is_string($res) ? json_decode($res, true) : null;
    if (empty($j['secure_url'])) throw new UserError('The picture could not be stored online: ' . ($j['error']['message'] ?? 'no answer from the image service') . '.');
    return $j['secure_url'];
}
function cloud_delete(string $url): void {
    $c = cloud_config();
    if (!$c || !function_exists('curl_init') || !preg_match('#/upload/(?:v\d+/)?(.+)\.[A-Za-z0-9]+$#', $url, $m)) return;
    $p = ['public_id' => $m[1], 'timestamp' => time()];
    $ch = curl_init('https://api.cloudinary.com/v1_1/' . rawurlencode($c['cloud']) . '/image/destroy');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_POSTFIELDS => $p + ['api_key' => $c['key'], 'signature' => cloud_sign($p, $c['secret'])]]);
    curl_exec($ch);
    curl_close($ch);
}

// ----------------------------------------------------------- uploads ----
/** Normalise PHP's awkward multi-file array into a list of single-file arrays. */
function files_list(string $key): array {
    if (empty($_FILES[$key]) || !is_array($_FILES[$key]['name'])) {
        return (!empty($_FILES[$key]) && ($_FILES[$key]['error'] ?? 4) !== UPLOAD_ERR_NO_FILE) ? [$_FILES[$key]] : [];
    }
    $out = [];
    foreach ($_FILES[$key]['name'] as $i => $n) {
        if (($_FILES[$key]['error'][$i] ?? 4) === UPLOAD_ERR_NO_FILE) continue;
        $out[] = ['name' => $n, 'type' => $_FILES[$key]['type'][$i], 'tmp_name' => $_FILES[$key]['tmp_name'][$i],
                  'error' => $_FILES[$key]['error'][$i], 'size' => $_FILES[$key]['size'][$i]];
    }
    return $out;
}
/** Validate and store one uploaded picture. Returns its path (e.g. "uploads/products/ab12.jpg") or null if none was chosen. */
function save_image(?array $file, string $folder, int $maxSide = 1400): ?string {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new UserError('The picture could not be uploaded (it may be too large). Try a smaller image.');
    if ($file['size'] > 10 * 1024 * 1024) throw new UserError('Each picture must be under 10 MB.');
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) throw new UserError('Please upload a JPG, PNG, WEBP or GIF picture.');
    $ext = $types[$info[2]];
    $dir = __DIR__ . '/../uploads/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new UserError('The uploads folder cannot be created. Check folder permissions.');
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    $saved = false;

    // Shrink big phone photos so pages load fast (keeps the original look and orientation).
    if (max($info[0], $info[1]) > $maxSide && in_array($ext, ['jpg', 'png', 'webp'], true) && function_exists('imagecreatefromstring')) {
        $src = @imagecreatefromstring((string)file_get_contents($file['tmp_name']));
        if ($src) {
            if ($ext === 'jpg' && function_exists('exif_read_data')) {
                $ex = @exif_read_data($file['tmp_name']);
                $o = $ex['Orientation'] ?? 1;
                $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
                if ($angle) { $rot = imagerotate($src, $angle, 0); if ($rot) { imagedestroy($src); $src = $rot; } }
            }
            $w = imagesx($src); $h = imagesy($src); $scale = $maxSide / max($w, $h);
            $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            if ($ext !== 'jpg') { imagealphablending($dst, false); imagesavealpha($dst, true); }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            if ($ext === 'jpg') $saved = imagejpeg($dst, $dest, 85);
            elseif ($ext === 'png') $saved = imagepng($dst, $dest, 6);
            elseif (function_exists('imagewebp')) $saved = imagewebp($dst, $dest, 85);
            imagedestroy($src); imagedestroy($dst);
        }
    }
    if (!$saved && !move_uploaded_file($file['tmp_name'], $dest)) throw new UserError('The picture could not be saved. Check folder permissions on "uploads".');
    if (cloud_config()) {                                    // keep the picture online instead of on this server
        try { return cloud_upload($dest, $folder); } finally { @unlink($dest); }
    }
    return 'uploads/' . $folder . '/' . $name;
}
function delete_upload(?string $rel): void {
    if (!$rel) return;
    if (preg_match('#^https?://#i', $rel)) { cloud_delete($rel); return; }
    $base = realpath(__DIR__ . '/../uploads');
    $full = realpath(__DIR__ . '/../' . $rel);
    if ($base && $full && strpos($full, $base) === 0 && is_file($full)) @unlink($full);
}

// -------------------------------------------------------------- cart ----
// The cart holds lines: key => ['id'=>product, 'size'=>..., 'color'=>..., 'qty'=>...]
function cart_count(): int {
    $n = 0;
    foreach ($_SESSION['cart'] ?? [] as $l) $n += (int)($l['qty'] ?? 0);
    return $n;
}
/** Returns [items, subtotal]. Prices always come from the database, never from the browser. */
function cart_items(): array {
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) return [[], 0.0];
    $ids = array_values(array_unique(array_map(fn($l) => (int)($l['id'] ?? 0), $cart)));
    $st = db()->prepare('SELECT * FROM products WHERE active = 1 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $st->execute($ids);
    $rows = []; $left = [];
    foreach ($st as $p) { $rows[(int)$p['id']] = $p; $left[(int)$p['id']] = max(0, (int)$p['stock']); }
    $items = []; $sub = 0.0; $clean = [];
    foreach ($cart as $key => $l) {
        $id = (int)($l['id'] ?? 0);
        if (!isset($rows[$id]) || $left[$id] <= 0) continue;                 // removed or sold out
        $qty = max(1, min((int)$l['qty'], $left[$id], 99));
        $left[$id] -= $qty;                                                  // sizes of one product share its stock
        $size = (string)($l['size'] ?? ''); $color = (string)($l['color'] ?? '');
        $clean[$key] = ['id' => $id, 'size' => $size, 'color' => $color, 'qty' => $qty];
        $p = $rows[$id];
        $p['key'] = $key; $p['size'] = $size; $p['color'] = $color; $p['variant'] = variant_label($size, $color);
        $p['qty'] = $qty; $p['line'] = $qty * (float)$p['price'];
        $items[] = $p; $sub += $p['line'];
    }
    $_SESSION['cart'] = $clean;
    return [$items, $sub];
}
/** Adds one line to the cart. Returns [ok, message, page to go to on failure]. */
function cart_add(int $id, int $qty, string $size, string $color): array {
    $qty = max(1, min(99, $qty));
    $st = db()->prepare('SELECT id, name, slug, stock, sizes, colors FROM products WHERE id = ? AND active = 1'); $st->execute([$id]);
    $p = $st->fetch();
    if (!$p) return [false, 'That item is no longer available.', 'index.php'];
    $page = 'product.php?p=' . urlencode($p['slug']);
    if ((int)$p['stock'] <= 0) return [false, $p['name'] . ' is sold out.', $page];
    $sizes = product_options($p['sizes']); $colors = product_options($p['colors']);
    $size  = $sizes  ? trim($size)  : '';
    $color = $colors ? trim($color) : '';
    if ($sizes && !in_array($size, $sizes, true))    return [false, 'Please choose a size.', $page];
    if ($colors && !in_array($color, $colors, true)) return [false, 'Please choose a colour.', $page];
    // Every size of one product shares the same stock.
    $inOther = 0; $key = $id . '-' . substr(md5($size . '|' . $color), 0, 6);
    foreach ($_SESSION['cart'] ?? [] as $k => $l) if ((int)$l['id'] === $id && $k !== $key) $inOther += (int)$l['qty'];
    $room = (int)$p['stock'] - $inOther;
    if ($room <= 0) return [false, 'You already have all available pieces of ' . $p['name'] . ' in your cart.', 'cart.php'];
    $have = (int)($_SESSION['cart'][$key]['qty'] ?? 0);
    $_SESSION['cart'][$key] = ['id' => $id, 'size' => $size, 'color' => $color, 'qty' => min($have + $qty, $room, 99)];
    return [true, 'Added to cart: ' . $p['name'], 'cart.php'];
}
function delivery_fee(float $subtotal): float {
    $fee = (float)setting('delivery_fee', '0'); $free = (float)setting('free_delivery_over', '0');
    return ($free > 0 && $subtotal >= $free) ? 0.0 : $fee;
}

// ------------------------------------------------------------ orders ----
function order_message(array $o, array $items): string {
    $l = ['Hello ' . setting('store_name') . ', I have placed an order on your website.', '*Order: ' . $o['order_no'] . '*', ''];
    $i = 1;
    foreach ($items as $it) $l[] = $i++ . '. ' . $it['name'] . (!empty($it['variant']) ? ' (' . $it['variant'] . ')' : '') . ' x' . $it['qty'] . ' = ' . money($it['price'] * $it['qty']);
    $l[] = '';
    $l[] = 'Subtotal: ' . money($o['subtotal']);
    $l[] = 'Delivery: ' . ($o['delivery_fee'] > 0 ? money($o['delivery_fee']) : 'Free');
    $l[] = '*Total: ' . money($o['total']) . '*';
    $l[] = '';
    $l[] = 'Name: ' . $o['customer_name'];
    $l[] = 'Phone: ' . $o['phone'];
    $l[] = 'Location: ' . $o['location'];
    if (!empty($o['address'])) $l[] = 'Address: ' . $o['address'];
    if (!empty($o['notes']))   $l[] = 'Notes: ' . $o['notes'];
    return implode("\n", $l);
}
/** Change an order's status and keep stock numbers right (cancelling puts items back). */
function set_order_status(int $orderId, string $new): void {
    if (!in_array($new, ORDER_STATUSES, true)) return;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE'); $st->execute([$orderId]);
        $old = $st->fetchColumn();
        if ($old === false || $old === $new) { $pdo->commit(); return; }
        $items = $pdo->prepare('SELECT product_id, qty FROM order_items WHERE order_id = ?'); $items->execute([$orderId]);
        foreach ($items as $it) {
            if (!$it['product_id']) continue;
            if ($new === 'Cancelled' && $old !== 'Cancelled')
                $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?')->execute([$it['qty'], $it['product_id']]);
            elseif ($old === 'Cancelled' && $new !== 'Cancelled')
                $pdo->prepare('UPDATE products SET stock = GREATEST(stock - ?, 0) WHERE id = ?')->execute([$it['qty'], $it['product_id']]);
        }
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$new, $orderId]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

// ----------------------------------------------------- product card ----
function product_card(array $p): void {
    $off    = discount_pct($p);
    $out    = (int)$p['stock'] <= 0;
    $link   = url('product.php?p=' . urlencode($p['slug']));
    $sizes  = product_options($p['sizes'] ?? '');
    $colors = product_options($p['colors'] ?? '');
    ?>
    <article class="card">
        <a class="card-img" href="<?= e($link) ?>">
            <img src="<?= e(img_url($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
            <?php if ($off): ?><span class="tag tag-sale">-<?= $off ?>%</span><?php endif; ?>
            <?php if ($out): ?><span class="tag tag-out">Sold out</span><?php endif; ?>
        </a>
        <div class="card-body">
            <a class="card-name" href="<?= e($link) ?>"><?= e($p['name']) ?></a>
            <?php if ($sizes): ?><div class="card-sizes"><?= e(implode(', ', array_slice($sizes, 0, 6))) ?><?= count($sizes) > 6 ? '...' : '' ?></div><?php endif; ?>
            <div class="price"><?= e(money($p['price'])) ?>
                <?php if ($off): ?><s class="old"><?= e(money($p['old_price'])) ?></s><?php endif; ?></div>
            <?php if ($out): ?>
                <span class="btn btn-ghost btn-block btn-sm is-off">Sold out</span>
            <?php elseif ($sizes || $colors): ?>
                <a class="btn btn-ghost btn-block btn-sm" href="<?= e($link) ?>">Choose <?= $sizes ? 'size' : 'colour' ?></a>
            <?php else: ?>
                <form method="post" action="<?= e(url('cart.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-ghost btn-block btn-sm">Add to cart</button>
                </form>
            <?php endif; ?>
        </div>
    </article>
    <?php
}
