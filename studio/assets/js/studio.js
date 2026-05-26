// ── Fenor Studio — shared JS ──────────────────────────────────────────────────

// Language toggle (PT ↔ EN)
// Stores preference in localStorage key 'fenor_studio_lang'
// Applies data-pt / data-en attributes to all matching elements
// Also respects .pt-only / .en-only CSS classes (see studio.css)

(function () {
  var STORAGE_KEY = 'fenor_studio_lang';

  function applyLang(lang) {
    document.documentElement.setAttribute('data-lang', lang);
    var label = document.getElementById('lang-label');
    if (label) label.textContent = lang === 'pt' ? 'EN' : 'PT';

    document.querySelectorAll('[data-pt][data-en]').forEach(function (el) {
      var text = el.getAttribute('data-' + lang);
      if (text === null) return;
      if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
        el.placeholder = text;
      } else if (el.innerHTML !== el.textContent) {
        el.innerHTML = text;
      } else {
        el.textContent = text;
      }
    });
  }

  window.studioToggleLang = function () {
    var current = document.documentElement.getAttribute('data-lang') || 'pt';
    var next = current === 'pt' ? 'en' : 'pt';
    localStorage.setItem(STORAGE_KEY, next);
    applyLang(next);
  };

  // Apply saved language on page load
  window.addEventListener('DOMContentLoaded', function () {
    var saved = localStorage.getItem(STORAGE_KEY) || 'pt';
    applyLang(saved);
  });

  // Current language helper
  window.studioLang = function () {
    return document.documentElement.getAttribute('data-lang') || 'pt';
  };
})();
