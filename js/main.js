// Bliss Bites – small interactions. Links and content come from the admin panel.

// Mobile menu drawer
const toggle = document.querySelector(".menu-toggle");
const drawer = document.querySelector(".drawer");
const setDrawer = open => {
  drawer.hidden = !open;
  toggle.setAttribute("aria-expanded", open);
  toggle.querySelector("use").setAttribute("href", open ? "#i-close" : "#i-menu");
};
toggle.addEventListener("click", () => setDrawer(drawer.hidden));
drawer.querySelectorAll("a").forEach(a => a.addEventListener("click", () => setDrawer(false)));

// "View Full Menu" on mobile shows all menu cards
const fullBtn = document.querySelector(".js-full-menu");
fullBtn?.addEventListener("click", () => {
  const grid = document.querySelector(".menu-grid");
  const showing = grid.classList.toggle("show-all");
  fullBtn.firstChild.textContent = showing ? "Show Less " : "View Full Menu ";
});

// Baking stories carousel arrows
const track = document.querySelector(".car-track");
document.querySelectorAll(".car-btn").forEach(btn =>
  btn.addEventListener("click", () => {
    const step = track.firstElementChild.getBoundingClientRect().width + 12;
    track.scrollBy({ left: btn.classList.contains("next") ? step : -step, behavior: "smooth" });
  })
);

// "Home" and the logo always scroll right back to the top
document.querySelectorAll('a[href="#top"]').forEach(a =>
  a.addEventListener("click", e => {
    e.preventDefault();
    window.scrollTo({ top: 0, behavior: "smooth" });
    history.replaceState(null, "", location.pathname + location.search);
  })
);

// Highlight the menu item for the section you're looking at
const links = [...document.querySelectorAll(".main-nav a")];
const targetOf = a => a.getAttribute("href") === "#top" ? document.querySelector(".hero") : document.querySelector(a.getAttribute("href"));
const setActive = el => links.forEach(l => l.classList.toggle("active", targetOf(l) === el));
const obs = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) setActive(e.target); });
}, { rootMargin: "-45% 0px -50% 0px" });
links.map(targetOf).filter(Boolean).forEach(s => obs.observe(s));
window.addEventListener("scroll", () => { if (window.scrollY < 40) setActive(document.querySelector(".hero")); }, { passive: true });
