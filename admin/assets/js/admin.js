/**
 * Moms Beauty Touch — Admin Portal JavaScript
 * Vanilla ES6+ | Zero external libraries
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Mobile Sidebar Toggle
  const menuToggle = document.querySelector('.menu-toggle');
  const sidebar = document.querySelector('.admin-sidebar');

  if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      sidebar.classList.toggle('open');
    });

    document.addEventListener('click', (e) => {
      if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    });
  }

  // 2. Alert Dismissal
  document.querySelectorAll('.alert-close').forEach((btn) => {
    btn.addEventListener('click', () => {
      const alert = btn.closest('.alert');
      if (alert) {
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 200);
      }
    });
  });

  // 3. Image Upload Preview
  document.querySelectorAll('input[type="file"][data-preview]').forEach((input) => {
    input.addEventListener('change', () => {
      const targetSelector = input.getAttribute('data-preview');
      const targetImg = document.querySelector(targetSelector);
      if (targetImg && input.files && input.files[0]) {
        const file = input.files[0];
        if (file.type.match(/^image\//)) {
          targetImg.src = URL.createObjectURL(file);
          targetImg.style.display = 'block';
        }
      }
    });
  });

  // 4. Modal Open & Close Triggers
  document.querySelectorAll('[data-modal-open]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const modalId = btn.getAttribute('data-modal-open');
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
      }
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.modal-backdrop');
      if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
      }
    });
  });

  // Close modal when clicking on backdrop
  document.querySelectorAll('.modal-backdrop').forEach((modal) => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
      }
    });
  });

  // 5. Slug Generator Helper (for Services)
  const titleInput = document.getElementById('service-title');
  const slugInput = document.getElementById('service-slug');
  if (titleInput && slugInput && !slugInput.value) {
    titleInput.addEventListener('input', () => {
      slugInput.value = titleInput.value
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
    });
  }

  // 6. Confirm Delete Confirmation Protection
  document.querySelectorAll('form[data-confirm-delete]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      const message = form.getAttribute('data-confirm-delete') || 'Are you sure you want to permanently delete this item? This action cannot be undone.';
      if (!confirm(message)) {
        e.preventDefault();
      }
    });
  });
});

