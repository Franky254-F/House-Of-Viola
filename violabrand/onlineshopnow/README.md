# Online Shop Now: clothing and fashion store with WhatsApp ordering (XAMPP)

A black-and-gold online shop for **clothes, shoes, bags and accessories**. Customers pick a size and colour, place an order, and send it to
your **WhatsApp** with one tap. Every order is also saved in your admin panel. You can edit everything yourself.

## Install on XAMPP (5 minutes)

1. Open the **XAMPP Control Panel** and **Start** both **Apache** and **MySQL**.
2. Extract the zip. Copy the `onlineshopnow` folder into `C:\xampp\htdocs\` so that this file exists:
   `C:\xampp\htdocs\onlineshopnow\install.php` (not `onlineshopnow\onlineshopnow\install.php`).
3. Open **http://localhost/onlineshopnow/install.php**, fill in your store name, WhatsApp number, admin username and password, then **Install store**.
4. Store: **http://localhost/onlineshopnow/**   Admin panel: **http://localhost/onlineshopnow/admin/**

Needs PHP 7.4 or newer (every current XAMPP has it).
**Already installed an earlier version?** Just copy the new files over the old ones. The database upgrades itself.
Old sample items can be deleted under *Products*.

## Sending your store link to customers

- **Admin Dashboard has a "Your store link" box** with **Copy link** and **Send on WhatsApp** buttons.
- The store footer and every product page also have **Copy link** and **Share on WhatsApp**.
- `localhost` links only open on your own computer. To share with real customers, put the store online (any PHP and MySQL web host),
  then type the live address into **Store settings, Public store link**. Every share button then uses that address.
- For phones on the same Wi-Fi, the dashboard also shows a **Wi-Fi link** to copy.
- For a temporary public link while testing, a free tunnel tool such as ngrok can expose your XAMPP site. Paste the address it gives you into *Public store link*.

## What you can edit (admin panel)

| Page | What you do there |
|---|---|
| **Dashboard** | Your store link, new orders, sales this month, items running low. |
| **Customers** | Everyone who created an account: contact details, number of orders, total spent. WhatsApp them, set a temporary password if they forgot theirs, or delete an account (their past orders are kept). |
| **Orders** | Every order with the size and colour chosen. Change status. One tap to message the customer on WhatsApp. A beep and alert appear when a new order arrives. |
| **Products** | Add, edit, hide, delete. Main picture plus extra pictures. Price, old price (shows a % off), stock, **sizes**, **colours**, "Favourite" and "On sale". |
| **Categories** | Women, Men, Kids, Shoes, Bags, Accessories, or your own. Rename, reorder, hide. |
| **Hero banner** | The big picture at the top of your home page. Upload it, write the headline and button words (put *stars* around words to make them italic gold), set how dark the picture is, and see a live preview. |
| **Promo banners** | Framed pictures shown in a row further down the home page. |
| **Store settings** | Name, logo, accent and background colours (default black and gold), cookie notice, public store link, WhatsApp number, delivery fee, free-delivery amount, announcement, sale countdown, footer, social links. |

Tips: upright (portrait) pictures look best. Use the one-click size buttons (Clothes, Shoes, Kids) on the product form.
All sizes of one item share its stock number.

## Customer accounts

- Customers can **Create account** (name, phone, optional email, password) and **Sign in** with their email or phone number.
- When signed in, checkout is pre-filled, every order they place appears under **My account** (with status), and they can edit their details or change their password.
- **Sign out** is in the header (and in the menu on phones) and on the account page.
- **Adding to cart needs an account.** A visitor who taps Add to cart or Order on WhatsApp without being signed in is sent to the sign in / sign up page. After signing in, the item they chose is added automatically.
- The sign in, sign up and admin sign in pages share one dark-and-gold split-screen design. If you upload a hero banner, it appears on the left side of these pages too.
- Forgot password? Open **Admin > Customers > Reset password** and send the customer the temporary password (for example on WhatsApp).

## Logo and name

The header always shows **the logo and the shop name side by side**. If you have not uploaded a logo, a gold monogram emblem made from the shop name (for example "OS") is shown instead. Upload a logo in **Store settings** to replace the emblem. The name stays.

## How an order works

1. Customer picks a size and colour, adds to cart, and checks out (name, phone, location).
2. The order is saved (like `ORD-240601-AB12C`) and stock goes down.
3. WhatsApp opens with the full order typed out (items, sizes, colours, total, address), ready to send to your number.
4. You see it in **Orders**, confirm on WhatsApp, and update the status. Customers can follow it on **Track my order**.

Cancelling an order puts the items back in stock.

## Troubleshooting

- **"Not Found"**: the folder is in the wrong place or named differently. The link must match the folder name exactly.
- **"The store database is not ready"**: MySQL is not running, or `config.php` has the wrong details (XAMPP default: user `root`, no password).
- **A picture will not upload**: raise `upload_max_filesize` and `post_max_size` in `php.ini` (for example 20M) and restart Apache.
- **Start again**: in phpMyAdmin drop the `brand_shop` database and delete `install.lock`.
- **Forgot the admin password**: open `config.php`, change `env_or('ALLOW_REINSTALL', '0')` to `'1'`, run `install.php` again with the same username and a new password (products and orders are kept), then change it back to `'0'`.

## Folder guide

`index.php` home, `category.php` browse and search, `product.php`, `cart.php`, `checkout.php`, `order_success.php`, `track.php`,
`admin/` control panel, `includes/` shared code, `assets/` styles and scripts, `uploads/` your pictures.

## Menu, footer and social icons

- The header shows the logo and shop name, the search bar, the cart and a **menu button (three lines, top right)**. The menu slides out and holds Shop, every category, Sale, Track order, and Sign in / My account / Sign out.
- **Store settings > Social media:** add Facebook, Instagram, TikTok, X, YouTube, LinkedIn, WhatsApp, Telegram, or **Other** (any name). Each appears as a round icon below the About, Shop and Help columns in the footer. Add or remove as many as you like.
- The email address you enter in Store settings appears under **Contact** (below About) as a link. Gmail, Outlook and Yahoo addresses open their webmail compose page in a new tab; other addresses open the customer's mail app.

## Putting it online

Netlify cannot run PHP. See **DEPLOY.md** for step-by-step instructions for a free PHP + MySQL host, for Render (Docker), and for paid hosting.
