/* Designestoon — gallery, filters, lightbox and mobile navigation */
"use strict";
let visibleProjects = [];
let currentProjectIndex = 0;
let previousFocusedElement = null;
let portfolioProjects = [];
let portfolioReady = false;
let portfolioContact = null;
let initialPortfolioMessage = null;
const originalCategories = [];
const contactLinks = [];
let instagramLink = null;

document.addEventListener("DOMContentLoaded", () => {
  initializeNavigation();
  document.getElementById("copyright-year")?.replaceChildren(String(new Date().getFullYear()));
  initializePortfolioFilters();
  initializeRentalCategoryCards();
  initializeLightbox();
  initializePortfolio();
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

async function initializePortfolio() {
  const grid = document.getElementById("portfolio-grid");
  if (!grid) return;
  if (!initialPortfolioMessage) {
    initialPortfolioMessage = grid.firstElementChild.cloneNode(true);
    document.querySelectorAll('.filter-btn[data-filter]').forEach(button => {
      if (button.dataset.filter !== 'all') originalCategories.push({slug: button.dataset.filter, title: button.textContent});
    });
    contactLinks.push(...document.querySelectorAll('.footer-contact-link'));
    instagramLink = document.querySelector('.footer-instagram');
  }
  portfolioReady = false;
  portfolioProjects = [];
  visibleProjects = [];
  portfolioContact = null;
  closeLightbox();
  setPortfolioControls(false);
  const apiMode = window.DesignestoonConfig?.mode === 'api';
  grid.setAttribute('aria-busy', 'true');
  if (apiMode) {
    replacePortfolioFilters([]);
    setPortfolioControls(false);
    showPortfolioMessage('در حال دریافت نمونه‌کارها…', 'لطفاً کمی صبر کنید.');
    setFooterContact(null, 'در حال دریافت اطلاعات تماس…');
  }
  try {
    const data = await window.DesignestoonData.load(typeof projects === 'undefined' ? [] : projects, originalCategories);
    portfolioProjects = data.projects;
    portfolioContact = data.contact;
    if (data.mode === 'api') replacePortfolioFilters(data.categories);
    portfolioReady = true;
    grid.replaceChildren(initialPortfolioMessage.cloneNode(true));
    setPortfolioControls(true);
    if (data.mode === 'api') setFooterContact(data.contact);
  } catch (error) {
    // در حالت API هیچ‌گاه داده قدیمی یا ذخیره‌شده دوباره نمایش داده نمی‌شود.
    showPortfolioMessage('دریافت گالری انجام نشد.', 'ارتباط با سرویس برقرار نشد یا اطلاعات معتبر نیست. دوباره تلاش کنید.', true);
    if (apiMode) setFooterContact(null, 'اطلاعات تماس در دسترس نیست');
  } finally {
    grid.setAttribute('aria-busy', 'false');
  }
}
function showPortfolioMessage(title, description, retry = false) {
  const message = initialPortfolioMessage.cloneNode(true);
  message.querySelector('h3').textContent = title;
  message.querySelector('p').textContent = description;
  if (retry) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-outline';
    button.textContent = 'تلاش دوباره';
    button.addEventListener('click', initializePortfolio);
    message.appendChild(button);
  }
  document.getElementById('portfolio-grid').replaceChildren(message);
}
function replacePortfolioFilters(categories) {
  const container = document.querySelector('.portfolio-filters');
  const fragment = document.createDocumentFragment();
  [{slug: 'all', title: 'همه نمونه‌کارها'}, ...categories].forEach(category => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'filter-btn';
    button.dataset.filter = category.slug;
    button.setAttribute('aria-pressed', 'false');
    button.textContent = category.title;
    fragment.appendChild(button);
  });
  container.replaceChildren(fragment);
}
function setPortfolioControls(ready) {
  const filters = [...document.querySelectorAll('.filter-btn')];
  filters.forEach(button => { button.disabled = !ready; });
  document.querySelectorAll('[data-rental-filter]').forEach(button => {
    const available = ready && filters.some(filter => filter.dataset.filter === button.dataset.rentalFilter);
    button.disabled = !available;
    if (ready && !available) button.title = 'این دسته فعلاً در دسترس نیست.';
    else button.removeAttribute('title');
  });
}
function displayPhone(phone) {
  return `${phone.slice(0, 4)} ${phone.slice(4, 7)} ${phone.slice(7)}`;
}
function phoneHref(phone) {
  const validated = window.DesignestoonData.phone(phone);
  return 'tel:+98' + validated.slice(1);
}
function persianPhone(phone) {
  return phone.replace(/[0-9]/g, digit => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]);
}
function setFooterContact(contact, unavailable = '') {
  contactLinks.forEach((link, index) => {
    const phone = contact ? (index === 0 ? contact.primary : contact.secondary) : '';
    link.textContent = phone ? displayPhone(phone) : (index === 0 ? unavailable : '');
    if (phone) {
      link.href = phoneHref(phone);
      link.setAttribute('aria-label', 'تماس با شماره ' + persianPhone(phone));
      link.removeAttribute('aria-disabled');
    } else {
      link.removeAttribute('href');
      link.setAttribute('aria-disabled', 'true');
      link.removeAttribute('aria-label');
    }
    link.style.display = !phone && index !== 0 ? 'none' : '';
  });
  if (instagramLink) {
    if (contact?.instagram) {
      instagramLink.href = contact.instagram;
      instagramLink.removeAttribute('aria-disabled');
    } else {
      instagramLink.removeAttribute('href');
      instagramLink.setAttribute('aria-disabled', 'true');
    }
  }
}
function createReservationBox(project) {
  const phone = portfolioContact.primary;
  const box = document.createElement('a');
  box.className = 'rental-product-contact';
  box.href = phoneHref(phone);
  box.setAttribute('aria-label', `تماس برای قیمت و رزرو ${project.title} با شماره ${persianPhone(phone)}`);
  const icon = document.createElement('span');
  icon.className = 'rental-contact-icon';
  icon.setAttribute('aria-hidden', 'true');
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  for (const [name, value] of Object.entries({viewBox: '0 0 24 24', width: '20', height: '20', fill: 'none', stroke: 'currentColor', 'stroke-width': '1.8', 'stroke-linecap': 'round', 'stroke-linejoin': 'round'})) svg.setAttribute(name, value);
  const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
  path.setAttribute('d', 'M22 16.92v3a2 2 0 0 1-2.18 2A19.8 19.8 0 0 1 11.19 18a19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.09 3.18 2 2 0 0 1 4.08 1h3a2 2 0 0 1 2 1.72c.12.96.35 1.9.69 2.8a2 2 0 0 1-.45 2.11L8.05 8.9a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.84.57 2.8.69A2 2 0 0 1 22 16.92z');
  svg.appendChild(path);
  icon.appendChild(svg);
  const copy = document.createElement('span');
  copy.className = 'rental-contact-copy';
  const text = document.createElement('strong');
  text.textContent = 'برای قیمت و رزرو تماس بگیرید';
  const number = document.createElement('small');
  number.dir = 'ltr';
  number.textContent = displayPhone(phone);
  copy.append(text, number);
  const arrow = document.createElement('span');
  arrow.className = 'rental-contact-arrow';
  arrow.setAttribute('aria-hidden', 'true');
  arrow.textContent = '←';
  box.append(icon, copy, arrow);
  return box;
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

    card.appendChild(createReservationBox(project));

    fragment.appendChild(card);
  });
  container.replaceChildren(fragment);
}

function initializePortfolioFilters() {
  const filters = document.querySelector(".portfolio-filters");
  const grid = document.getElementById("portfolio-grid");
  if (!filters || !grid) return;
  filters.addEventListener("click", event => {
    if (!portfolioReady) return;
    const button = event.target.closest(".filter-btn");
    if (!button || !filters.contains(button)) return;
    const filter = button.dataset.filter;
    visibleProjects = filter === "all" ? [...portfolioProjects] : portfolioProjects.filter(project => project.category === filter);
    renderProjects(grid, visibleProjects);
    if (!visibleProjects.length) showPortfolioMessage('نمونه‌کاری برای نمایش وجود ندارد.', 'در این دسته هنوز نمونه‌کاری منتشر نشده است.');
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
      if (!portfolioReady || !filterButton || filterButton.disabled) return;
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
