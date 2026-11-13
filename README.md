# Bliss Bites by Nutan Jain – Website + Admin Panel

Home bakery website for Bliss Bites, Guwahati, with an admin panel to manage everything without touching code.

- **Website:** `index.php` – menu, about, Instagram gallery, baking stories, order enquiry form, WhatsApp ordering
- **Admin panel:** `/admin` – log in to manage the site

Built with plain PHP 8.1+ and MySQL – runs on Hostinger shared hosting. No build step.

---

## Setting it up on Hostinger (one time, ~10 minutes)

1. **Create a database** – hPanel → *Databases* → *MySQL Databases*. Create a new database and user, and note down the **database name**, **user** and **password**.
2. **Put the code on your site** – hPanel → *Websites* → *Advanced* → *GIT*.
   Repository: `https://github.com/bizskill17/bakerywebsite.git`, Branch: `main`, Directory: leave empty (installs into `public_html`).
   Click **Create**, then **Deploy**. (If `public_html` already has files, empty it first.)
   *Turn on Auto Deployment* if you want every GitHub change to go live automatically.
3. **PHP version** – hPanel → *Advanced* → *PHP Configuration*: choose **8.1 or newer**.
4. **Run the setup** – open `https://yourdomain.com/install.php`. Enter the database details from step 1 and choose your admin username and password. Click **Set up website**.
5. **Delete `install.php`** – hPanel → *File Manager* → `public_html` → delete `install.php`. (It locks itself after setup anyway, but it's safer gone.)
6. **Log in** at `https://yourdomain.com/admin` and go to **Website Content** to add your WhatsApp number and Instagram username.

> `config.php` (your database password) and the `uploads/` folder (your photos) are created on the server and are **not** stored in GitHub, so future deployments never overwrite them.

## What you can do in the admin panel

| Section | What it does |
|---|---|
| **Dashboard** | New enquiries at a glance, quick links |
| **Enquiries** | Orders sent from the website form. Reply on WhatsApp in one tap, mark as Contacted / Confirmed / Delivered / Cancelled, download all as Excel (CSV) |
| **Menu Items** | Add, edit, delete, reorder; photo, price, description; mark *Sold out* |
| **Gallery** | Upload several photos at once, reorder, delete (first 5 appear on the site) |
| **Baking Stories** | Cover photo + link to an Instagram reel / YouTube video |
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
