/**
 * Moms Beauty Touch - Main JavaScript
 * Handles Scroll Reveals, Mobile Menu, Active Navigation, Dynamic Year,
 * and Real-Time Dynamic Hydration from Backend APIs (Settings, Services, Gallery).
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Dynamic Copyright Year
  const yearEl = document.getElementById('currentYear');
  if (yearEl) {
    yearEl.textContent = new Date().getFullYear();
  }

  // 2. IntersectionObserver for Scroll Reveal
  const reveals = document.querySelectorAll('.reveal');
  if (reveals.length > 0 && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, {
      threshold: 0.05,
      rootMargin: '50px 0px 50px 0px'
    });

    reveals.forEach((el) => {
      const rect = el.getBoundingClientRect();
      if (rect.top < window.innerHeight + 100 && rect.bottom > -50) {
        el.classList.add('is-visible');
      } else {
        observer.observe(el);
      }
    });
  } else {
    // Fallback for browsers without IntersectionObserver
    reveals.forEach((el) => el.classList.add('is-visible'));
  }

  // 3. Mobile Navigation Drawer Toggle
  const menuBtn = document.getElementById('mobileMenuBtn');
  const drawer = document.getElementById('mobileDrawer');
  const iconMenu = document.getElementById('iconMenu');
  const iconClose = document.getElementById('iconClose');

  if (menuBtn && drawer) {
    menuBtn.addEventListener('click', () => {
      const isOpen = drawer.classList.contains('open');
      if (isOpen) {
        drawer.classList.remove('open');
        if (iconMenu && iconClose) {
          iconMenu.style.display = 'block';
          iconClose.style.display = 'none';
        }
        menuBtn.setAttribute('aria-expanded', 'false');
      } else {
        drawer.classList.add('open');
        if (iconMenu && iconClose) {
          iconMenu.style.display = 'none';
          iconClose.style.display = 'block';
        }
        menuBtn.setAttribute('aria-expanded', 'true');
      }
    });

    // Close drawer when clicking a mobile link
    const mobileLinks = drawer.querySelectorAll('.mobile-nav-link');
    mobileLinks.forEach((link) => {
      link.addEventListener('click', () => {
        drawer.classList.remove('open');
        if (iconMenu && iconClose) {
          iconMenu.style.display = 'block';
          iconClose.style.display = 'none';
        }
        menuBtn.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // 4. Mark Active Navigation Link
  const currentPath = window.location.pathname.split('/').pop() || 'index.html';
  const navLinks = document.querySelectorAll('.nav-link, .mobile-nav-link');
  navLinks.forEach((link) => {
    const href = link.getAttribute('href');
    if (href === currentPath || (currentPath === '' && href === 'index.html')) {
      link.classList.add('active');
    }
  });

  // 5. Dynamic Business Settings Hydration (Instant Reflection of Admin Settings)
  loadBusinessSettings();

  // 6. Dynamic Services Hydration (Instant Reflection of Admin Services on Home & Services pages)
  loadDynamicServices();

  // 7. Dynamic Selected Work Hydration (Instant Reflection of Admin Gallery on Home page)
  loadDynamicHomeGallery();
});

// Helper for XSS escaping
function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * Loads business settings from api/settings.php
 * Reflects Admin changes to phone, WhatsApp, Instagram, rating, hours, address, and logo.
 */
async function loadBusinessSettings() {
  try {
    const res = await fetch('api/settings.php', { cache: 'no-store' });
    if (!res.ok) return;
    const json = await res.json();
    if (json.status !== 'success' || !json.data) return;
    const s = json.data;

    // 1. Phone number updates
    if (s.phone) {
      document.querySelectorAll('a[href^="tel:"]').forEach((el) => {
        el.href = `tel:${s.phone_tel || s.phone.replace(/[^0-9+]/g, '')}`;
        if (el.textContent.includes('72198') || el.textContent.includes('+91')) {
          el.textContent = s.phone;
        }
      });
      // Replace text phone occurrences in text nodes
      document.querySelectorAll('p, span').forEach((el) => {
        if (el.children.length === 0 && el.textContent.includes('+91 72198 84594')) {
          el.textContent = el.textContent.replace('+91 72198 84594', s.phone);
        }
      });
    }

    // 2. WhatsApp links
    if (s.whatsapp) {
      const cleanWa = s.whatsapp.replace(/[^0-9]/g, '');
      document.querySelectorAll('a[href*="wa.me"]').forEach((el) => {
        try {
          const u = new URL(el.href);
          const text = u.searchParams.get('text') || '';
          el.href = `https://wa.me/${cleanWa}${text ? '?text=' + encodeURIComponent(text) : ''}`;
        } catch (_) {
          el.href = `https://wa.me/${cleanWa}`;
        }
      });
    }

    // 3. Instagram links (footer and floating button)
    if (s.instagram) {
      document.querySelectorAll('.insta-float-btn, a[href*="instagram.com"]').forEach((el) => {
        el.href = s.instagram;
      });
    }

    // 4. Rating
    if (s.rating) {
      const formattedRating = parseFloat(s.rating).toFixed(2);
      document.querySelectorAll('strong').forEach((el) => {
        if (el.textContent.trim() === '4.52') {
          el.textContent = formattedRating;
        }
      });
      document.querySelectorAll('.footer-rating').forEach((el) => {
        const textNode = Array.from(el.childNodes).find((n) => n.nodeType === Node.TEXT_NODE && n.nodeValue.includes('Google'));
        if (textNode) {
          textNode.nodeValue = ` ${formattedRating} on Google`;
        }
      });
    }

    // 5. Operating Hours
    if (s.hours) {
      document.querySelectorAll('.footer-text').forEach((el) => {
        if (el.textContent.trim() === 'Open 24 hours') {
          el.textContent = s.hours;
        }
      });
    }

    // 6. Address
    if (s.address) {
      document.querySelectorAll('.footer-text').forEach((el) => {
        if (el.textContent.includes('Near Manti Memorial High School')) {
          el.textContent = s.address;
        }
      });
    }

    // 7. Logo
    if (s.logo_url) {
      document.querySelectorAll('.site-logo-img').forEach((el) => {
        el.src = s.logo_url;
      });
    }
  } catch (err) {
    console.debug('Settings API load skipped:', err);
  }
}

/**
 * Loads dynamic services from api/services.php
 * Reflects services created, edited, and image uploads from Admin.
 */
async function loadDynamicServices() {
  const homeGrid = document.getElementById('servicesHomeGrid');
  const servicesContainer = document.getElementById('servicesContainer');
  if (!homeGrid && !servicesContainer) return;

  try {
    const res = await fetch('api/services.php', { cache: 'no-store' });
    if (!res.ok) return;
    const json = await res.json();
    if (json.status !== 'success' || !Array.isArray(json.data) || json.data.length === 0) return;
    const services = json.data;

    // Home Page Services Overview
    if (homeGrid) {
      homeGrid.innerHTML = services.map((s, idx) => `
        <div class="reveal is-visible" style="transition-delay: ${idx * 100}ms;">
          <a href="service-details.html?service=${encodeURIComponent(s.slug)}" style="display: block; text-decoration: none;">
            <div class="img-zoom aspect-3-4 rounded-sm">
              <img src="${escapeHtml(s.image_url)}" alt="${escapeHtml(s.title)}" loading="lazy" class="object-cover">
            </div>
            <h3 style="margin-top: 1.25rem; font-size: 1.5rem;">${escapeHtml(s.title)}</h3>
            <p style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--muted-foreground);">
              ${escapeHtml(s.short)}
            </p>
          </a>
        </div>
      `).join('');
    }

    // Services Page List
    if (servicesContainer) {
      servicesContainer.innerHTML = services.map((s, idx) => `
        <article class="reveal is-visible service-card-grid ${idx % 2 === 1 ? 'service-reversed' : ''}">
          <div class="img-zoom aspect-4-3 rounded-sm shadow-soft">
            <img src="${escapeHtml(s.image_url)}" alt="${escapeHtml(s.title)}" loading="lazy" class="object-cover">
          </div>
          <div>
            <p class="eyebrow">${String(idx + 1).padStart(2, '0')}</p>
            <h2 class="section-title" style="font-size: clamp(2rem, 4vw, 2.75rem); margin-top: 0.75rem;">${escapeHtml(s.title)}</h2>
            <p style="margin-top: 1.25rem; color: var(--muted-foreground); font-size: 1.0625rem; line-height: 1.6;">
              ${escapeHtml(s.short)}
            </p>
            ${s.price ? `<p style="margin-top: 0.75rem; font-weight: 500; color: var(--gold);">${escapeHtml(s.price)}</p>` : ''}
            <div style="margin-top: 2rem; display: flex; flex-wrap: wrap; gap: 1rem;">
              <a href="service-details.html?service=${encodeURIComponent(s.slug)}" class="btn btn-outline">View Details</a>
              <a href="booking.html?service=${encodeURIComponent(s.slug)}" class="btn btn-primary">Enquire Now</a>
            </div>
          </div>
        </article>
      `).join('');
    }
  } catch (err) {
    console.debug('Services API load skipped:', err);
  }
}

/**
 * Loads dynamic photos into Home Page "Selected work" from api/gallery.php
 * Reflects photos uploaded to Admin Gallery instantly.
 */
async function loadDynamicHomeGallery() {
  const homeGallery = document.getElementById('portfolioHomeGrid');
  if (!homeGallery) return;

  try {
    const res = await fetch('api/gallery.php', { cache: 'no-store' });
    if (!res.ok) return;
    const json = await res.json();
    if (json.status !== 'success' || !Array.isArray(json.data) || json.data.length === 0) return;

    const items = json.data.slice(0, 3);
    const existingImgs = homeGallery.querySelectorAll('img');
    items.forEach((item, i) => {
      if (existingImgs[i]) {
        existingImgs[i].src = item.image_url;
        existingImgs[i].alt = item.alt_text || item.category;
      }
    });

    const note = document.getElementById('portfolioHomeNote');
    if (note) {
      note.style.display = 'none';
    }
  } catch (err) {
    console.debug('Home Gallery API load skipped:', err);
  }
}
