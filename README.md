# Bliss Bites by Nutan Jain – Website

Home bakery website for Bliss Bites, Guwahati. Plain HTML/CSS/JS – no build step. Open `index.html` in a browser or upload the folder to any web host.

## Set your contact details
Edit the top of `js/main.js`:

```js
const WHATSAPP_NUMBER = "91XXXXXXXXXX";   // e.g. 919876543210
const INSTAGRAM_HANDLE = "blissbites";    // without @
```

Every WhatsApp / Order button and every Instagram link on the site uses these.

## Replace photos
All photos live in `images/`. To use your own, replace a file with one of the same name:

| File | Where it shows |
|---|---|
| `hero.jpg` | Big brownie photo at the top |
| `menu-*.jpg` | Our Menu cards |
| `about-nutan.jpg` | About section (desktop) |
| `about-brownie.jpg` | About section (mobile) |
| `gallery-1…5.jpg` | Instagram Gallery |
| `story-1…5.jpg` | Watch Our Baking Stories |

## Structure
```
index.html      page content
css/style.css   all styling (desktop + mobile)
js/main.js      links, mobile menu, carousel
fonts/          self-hosted fonts
images/         photos + favicon
```
