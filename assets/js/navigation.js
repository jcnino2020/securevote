/* Student navigation and election discovery interactions. */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    // On compact login screens, anchor preferences beside the card's logo.
    var loginPreferences = document.querySelector('.page-login .auth-layout > .display-settings');
    var loginHead = document.querySelector('.page-login .auth-card__head');
    var loginBrand = loginHead && loginHead.querySelector('.brand');
    if (loginPreferences && loginBrand) {
      var preferencesHome = document.createComment('Login preferences position');
      loginPreferences.parentNode.insertBefore(preferencesHome, loginPreferences);
      var logoRow = document.createElement('div');
      logoRow.className = 'auth-card__brand-row';
      loginHead.insertBefore(logoRow, loginBrand);
      logoRow.appendChild(loginBrand);
      var compactLogin = window.matchMedia('(max-width: 720px)');
      function placeLoginPreferences() {
        loginPreferences.open = false;
        if (compactLogin.matches) logoRow.appendChild(loginPreferences);
        else preferencesHome.parentNode.insertBefore(loginPreferences, preferencesHome.nextSibling);
      }
      placeLoginPreferences();
      compactLogin.addEventListener('change', placeLoginPreferences);
    }

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
        if (open) {
          var settings = document.querySelector('.display-settings[open]');
          if (settings) settings.open = false;
        }
        if (returnFocus) menuToggle.focus();
      }

      menuToggle.addEventListener('click', function () {
        setMenuOpen(menuPanel.hidden);
      });
      menuPanel.addEventListener('focusout', function (event) {
        if (event.relatedTarget && !publicMenu.contains(event.relatedTarget)) setMenuOpen(false);
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
        if (window.innerWidth >= 1300 && !menuPanel.hidden) setMenuOpen(false);
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
      var query = search ? search.value.trim().toLocaleLowerCase() : '';
      var visible = 0;
      cards.forEach(function (card) {
        var status = card.getAttribute('data-status');
        var title = (card.getAttribute('data-title') || '').toLocaleLowerCase();
        var matchesStatus = selected === 'all' || status === selected || (selected === 'past' && (status === 'closed' || status === 'archived'));
        var matchesSearch = !query || title.indexOf(query) !== -1;
        card.hidden = !(matchesStatus && matchesSearch);
        if (!card.hidden) visible++;
      });
      if (count) {
        count.textContent = document.documentElement.lang === 'fil'
          ? visible + ' sa ' + cards.length + ' eleksiyon ang ipinapakita'
          : visible + ' of ' + cards.length + ' election' + (cards.length === 1 ? '' : 's') + ' shown';
      }
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
    if (search) search.addEventListener('input', update);
    if (reset) reset.addEventListener('click', function () {
      if (search) search.value = '';
      buttons[0].click();
      if (search) search.focus();
    });
    update();
  });
})();
