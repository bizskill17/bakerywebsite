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

// Highlight current section in desktop nav
const links = [...document.querySelectorAll(".main-nav a")];
const sections = links.map(a => document.querySelector(a.getAttribute("href"))).filter(Boolean);
const obs = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      links.forEach(l => l.classList.toggle("active", l.getAttribute("href") === "#" + e.target.id));
    }
  });
}, { rootMargin: "-45% 0px -50% 0px" });
sections.forEach(s => obs.observe(s));
