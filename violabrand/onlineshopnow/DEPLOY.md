# Putting Online Shop Now on the internet

**Netlify, Vercel, GitHub Pages and Cloudflare Pages cannot run this shop.** They only serve ready-made web pages.
This shop is a PHP + MySQL application (like WordPress), so it needs a host that runs PHP and gives you a MySQL database.

| Option | Cost | Good for | Watch out for |
|---|---|---|---|
| **A. Free PHP + MySQL host (InfinityFree style)** | Free | Easiest. Works like XAMPP: upload files, create a database. Pictures stay saved. | Free hosts have usage limits and no promises. Fine to start and test. |
| **B. Render (Docker) + free MySQL + Cloudinary** | Free | Developers who know Git. | Sleeps after 15 minutes idle (about a minute to wake). Customers are signed out and carts are emptied when it restarts. Needs 3 extra accounts. |
| **C. Cheap paid PHP hosting + your own domain** | Small yearly fee | A real business. Always on, your own address (for example `yourshop.co.ke`), email at your domain. | You pay. Many hosts accept M-Pesa. |

Whatever you choose, do this **first**: in `config.php` nothing needs editing for option B (it reads settings from the host). For A and C you edit the five `DB_` lines.

---------------------------------------------------------------------------------------------------

## Option A: free PHP + MySQL host (about 15 minutes)

These steps are the same on most hosts that give you a control panel (InfinityFree, and similar).

1. **Create the account and a hosting site.** Pick the free subdomain (for example `onlineshopnow.example-host.com`).
2. **Create a MySQL database** in the control panel (look for "MySQL Databases"). Write down the four things it shows you:
   the **host name** (it is usually NOT `localhost` on free hosts), the **database name**, the **user name**, and the **password**.
3. **Upload the shop.** Open the File Manager (or use FTP) and go into the `htdocs` folder (some hosts call it `public_html`).
   Upload **the contents** of the `onlineshopnow` folder, so that `index.php` sits directly inside `htdocs`. Delete the host's placeholder `index.html` if there is one.
4. **Edit `config.php`** (right-click, Edit) and put in your database details:
   ```php
   define('DB_HOST', env_or('DB_HOST', 'sql123.your-host.com'));   // the host name from step 2
   define('DB_NAME', env_or('DB_NAME', 'epiz_12345_shop'));
   define('DB_USER', env_or('DB_USER', 'epiz_12345'));
   define('DB_PASS', env_or('DB_PASS', 'your-database-password'));
   ```
   Leave `DB_PORT` as 3306 and `DB_SSL` as 0 unless your host says otherwise.
5. Open `https://your-site-address/install.php`, fill the form, click **Install store**. The installer creates all the tables.
6. Open `https://your-site-address/admin/`, sign in, and:
   * **Store settings > Public store link**: type your site address (for example `https://onlineshopnow.example-host.com`). Now every Copy link / WhatsApp share button uses it.
   * Upload your **Hero banner** and products.
7. **Delete `install.php`** from the server once you are done (the installer also switches itself off after the first admin exists).
8. Turn on the host's **free SSL certificate** (HTTPS) if it offers one, and then use `https://` in the Public store link.

**Backups:** in the host's phpMyAdmin choose your database, then **Export**, and keep the file. Also download the `uploads` folder now and then.

---------------------------------------------------------------------------------------------------

## Option B: Render (free) with a free MySQL database

Render runs the shop from the included `Dockerfile`. Know the limits before you start:

* A free Render web service **spins down after 15 minutes without visitors** and takes about a minute to wake up.
* **Files saved on the server are erased** whenever it restarts, redeploys or spins down. That is why product pictures must be stored at **Cloudinary** (set up below).
* Render has **no free MySQL**, so the database comes from another provider (Aiven's free MySQL below).
* PHP sessions are also files, so **customers are signed out and carts are emptied** when the service restarts.

### 1. Put the code on GitHub
Create a (private is fine) repository and upload the **contents** of the `onlineshopnow` folder, so that `Dockerfile` and `index.php` are at the top level.

### 2. Free MySQL (Aiven)
Create an Aiven account and a **free MySQL** service. When it is running, copy: **host, port, user, password, database name** (the default database is `defaultdb`).
Aiven's free MySQL has 1 GB of storage. It is not meant for heavy production use, and Aiven may power off a free service that goes unused for a long time (you get a notice and can switch it back on).

### 3. Free picture storage (Cloudinary)
Create a Cloudinary account (free plan). On its dashboard copy the **API environment variable**; it looks like
`cloudinary://123456789012345:AbCdEf...@your-cloud-name`.

### 4. Create the Render service
In Render choose **New > Blueprint**, pick your repository (it reads `render.yaml`), and enter these values when asked:

| Name | Value |
|---|---|
| `DB_HOST` | the host from Aiven |
| `DB_PORT` | the port from Aiven (it is not 3306) |
| `DB_NAME` | `defaultdb` (or your database name) |
| `DB_USER` | the user from Aiven (usually `avnadmin`) |
| `DB_PASS` | the password from Aiven |
| `DB_SSL` | `1` (already set) |
| `CLOUDINARY_URL` | the value from step 3 |

(If you prefer **New > Web Service** instead of a Blueprint: choose your repository, Runtime **Docker**, Instance type **Free**, and add the same variables under Environment.)

### 5. Install
When the deploy finishes, open `https://your-service.onrender.com/install.php`, install, then sign in at `/admin/`.
In **Store settings > Public store link** put `https://your-service.onrender.com`.

**Locked out of the admin?** Add the environment variable `ALLOW_REINSTALL` = `1`, open `/install.php` and install again with the same username and a new password, then **delete the variable**.

**Slow first visit:** after 15 idle minutes the first visitor waits about a minute. A free uptime monitor that opens your site every few minutes keeps it awake, but this uses your monthly free hours.

---------------------------------------------------------------------------------------------------

## Option C: paid hosting (recommended once the shop is earning)

Any "cPanel" or "PHP + MySQL" hosting works exactly like Option A, with these extras: your own domain, always-on speed, backups, and email at your domain
(so the footer email link looks professional). Look for PHP 8 or newer, MySQL or MariaDB, and free SSL. Local Kenyan hosts exist and many accept M-Pesa.

---------------------------------------------------------------------------------------------------

## After going live: checklist

- [ ] Admin password is strong and not shared.
- [ ] **Store settings > Public store link** is your real address (with `https://`).
- [ ] WhatsApp number, delivery fee and contact details are correct.
- [ ] `install.php` deleted from the server.
- [ ] You tried the whole path once: sign up as a test customer, add to cart, check out, send on WhatsApp, and see the order in the admin.
- [ ] You know how to export a backup of the database.
