/* Apply appearance before paint; all interactions remain deferred. */
(function () {
  'use strict';
  var theme;
  try { theme = localStorage.getItem('securevote.theme'); } catch (error) {}
  if (theme !== 'light' && theme !== 'dark') {
    theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  document.documentElement.dataset.theme = theme;
  document.documentElement.style.colorScheme = theme;
})();
