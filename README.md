# Bliss Bites by Nutan Jain – Website + Admin Panel

Home bakery website for Bliss Bites, Guwahati, with an admin panel to manage everything without touching code.

- **Website:** `index.php` – menu, about, Instagram gallery, baking stories, order enquiry form, WhatsApp ordering
- **Admin panel:** `/admin` – log in to manage the site

Built with plain PHP 8.1+ and MySQL – runs on Hostinger shared hosting. No build step.

---

## Setting it up on Hostinger (automatic, via GitHub Actions)

Every push to `main` uploads the site to Hostinger over FTP (`.github/workflows/deploy.yml`).

1. **hPanel → Databases → MySQL Databases:** create a database and user.
2. **hPanel → Advanced → PHP Configuration:** choose PHP **8.1 or newer**.
3. **GitHub → Settings → Secrets and variables → Actions:** add these repository secrets:

| Secret | Example |
|---|---|
| `FTP_SERVER` | `147.93.92.210` |
| `FTP_USERNAME` | your FTP username from hPanel → Files → FTP Accounts |
| `FTP_PASSWORD` | your FTP password |
| `DB_HOST` | optional – `localhost` (default) |
| `DB_NAME` | `u380752258_blissbites` |
| `DB_USER` | `u380752258_blissadmin` |
| `DB_PASSWORD` | the MySQL user's password |
| `ADMIN_USERNAME` | the username you'll log in to `/admin` with |
| `ADMIN_PASSWORD` | your admin password (8+ characters) |
| `INSTAGRAM_TOKEN` | optional – Instagram access token for automatic sync (or paste it in the admin panel) |

4. **GitHub → Actions → Deploy to Hostinger → Run workflow.**
5. Open your website. On the first visit it creates its database tables, starter content and your admin login by itself. Log in at `/admin`.

Notes
- `ADMIN_USERNAME`/`ADMIN_PASSWORD` only create the *first* login. To change your password later, use **My Account** in the admin panel.
- Your uploaded photos (`uploads/`) and the database are never touched by deployments.
- `install.php` is an alternative manual setup; it locks itself once `config.php` exists.

## What you can do in the admin panel

| Section | What it does |
|---|---|
| **Dashboard** | New enquiries at a glance, quick links |
| **Enquiries** | Orders sent from the website form. Reply on WhatsApp in one tap, mark as Contacted / Confirmed / Delivered / Cancelled, download all as Excel (CSV) |
| **Menu Items** | Add, edit, delete, reorder; photo, price, description; mark *Sold out* |
| **Gallery** | Upload several photos at once, reorder, delete (first 5 appear on the site) |
| **Baking Stories** | Cover photo + link to an Instagram reel / YouTube video |
| **Instagram Sync** | Shows your latest Instagram photos (Gallery) and reels (Baking Stories) automatically, every 5 minutes, plus a *Sync now* button. Needs a Business/Creator account and an access token – step-by-step help is on that page |
| **Website Content** | WhatsApp number, Instagram, delivery city, banner text, About text, main photos |
| **My Account** | Change admin username / password |

Photos are automatically resized and compressed, and phone photos are turned the right way up.

## Security built in
- Passwords stored hashed; login locks for 15 minutes after 5 wrong attempts; auto-logout after 8 hours idle
- All admin forms protected against cross-site request forgery
- Uploaded photos are checked and re-saved as JPEG; scripts can never run from `uploads/`
- Enquiry form has spam protection (hidden trap field + max 5 enquiries/hour per visitor)
- `inc/`, `data/` and `config.php` are blocked from the web

## Files
```
index.php        public website
enquiry.php      receives the order form
install.php      one-time setup wizard (delete after setup)
admin/           admin panel
inc/             shared PHP code (database, uploads, icons) – not web-accessible
css/ js/ fonts/  website styling and scripts
images/          starter photos (replace them from the admin panel)
uploads/         photos you upload (created on the server)
```

## Testing on your computer
`php -S localhost:8000` in this folder, open http://localhost:8000/install.php and choose *Simple file database (for testing only)*.
