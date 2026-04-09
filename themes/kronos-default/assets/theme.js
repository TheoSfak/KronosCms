/* KronosCMS — theme.js (kronos-default)
   Minimal frontend JS for the public theme. */
(function () {
  'use strict';

  // Copy public/assets/css/theme.css path is served via kronos_asset()
  // No framework needed — keep it lean.

  // Smooth scroll for anchor links
  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth' });
      }
    });
  });
})();
