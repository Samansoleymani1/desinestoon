/* Designestoon — gallery, filters, lightbox and mobile navigation */
"use strict";
let visibleProjects = [];
let currentProjectIndex = 0;
let previousFocusedElement = null;

document.addEventListener("DOMContentLoaded", () => {
  initializeNavigation();
  document.getElementById("copyright-year")?.replaceChildren(String(new Date().getFullYear()));
  initializePortfolio();
  initializePortfolioFilters();
  initializeRentalCategoryCards();
  initializeLightbox();
});

function initializeNavigation() {
  const toggle = document.getElementById("menu-toggle");
  const menu = document.getElementById("primary-navigation");
  if (!toggle || !menu) return;
  const closeMenu = () => {
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-label", "باز کردن منو");
    menu.classList.remove("is-open");
  };
  toggle.addEventListener("click", () => {
    const opening = toggle.getAttribute("aria-expanded") !== "true";
    toggle.setAttribute("aria-expanded", String(opening));
    toggle.setAttribute("aria-label", opening ? "بستن منو" : "باز کردن منو");
    menu.classList.toggle("is-open", opening);
  });
  menu.querySelectorAll("a").forEach(link => link.addEventListener("click", closeMenu));
 document.addEventListener("keydown", event => {
    if (event.key === "Escape" && menu.classList.contains("is-open")) {
      closeMenu();
      toggle.focus();
    }
  });
 document.addEventListener("click", event => {
    if (!toggle.contains(event.target) && !menu.contains(event.target)) closeMenu();
  });
  window.matchMedia("(min-width: 768px)").addEventListener("change", event => {
    if (event.matches) closeMenu();
  });
}

function initializePortfolio() {
  const grid = document.getElementById("portfolio-grid");
  if (!grid) return;
  if (typeof projects === "undefined" || !Array.isArray(projects)) {
    console.error("Portfolio data is missing.");
    return;
  }
  // Keep the gallery collapsed on first load. Projects appear only after a filter is chosen.
  visibleProjects = [];
}

function renderProjects(container, list) {
  const fragment = document.createDocumentFragment();
  list.forEach((project, index) => {
    const card = document.createElement("article");
    card.className = "portfolio-card";
    const button = document.createElement("button");
    button.type = "button";
    button.className = "portfolio-card-image";
    button.setAttribute("aria-label", `مشاهده تصویر بزرگ ${project.title}`);
    const image = document.createElement("img");
    image.src = project.image;
    image.alt = project.title;
    image.loading = "lazy";
    image.decoding = "async";
    image.width = project.width || 800;
    image.height = project.height || 1000;
    const content = document.createElement("div");
    content.className = "portfolio-card-content";
    const title = document.createElement("h3");
    title.textContent = project.title;
    button.addEventListener("click", () => openLightbox(index));
    button.appendChild(image);
    content.appendChild(title);
    card.append(button, content);

    // Every portfolio item gets a direct, tap-to-call price / reservation panel.
    {
      const contactBox = document.createElement("a");
      contactBox.className = "rental-product-contact";
      contactBox.href = "tel:+989117901519";
      contactBox.setAttribute("aria-label", `تماس برای قیمت و رزرو ${project.title} با شماره ۰۹۱۱۷۹۰۱۵۱۹`);
      contactBox.innerHTML = `
        <span class="rental-contact-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.8 19.8 0 0 1 11.19 18a19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.09 3.18 2 2 0 0 1 4.08 1h3a2 2 0 0 1 2 1.72c.12.96.35 1.9.69 2.8a2 2 0 0 1-.45 2.11L8.05 8.9a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.84.57 2.8.69A2 2 0 0 1 22 16.92z"/>
          </svg>
        </span>
        <span class="rental-contact-copy">
          <strong>برای قیمت و رزرو تماس بگیرید</strong>
          <small dir="ltr">0911 790 1519</small>
        </span>
        <span class="rental-contact-arrow" aria-hidden="true">←</span>`;
      card.appendChild(contactBox);
    }

    fragment.appendChild(card);
  });
  container.replaceChildren(fragment);
}

function initializePortfolioFilters() {
  const filters = document.querySelector(".portfolio-filters");
  const grid = document.getElementById("portfolio-grid");
  if (!filters || !grid || typeof projects === "undefined" || !Array.isArray(projects)) return;
  filters.addEventListener("click", event => {
    const button = event.target.closest(".filter-btn");
    if (!button || !filters.contains(button)) return;
    const filter = button.dataset.filter;
    visibleProjects = filter === "all" ? [...projects] : projects.filter(project => project.category === filter);
    renderProjects(grid, visibleProjects);
    filters.querySelectorAll(".filter-btn").forEach(item => {
      const active = item === button;
      item.classList.toggle("active", active);
      item.setAttribute("aria-pressed", String(active));
    });
  });
}


function initializeRentalCategoryCards() {
  document.querySelectorAll("[data-rental-filter]").forEach(cardButton => {
    cardButton.addEventListener("click", () => {
      const targetFilter = cardButton.dataset.rentalFilter;
      const filterButton = document.querySelector(`.portfolio-filters [data-filter="${targetFilter}"]`);
      if (!filterButton) return;
      filterButton.click();
      document.getElementById("portfolio-grid")?.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });
}

function initializeLightbox() {
  const lightbox = document.getElementById("portfolio-lightbox");
  if (!lightbox) return;
  lightbox.querySelector(".lightbox-close").addEventListener("click", closeLightbox);
  lightbox.querySelector(".lightbox-prev").addEventListener("click", () => changeLightboxImage(-1));
  lightbox.querySelector(".lightbox-next").addEventListener("click", () => changeLightboxImage(1));
  lightbox.addEventListener("click", event => {
    if (event.target === lightbox) closeLightbox();
  });
 document.addEventListener("keydown", event => {
    if (lightbox.hidden) return;
    if (event.key === "Escape") closeLightbox();
    else if (event.key === "ArrowRight") changeLightboxImage(-1);
    else if (event.key === "ArrowLeft") changeLightboxImage(1);
    else if (event.key === "Tab") trapLightboxFocus(event, lightbox);
  });
}

function openLightbox(index) {
  if (!visibleProjects[index]) return;
  const lightbox = document.getElementById("portfolio-lightbox");
  previousFocusedElement = document.activeElement;
  currentProjectIndex = index;
  updateLightboxImage();
  lightbox.hidden = false;
  lightbox.setAttribute("aria-hidden", "false");
  document.body.classList.add("lightbox-open");
  lightbox.querySelector(".lightbox-close").focus();
}
function closeLightbox() {
  const lightbox = document.getElementById("portfolio-lightbox");
  if (!lightbox || lightbox.hidden) return;
  lightbox.hidden = true;
  lightbox.setAttribute("aria-hidden", "true");
  document.body.classList.remove("lightbox-open");
  if (previousFocusedElement && previousFocusedElement.isConnected) previousFocusedElement.focus();
}
function changeLightboxImage(direction) {
  if (!visibleProjects.length) return;
  currentProjectIndex = (currentProjectIndex + direction + visibleProjects.length) % visibleProjects.length;
  updateLightboxImage();
}
function updateLightboxImage() {
  const project = visibleProjects[currentProjectIndex];
  if (!project) return;
  const image = document.getElementById("lightbox-image");
  image.src = project.image;
  image.alt = project.title;
  document.getElementById("lightbox-caption").textContent = project.title;
}
function trapLightboxFocus(event, lightbox) {
  const buttons = [...lightbox.querySelectorAll("button:not([disabled])")];
  if (!buttons.length) return;
  const first = buttons[0];
  const last = buttons[buttons.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}
