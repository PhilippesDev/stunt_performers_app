/**
 * Cascade E-Commerce App Framework
 * Handles Toasts, Modals, Drawers, Dynamic Tabs, AJAX requests & Loading states
 */
window.Cascade = (function() {
  'use strict';

  // Ensure Toast container exists
  function ensureToastContainer() {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      document.body.appendChild(container);
    }
    return container;
  }

  // Toast System
  function showToast(message, type = 'info', duration = 4000) {
    const container = ensureToastContainer();
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    let icon = 'fa-info-circle';
    if (type === 'success') icon = 'fa-check-circle';
    if (type === 'error') icon = 'fa-exclamation-circle';
    if (type === 'warning') icon = 'fa-exclamation-triangle';

    toast.innerHTML = `
      <i class="fas ${icon}" style="font-size: 1.2rem;"></i>
      <span style="flex: 1; font-size: 0.875rem;">${message}</span>
      <button onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; cursor:pointer;">&times;</button>
    `;

    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('is-show'));

    if (duration > 0) {
      setTimeout(() => {
        toast.classList.remove('is-show');
        setTimeout(() => toast.remove(), 300);
      }, duration);
    }
  }

  // Generic AJAX Fetch helper
  async function fetchAPI(url, options = {}) {
    const defaults = {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    };
    
    const config = { ...defaults, ...options };
    
    try {
      const response = await fetch(url, config);
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.message || 'Une erreur est survenue');
      }
      return data;
    } catch (err) {
      console.error('Cascade Fetch Error:', err);
      throw err;
    }
  }

  // Modal controller
  function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('is-open');
  }

  function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('is-open');
  }

  // Drawer controller
  function openDrawer(id) {
    const drawer = document.getElementById(id);
    if (drawer) drawer.classList.add('is-open');
  }

  function closeDrawer(id) {
    const drawer = document.getElementById(id);
    if (drawer) drawer.classList.remove('is-open');
  }

  // Button Loading Helper
  function setButtonLoading(btn, isLoading, loadingText = 'Chargement...') {
    if (!btn) return;
    if (isLoading) {
      btn.dataset.originalText = btn.innerHTML;
      btn.classList.add('is-loading');
      btn.disabled = true;
      btn.innerHTML = `<span class="spinner"></span> ${loadingText}`;
    } else {
      btn.classList.remove('is-loading');
      btn.disabled = false;
      if (btn.dataset.originalText) {
        btn.innerHTML = btn.dataset.originalText;
      }
    }
  }

  // Tab controller for client-side tab switching without page reload
  function initTabs(containerSelector = '.tabs-container') {
    const containers = document.querySelectorAll(containerSelector);
    containers.forEach(container => {
      const tabs = container.querySelectorAll('.tab-btn');
      const panels = container.querySelectorAll('.tab-panel');

      tabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
          e.preventDefault();
          const target = tab.dataset.tab;

          tabs.forEach(t => t.classList.remove('is-active'));
          panels.forEach(p => p.style.display = 'none');

          tab.classList.add('is-active');
          const targetPanel = container.querySelector(`#tab-${target}`);
          if (targetPanel) targetPanel.style.display = 'block';
        });
      });
    });
  }

  // Auto initialize backdrops click to close
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
      backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) backdrop.classList.remove('is-open');
      });
    });
    document.querySelectorAll('.drawer-backdrop').forEach(backdrop => {
      backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) backdrop.classList.remove('is-open');
      });
    });
    initTabs();
  });

  return {
    toast: showToast,
    fetch: fetchAPI,
    modal: { open: openModal, close: closeModal },
    drawer: { open: openDrawer, close: closeDrawer },
    buttonLoading: setButtonLoading,
    initTabs: initTabs
  };
})();
