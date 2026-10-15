/* Student navigation and election discovery interactions. */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var collapse = document.querySelector('[data-sidebar-collapse]');
    if (collapse) {
      var key = 'securevote.sidebar.collapsed';
      var saved = false;
      try { saved = window.localStorage.getItem(key) === 'true'; } catch (error) {}
      setCollapsed(saved);

      collapse.addEventListener('click', function () {
        var next = !document.body.classList.contains('sidebar-collapsed');
        setCollapsed(next);
        try { window.localStorage.setItem(key, String(next)); } catch (error) {}
      });

      function setCollapsed(value) {
        document.body.classList.toggle('sidebar-collapsed', value);
        collapse.setAttribute('aria-expanded', String(!value));
        collapse.setAttribute('aria-label', value ? 'Expand navigation' : 'Collapse navigation');
        collapse.setAttribute('title', value ? 'Expand navigation' : 'Collapse navigation');
      }
    }

    var publicMenu = document.querySelector('.public-menu');
    if (publicMenu) {
      var menuToggle = publicMenu.querySelector('[data-public-menu-toggle]');
      var menuPanel = publicMenu.querySelector('.public-menu__panel');

      function setMenuOpen(open, returnFocus) {
        menuPanel.hidden = !open;
        menuToggle.setAttribute('aria-expanded', String(open));
        menuToggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
        publicMenu.classList.toggle('is-open', open);
        if (returnFocus) menuToggle.focus();
      }

      menuToggle.addEventListener('click', function () {
        setMenuOpen(menuPanel.hidden);
      });
      menuPanel.addEventListener('click', function (event) {
        if (event.target.closest('a')) setMenuOpen(false);
      });
      document.addEventListener('click', function (event) {
        if (!publicMenu.contains(event.target) && !menuPanel.hidden) setMenuOpen(false);
      });
      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !menuPanel.hidden) {
          setMenuOpen(false, true);
        }
      });
      window.addEventListener('resize', function () {
        if (window.innerWidth > 1100 && !menuPanel.hidden) setMenuOpen(false);
      });
    }

    var explorer = document.querySelector('[data-election-explorer]');
    if (!explorer) return;

    var search = explorer.querySelector('[data-election-search]');
    var buttons = Array.prototype.slice.call(explorer.querySelectorAll('[data-election-filter]'));
    var cards = Array.prototype.slice.call(document.querySelectorAll('[data-election-card]'));
    var count = explorer.querySelector('[data-election-count]');
    var empty = document.querySelector('[data-election-empty]');
    var reset = empty && empty.querySelector('[data-election-reset]');
    var selected = 'all';

    function update() {
      var query = search.value.trim().toLocaleLowerCase();
      var visible = 0;
      cards.forEach(function (card) {
        var status = card.getAttribute('data-status');
        var title = (card.getAttribute('data-title') || '').toLocaleLowerCase();
        var matchesStatus = selected === 'all' || status === selected || (selected === 'past' && (status === 'closed' || status === 'archived'));
        var matchesSearch = !query || title.indexOf(query) !== -1;
        card.hidden = !(matchesStatus && matchesSearch);
        if (!card.hidden) visible++;
      });
      count.textContent = visible + ' of ' + cards.length + ' election' + (cards.length === 1 ? '' : 's') + ' shown';
      if (empty) empty.hidden = visible !== 0;
    }

    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        selected = button.getAttribute('data-election-filter');
        buttons.forEach(function (item) {
          item.setAttribute('aria-pressed', String(item === button));
        });
        update();
      });
    });
    search.addEventListener('input', update);
    if (reset) reset.addEventListener('click', function () {
      search.value = '';
      buttons[0].click();
      search.focus();
    });
    update();
  });
})();
