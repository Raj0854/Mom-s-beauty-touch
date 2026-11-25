/**
 * Moms Beauty Touch - Portfolio & Lightbox JavaScript
 * Dynamically fetches photos from api/gallery.php (reflecting Admin uploads instantly),
 * handles category filtering, and responsive full-screen lightbox.
 */

document.addEventListener('DOMContentLoaded', async () => {
  const filterBtns = document.querySelectorAll('.portfolio-filter-btn');
  const grid = document.getElementById('portfolioGrid');
  const emptyState = document.getElementById('portfolioEmpty');
  const sampleNote = document.getElementById('portfolioSampleNote');

  // Try to load dynamic gallery items from backend API
  try {
    const res = await fetch('api/gallery.php', { cache: 'no-store' });
    if (res.ok) {
      const json = await res.json();
      if (json.status === 'success' && Array.isArray(json.data) && json.data.length > 0) {
        // Render dynamic uploaded photos from Admin
        grid.innerHTML = json.data.map((item) => `
          <button type="button" class="gallery-item img-zoom" data-category="${escapeHtml(item.category)}" aria-label="${escapeHtml(item.alt_text || ('View ' + item.category + ' look'))}">
            <img src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.alt_text || item.category)}" width="1280" height="1600" loading="lazy">
          </button>
        `).join('');

        if (sampleNote) {
          sampleNote.style.display = 'none';
        }
      }
    }
  } catch (err) {
    console.debug('Dynamic gallery API load skipped, showing fallback items:', err);
  }

  // Setup Filtering & Lightbox
  setupGalleryInteractions();

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function setupGalleryInteractions() {
    const galleryItems = document.querySelectorAll('.gallery-item');
    const lightboxModal = document.getElementById('lightboxModal');
    const lightboxImg = document.getElementById('lightboxImg');
    const lightboxClose = document.getElementById('lightboxClose');
    const lightboxPrev = document.getElementById('lightboxPrev');
    const lightboxNext = document.getElementById('lightboxNext');

    let currentVisibleItems = [];
    let currentIndex = 0;

    function updateVisibleItems() {
      currentVisibleItems = Array.from(galleryItems).filter((item) => item.style.display !== 'none');
    }

    // 1. Category Filtering
    filterBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        filterBtns.forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');

        const selectedCat = btn.getAttribute('data-category');
        let visibleCount = 0;

        galleryItems.forEach((item) => {
          const itemCat = item.getAttribute('data-category');
          if (selectedCat === 'All' || itemCat === selectedCat) {
            item.style.display = 'block';
            item.style.animation = 'fadeIn 0.4s ease-out';
            visibleCount++;
          } else {
            item.style.display = 'none';
          }
        });

        if (emptyState) {
          emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
        }
      });
    });

    // 2. Lightbox Functionality
    function showImage(index) {
      if (currentVisibleItems.length === 0) return;
      if (index < 0) {
        currentIndex = currentVisibleItems.length - 1;
      } else if (index >= currentVisibleItems.length) {
        currentIndex = 0;
      } else {
        currentIndex = index;
      }

      const item = currentVisibleItems[currentIndex];
      const imgEl = item ? item.querySelector('img') : null;
      if (imgEl && lightboxImg) {
        lightboxImg.src = imgEl.src;
        lightboxImg.alt = imgEl.alt || 'Portfolio image';
      }
    }

    function openLightbox(index) {
      updateVisibleItems();
      currentIndex = index;
      showImage(currentIndex);
      if (lightboxModal) {
        lightboxModal.classList.add('active');
        document.body.style.overflow = 'hidden';
      }
    }

    function closeLightbox() {
      if (lightboxModal) {
        lightboxModal.classList.remove('active');
        document.body.style.overflow = '';
      }
    }

    // Attach click to each gallery item
    galleryItems.forEach((item) => {
      item.addEventListener('click', () => {
        updateVisibleItems();
        const idx = currentVisibleItems.indexOf(item);
        if (idx !== -1) {
          openLightbox(idx);
        }
      });
    });

    if (lightboxClose) {
      lightboxClose.addEventListener('click', (e) => {
        e.stopPropagation();
        closeLightbox();
      });
    }

    if (lightboxPrev) {
      lightboxPrev.addEventListener('click', (e) => {
        e.stopPropagation();
        showImage(currentIndex - 1);
      });
    }

    if (lightboxNext) {
      lightboxNext.addEventListener('click', (e) => {
        e.stopPropagation();
        showImage(currentIndex + 1);
      });
    }

    if (lightboxModal) {
      lightboxModal.addEventListener('click', (e) => {
        if (e.target === lightboxModal || e.target === lightboxClose) {
          closeLightbox();
        }
      });
    }

    // Keyboard navigation
    window.addEventListener('keydown', (e) => {
      if (!lightboxModal || !lightboxModal.classList.contains('active')) return;

      if (e.key === 'Escape') {
        closeLightbox();
      } else if (e.key === 'ArrowLeft') {
        showImage(currentIndex - 1);
      } else if (e.key === 'ArrowRight') {
        showImage(currentIndex + 1);
      }
    });
  }
});
