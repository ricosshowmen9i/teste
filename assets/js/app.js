/**
 * SETE — app.js
 * Main application orchestrator
 */

'use strict';

// ── CSRF helper ───────────────────────────────────────────────────
const CSRF = {
  get() {
    return window.SETE_CSRF || '';
  },
};

// ── Fetch helpers ─────────────────────────────────────────────────
async function apiGet(url) {
  const res = await fetch(url, { credentials: 'same-origin' });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

async function apiPost(url, data) {
  const form = new FormData();
  form.append('csrf_token', CSRF.get());
  for (const [k, v] of Object.entries(data)) {
    if (v !== undefined && v !== null) form.append(k, v);
  }
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    body: form,
  });
  return res.json();
}

async function apiPostFile(url, formData) {
  formData.append('csrf_token', CSRF.get());
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    body: formData,
  });
  return res.json();
}

// ── Toast notifications ───────────────────────────────────────────
function showToast(message, type = 'info', duration = 3500) {
  const container = document.getElementById('toast-container');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(30px)';
    toast.style.transition = 'all .3s ease';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

// ── Modal helpers ─────────────────────────────────────────────────
function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('open');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('open');
}

function closeAllModals() {
  document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('open'));
}

// ── Theme management ──────────────────────────────────────────────
function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme || 'green');
}

function saveTheme(theme) {
  applyTheme(theme);
}

// ── Confirm dialog ────────────────────────────────────────────────
function confirmAction(message) {
  return window.confirm(message);
}

// ── Debounce ──────────────────────────────────────────────────────
function debounce(fn, delay) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}

// ── Format date/time ──────────────────────────────────────────────
function formatTime(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr.replace(' ', 'T'));
  const now = new Date();
  const diffMs = now - d;
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffDays === 0) {
    return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  }
  if (diffDays === 1) return 'Ontem';
  if (diffDays < 7) return d.toLocaleDateString('pt-BR', { weekday: 'short' });
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

function formatDateLabel(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr.replace(' ', 'T'));
  const now = new Date();
  const diffDays = Math.floor((now - d) / 86400000);
  if (diffDays === 0) return 'Hoje';
  if (diffDays === 1) return 'Ontem';
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
}

// ── Escape HTML ───────────────────────────────────────────────────
function escHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

// ── Initialize ────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Apply saved theme
  const user = window.SETE_USER;
  if (user && user.theme) {
    applyTheme(user.theme);
  }

  // Header buttons
  const btnContacts = document.getElementById('btn-contacts');
  const btnProfile  = document.getElementById('btn-profile');
  const btnAdmin    = document.getElementById('btn-admin');
  const btnLogout   = document.getElementById('btn-logout');

  if (btnContacts) {
    btnContacts.addEventListener('click', () => {
      openModal('modal-contacts');
      if (window.ChatManager) ChatManager.loadCharacters();
    });
  }

  if (btnProfile) {
    btnProfile.addEventListener('click', () => {
      openModal('modal-profile');
      if (window.ProfileManager) ProfileManager.load();
    });
  }

  if (btnAdmin) {
    btnAdmin.addEventListener('click', () => {
      openModal('modal-admin');
      if (window.AdminManager) AdminManager.init();
    });
  }

  if (btnLogout) {
    btnLogout.addEventListener('click', async () => {
      if (!confirmAction('Deseja sair?')) return;
      await apiPost('api/auth.php', { action: 'logout' });
      location.reload();
    });
  }

  // Close modals on overlay click
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) overlay.classList.remove('open');
    });
  });

  // Close buttons
  document.querySelectorAll('.modal-close').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.modal-overlay');
      if (modal) modal.classList.remove('open');
    });
  });

  // Keyboard: Escape closes modals
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const openModals = [...document.querySelectorAll('.modal-overlay.open')];
      if (openModals.length > 0) {
        openModals[openModals.length - 1].classList.remove('open');
      }
    }
  });

  // Init modules if present
  if (window.ChatManager) ChatManager.init();
  if (window.AudioManager && typeof AudioManager.init === 'function') AudioManager.init();
});

// Export globals
window.apiGet        = apiGet;
window.apiPost       = apiPost;
window.apiPostFile   = apiPostFile;
window.showToast     = showToast;
window.openModal     = openModal;
window.closeModal    = closeModal;
window.closeAllModals = closeAllModals;
window.applyTheme    = applyTheme;
window.saveTheme     = saveTheme;
window.confirmAction = confirmAction;
window.debounce      = debounce;
window.formatTime    = formatTime;
window.formatDateLabel = formatDateLabel;
window.escHtml       = escHtml;
