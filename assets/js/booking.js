/**
 * Moms Beauty Touch - Booking Form JavaScript
 * Handles real-time client-side validation, query param pre-selection,
 * server-side API booking registration with reference code, and WhatsApp integration.
 */

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('bookingForm');
  const successCard = document.getElementById('bookingSuccess');
  const sendWhatsAppBtn = document.getElementById('sendWhatsAppBtn');
  const editDetailsBtn = document.getElementById('editDetailsBtn');
  const serviceSelect = document.getElementById('serviceSelect');
  const dateInput = document.getElementById('dateInput');
  const submitBtn = form ? form.querySelector('button[type="submit"]') : null;

  // Pre-fill service from URL parameter (e.g. booking.html?service=bridal)
  const urlParams = new URLSearchParams(window.location.search);
  const paramService = (urlParams.get('service') || '').toLowerCase();
  if (paramService && serviceSelect) {
    serviceSelect.value = paramService;
  }

  // Load services dynamically from API (Admin created/edited services)
  async function loadServicesDropdown() {
    if (!serviceSelect) return;
    try {
      const res = await fetch('api/services.php', { cache: 'no-store' });
      if (res.ok) {
        const json = await res.json();
        if (json.status === 'success' && Array.isArray(json.data) && json.data.length > 0) {
          serviceSelect.innerHTML = '<option value="">Choose a service...</option>' +
            json.data.map(s => `<option value="${s.id}" data-slug="${s.slug}">${s.title}</option>`).join('');

          if (paramService) {
            const match = Array.from(serviceSelect.options).find(o => 
              (o.getAttribute('data-slug') || '').toLowerCase() === paramService || o.value === paramService
            );
            if (match) serviceSelect.value = match.value;
          }
        }
      }
    } catch (err) {
      console.debug('Services dropdown fallback:', err);
    }
  }
  loadServicesDropdown();

  // Set min date to today
  if (dateInput) {
    const today = new Date().toISOString().split('T')[0];
    dateInput.min = today;
  }

  const serviceNames = {
    'bridal': 'Bridal Makeup',
    'party': 'Party Makeup',
    'wedding': 'Wedding Makeup',
    'engagement': 'Engagement Makeup'
  };

  const phoneRegex = /^(\+?91[\s-]?)?[6-9]\d{4}[\s-]?\d{5}$/;
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function showError(fieldName, message) {
    const errorEl = document.getElementById(`err_${fieldName}`);
    const inputEl = document.getElementById(`input_${fieldName}`);
    if (errorEl) {
      errorEl.textContent = message;
    }
    if (inputEl) {
      inputEl.style.borderColor = 'var(--destructive)';
    }
  }

  function clearErrors() {
    const errors = document.querySelectorAll('.form-error');
    errors.forEach((err) => (err.textContent = ''));
    const inputs = document.querySelectorAll('.form-input, .form-select, .form-textarea');
    inputs.forEach((input) => (input.style.borderColor = ''));
  }

  // Helper to fetch CSRF token from backend API
  async function getCsrfToken() {
    try {
      const res = await fetch('api/csrf.php', { cache: 'no-store' });
      if (res.ok) {
        const json = await res.json();
        return json.csrf_token || null;
      }
    } catch (e) {
      // Network/offline fallback
    }
    return null;
  }

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearErrors();

      let hasError = false;

      const name = (document.getElementById('input_name')?.value || '').trim();
      const phone = (document.getElementById('input_phone')?.value || '').trim();
      const email = (document.getElementById('input_email')?.value || '').trim();
      const service = (document.getElementById('serviceSelect')?.value || '').trim();
      const date = (document.getElementById('dateInput')?.value || '').trim();
      const time = (document.getElementById('input_time')?.value || '').trim();
      const location = (document.getElementById('input_location')?.value || '').trim();
      const notes = (document.getElementById('input_notes')?.value || '').trim();

      // Validation Rules
      if (!name || name.length < 2) {
        showError('name', 'Please enter your name');
        hasError = true;
      } else if (name.length > 100) {
        showError('name', 'Name must be under 100 characters');
        hasError = true;
      }

      if (!phone || !phoneRegex.test(phone)) {
        showError('phone', 'Enter a valid Indian mobile number');
        hasError = true;
      }

      if (email && !emailRegex.test(email)) {
        showError('email', 'Enter a valid email');
        hasError = true;
      }

      if (!service) {
        showError('service', 'Choose a service');
        hasError = true;
      }

      const today = new Date().toISOString().split('T')[0];
      if (!date) {
        showError('date', 'Choose your event date');
        hasError = true;
      } else if (date < today) {
        showError('date', "Date can't be in the past");
        hasError = true;
      }

      if (!time) {
        showError('time', 'Choose a preferred time');
        hasError = true;
      }

      if (!location || location.length < 2) {
        showError('location', 'Enter the event location');
        hasError = true;
      } else if (location.length > 200) {
        showError('location', 'Location must be under 200 characters');
        hasError = true;
      }

      if (notes.length > 1000) {
        showError('notes', 'Notes must be under 1000 characters');
        hasError = true;
      }

      if (hasError) {
        return;
      }

      // Default client-side fallback WhatsApp message
      const serviceTitle = serviceNames[service] || service;
      let fallbackText = `Appointment enquiry\nName: ${name}\nPhone: ${phone}`;
      if (email) fallbackText += `\nEmail: ${email}`;
      fallbackText += `\nService: ${serviceTitle}\nDate: ${date}\nTime: ${time}\nLocation: ${location}`;
      if (notes) fallbackText += `\nNotes: ${notes}`;

      let waUrl = `https://wa.me/917219884594?text=${encodeURIComponent(fallbackText)}`;
      let assignedRef = null;

      // Disable submit button while sending
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Recording enquiry...';
      }

      try {
        const csrfToken = await getCsrfToken();
        const payload = {
          name,
          phone,
          email,
          service,
          date,
          time,
          location,
          notes,
          csrf_token: csrfToken || ''
        };

        const response = await fetch('api/book.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            ...(csrfToken ? { 'X-CSRF-Token': csrfToken } : {})
          },
          body: JSON.stringify(payload)
        });

        if (response.ok) {
          const result = await response.json();
          if (result && result.status === 'success' && result.data) {
            assignedRef = result.data.ref_code;
            if (result.data.whatsapp_url) {
              waUrl = result.data.whatsapp_url;
            }
          }
        } else if (response.status === 422) {
          const errData = await response.json();
          if (errData && errData.errors) {
            for (const [field, msg] of Object.entries(errData.errors)) {
              showError(field, msg);
            }
            if (submitBtn) {
              submitBtn.disabled = false;
              submitBtn.textContent = 'Send Enquiry';
            }
            return;
          }
        }
      } catch (err) {
        console.warn('Backend API submission skipped or failed, using WhatsApp direct fallback.', err);
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Send Enquiry';
        }
      }

      if (sendWhatsAppBtn) {
        sendWhatsAppBtn.href = waUrl;
      }

      // If reference code assigned, update success message
      if (assignedRef && successCard) {
        const subtext = successCard.querySelector('p');
        if (subtext) {
          subtext.innerHTML = `Your booking reference is <strong style="color: var(--foreground);">${assignedRef}</strong>.<br>Tap below to send your enquiry on WhatsApp. Your appointment is confirmed once we reply.`;
        }
      }

      // Show success screen
      form.style.display = 'none';
      if (successCard) {
        successCard.style.display = 'block';
        successCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  }

  if (editDetailsBtn) {
    editDetailsBtn.addEventListener('click', () => {
      if (successCard && form) {
        successCard.style.display = 'none';
        form.style.display = 'grid';
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  }
});
