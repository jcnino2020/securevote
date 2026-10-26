/* ==========================================================================
   SecureVote - Secure Online Voting System
   assets/js/app.js (2026 Production Accessible Suite)

   WCAG 2.2 Level AA Accessible Enhancements:
     - Instant Flash-Free Theme Engine (Light / Dark Mode)
     - Accessible Password Visibility Controls
     - Accessible Mobile Navigation Drawer (Focus Trap & Escape handling)
     - Interactive Ballot Engine (Real-Time Selection Counters & Limits)
     - Candidate Platform Expanders
     - 1-Click Clipboard Copy with Accessible Live Announcements
     - Double-Submit Protection with Loading States
     - Accessible Modal Dialogs & Dismissable Alerts
   ========================================================================== */

(function () {
  'use strict';

  var STORAGE_KEY = 'securevote.theme';

  /* ======================================================================
     1. Instant Theme Engine (Runs before first paint)
     ==================================================================== */

  function storedTheme() {
    try {
      var value = window.localStorage.getItem(STORAGE_KEY);
      return value === 'light' || value === 'dark' ? value : null;
    } catch (e) {
      return null;
    }
  }

  function systemTheme() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
      ? 'dark'
      : 'light';
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    document.documentElement.style.colorScheme = theme;

    var toggle = document.querySelector('[data-theme-toggle]');
    if (toggle) {
      toggle.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
      var label = theme === 'dark' ? 'Switch to light appearance' : 'Switch to dark appearance';
      toggle.setAttribute('aria-label', label);
      toggle.setAttribute('title', label);
      var valueLabel = toggle.querySelector('[data-theme-label]');
      if (valueLabel) valueLabel.textContent = theme === 'dark' ? 'Dark mode' : 'Light mode';
    }
  }

  // Apply saved theme immediately
  var currentTheme = storedTheme() || systemTheme();
  applyTheme(currentTheme);

  /* ======================================================================
     2. Live Screen Reader Announcer
     ==================================================================== */

  var liveAnnouncer = null;

  function announce(message) {
    if (!liveAnnouncer) {
      liveAnnouncer = document.createElement('div');
      liveAnnouncer.setAttribute('role', 'status');
      liveAnnouncer.setAttribute('aria-live', 'polite');
      liveAnnouncer.setAttribute('aria-atomic', 'true');
      liveAnnouncer.className = 'visually-hidden';
      document.body.appendChild(liveAnnouncer);
    }
    liveAnnouncer.textContent = '';
    window.setTimeout(function () {
      liveAnnouncer.textContent = message;
    }, 50);
  }

  /* ======================================================================
     3. DOM Ready Initialization
     ==================================================================== */

  document.addEventListener('DOMContentLoaded', function () {
    initThemeToggle();
    initAccessibleContent();
    initSidebarDrawer();
    initPasswordToggles();
    initBallotEngine();
    initBioToggles();
    initCopyButtons();
    initSubmitGuards();
    initAlertDismissals();
    initConfirmPrompts();
    initTableFilters();
    initDialogs();
  });

  /* ======================================================================
     4. Theme Toggle Listener
     ==================================================================== */

  function initThemeToggle() {
    var toggle = document.querySelector('[data-theme-toggle]');
    if (toggle) {
      toggle.addEventListener('click', function () {
        var active = document.documentElement.getAttribute('data-theme') || 'light';
        var next = active === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        try {
          window.localStorage.setItem(STORAGE_KEY, next);
        } catch (e) {}
        announce('Color appearance switched to ' + next + ' mode');
      });
    }

    if (window.matchMedia) {
      var media = window.matchMedia('(prefers-color-scheme: dark)');
      var handler = function (event) {
        if (!storedTheme()) {
          applyTheme(event.matches ? 'dark' : 'light');
        }
      };
      if (media.addEventListener) {
        media.addEventListener('change', handler);
      } else if (media.addListener) {
        media.addListener(handler);
      }
    }
  }

  // Progressive enhancement: preserve native forms and table relationships.
  function initAccessibleContent() {
    var header = document.querySelector('.topbar');
    if (header) {
      var measureHeader = function () {
        document.documentElement.style.setProperty('--header-offset', Math.ceil(header.getBoundingClientRect().height + 16) + 'px');
      };
      measureHeader();
      if (window.ResizeObserver) new ResizeObserver(measureHeader).observe(header);
      else window.addEventListener('resize', measureHeader);
    }
    document.querySelectorAll('.table-wrap, .traffic-table-wrap, .comparison-scroll').forEach(function (region, index) {
      var table = region.querySelector('table');
      if (!table) return;
      table.querySelectorAll('thead th:not([scope])').forEach(function (cell) { cell.scope = 'col'; });
      var refresh = function () {
        var scrolls = region.scrollWidth > region.clientWidth + 1;
        if (scrolls) {
          region.tabIndex = 0;
          region.setAttribute('role', 'region');
          if (!region.hasAttribute('aria-label') && !region.hasAttribute('aria-labelledby')) {
            var caption = table.querySelector('caption');
            region.setAttribute('aria-label', caption ? caption.textContent.trim() : 'Scrollable data table ' + (index + 1));
          }
        } else if (region.getAttribute('role') === 'region') {
          region.removeAttribute('tabindex');
        }
      };
      refresh();
      if (window.ResizeObserver) new ResizeObserver(refresh).observe(region);
      else window.addEventListener('resize', refresh);
    });
    document.querySelectorAll('.form-field').forEach(function (field, index) {
      var error = field.querySelector('.field-error');
      if (!error) return;
      if (!error.id) error.id = 'field-feedback-' + index;
      field.querySelectorAll('input:not([type=hidden]), select, textarea').forEach(function (input) {
        input.setAttribute('aria-invalid', 'true');
        var descriptions = (input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        if (descriptions.indexOf(error.id) < 0) descriptions.push(error.id);
        input.setAttribute('aria-describedby', descriptions.join(' '));
      });
    });
    document.querySelectorAll('img').forEach(function (image) {
      image.decoding = 'async';
      if (!image.hasAttribute('loading') && image.getBoundingClientRect().top > window.innerHeight) image.loading = 'lazy';
    });
  }

  /* ======================================================================
     5. Mobile Navigation Drawer & Backdrop
     ==================================================================== */

  function initSidebarDrawer() {
    var sidebar = document.getElementById('sidebar');
    if (!sidebar) return;

    var backdrop = document.querySelector('.sidebar-backdrop');
    var toggles = document.querySelectorAll('[data-sidebar-toggle]');
    var closers = document.querySelectorAll('[data-sidebar-close]');
    var lastFocusedElement = null;

    function setSidebarAccess(open) {
      var mobile = window.innerWidth <= 900;
      sidebar.inert = mobile && !open;
      if (mobile && !open) sidebar.setAttribute('aria-hidden', 'true');
      else sidebar.removeAttribute('aria-hidden');
    }

    setSidebarAccess(false);

    function openSidebar() {
      lastFocusedElement = document.activeElement;
      sidebar.classList.add('is-open');
      setSidebarAccess(true);
      if (backdrop) backdrop.hidden = false;
      document.body.style.overflow = 'hidden';
      var shell = document.querySelector('.shell');
      if (shell) shell.inert = true;

      Array.prototype.forEach.call(toggles, function (btn) {
        btn.setAttribute('aria-expanded', 'true');
        btn.setAttribute('aria-label', 'Close navigation menu');
      });

      var closeBtn = sidebar.querySelector('.sidebar__close');
      if (closeBtn) closeBtn.focus();
      announce('Navigation menu opened');
    }

    function closeSidebar(returnFocus) {
      sidebar.classList.remove('is-open');
      if (backdrop) backdrop.hidden = true;
      document.body.style.overflow = '';
      var shell = document.querySelector('.shell');
      if (shell) shell.inert = false;

      Array.prototype.forEach.call(toggles, function (btn) {
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-label', 'Open navigation menu');
      });

      if (returnFocus && lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
        lastFocusedElement.focus();
      }
      setSidebarAccess(false);
      announce('Navigation menu closed');
    }

    Array.prototype.forEach.call(toggles, function (btn) {
      btn.addEventListener('click', function () {
        var isOpen = sidebar.classList.contains('is-open');
        if (isOpen) closeSidebar(true);
        else openSidebar();
      });
    });

    Array.prototype.forEach.call(closers, function (btn) {
      btn.addEventListener('click', function () { closeSidebar(true); });
    });

    sidebar.querySelectorAll('.nav a').forEach(function (link) {
      link.addEventListener('click', function () {
        if (sidebar.classList.contains('is-open')) closeSidebar(false);
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
        closeSidebar(true);
      }
      if (e.key === 'Tab' && sidebar.classList.contains('is-open')) {
        var focusable = Array.prototype.slice.call(sidebar.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex="0"]'))
          .filter(function (element) { return element.getClientRects().length > 0; });
        if (!focusable.length) return;
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth > 900 && sidebar.classList.contains('is-open')) {
        closeSidebar(false);
      }
      setSidebarAccess(sidebar.classList.contains('is-open'));
    });
  }

  /* ======================================================================
     6. Password Visibility Toggles (WCAG 2.2)
     ==================================================================== */

  function initPasswordToggles() {
    var buttons = document.querySelectorAll('[data-toggle-password]');
    Array.prototype.forEach.call(buttons, function (button) {
      button.addEventListener('click', function () {
        var targetSelector = button.getAttribute('data-toggle-password');
        var input = targetSelector
          ? document.querySelector(targetSelector)
          : button.parentElement.querySelector('input[type="password"], input[type="text"]');

        if (!input) return;

        var isPassword = input.getAttribute('type') === 'password';
        var nextType = isPassword ? 'text' : 'password';
        input.setAttribute('type', nextType);

        button.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
        var label = isPassword ? 'Hide password' : 'Show password';
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);

        var eyeIcon = button.querySelector('.icon-eye');
        var eyeOffIcon = button.querySelector('.icon-eye-off');
        if (eyeIcon && eyeOffIcon) {
          eyeIcon.hidden = isPassword;
          eyeOffIcon.hidden = !isPassword;
        }

        if (typeof input.selectionStart === 'number') {
          var len = input.value.length;
          input.setSelectionRange(len, len);
        }

        announce(isPassword ? 'Password revealed.' : 'Password hidden.');
      });
    });
  }

  /* ======================================================================
     7. Interactive Tactile Ballot Engine
     ==================================================================== */

  function initBallotEngine() {
    var form = document.getElementById('ballot-form');
    if (!form) return;

    var positions = form.querySelectorAll('.ballot__position');

    Array.prototype.forEach.call(positions, function (pos) {
      var max = parseInt(pos.getAttribute('data-max') || '1', 10);
      var counter = pos.querySelector('.ballot__counter');
      var warning = pos.querySelector('[data-ballot-warning]');
      var checkboxes = pos.querySelectorAll('input[type="checkbox"]');
      var radios = pos.querySelectorAll('input[type="radio"]');

      function updateMultiChoice() {
        var checked = pos.querySelectorAll('input[type="checkbox"]:checked');
        var count = checked.length;

        if (counter) {
          counter.textContent = (document.documentElement.lang === 'fil' ? count + ' sa ' + max + ' ang napili' : count + ' of ' + max + ' selected');
          if (count >= max) {
            counter.classList.add('is-full');
          } else {
            counter.classList.remove('is-full');
          }
        }

        // Disable unchecked if max reached
        Array.prototype.forEach.call(checkboxes, function (cb) {
          if (!cb.checked) {
            if (count >= max) {
              cb.disabled = true;
              cb.closest('.choice').classList.add('is-disabled');
            } else {
              cb.disabled = false;
              cb.closest('.choice').classList.remove('is-disabled');
            }
          }
        });

        if (count > max && warning) {
          warning.hidden = false;
          warning.textContent = 'Maximum ' + max + ' candidates allowed.';
        } else if (warning) {
          warning.hidden = true;
        }
      }

      function updateSingleChoice() {
        var checked = pos.querySelector('input[type="radio"]:checked');
        var isAbstain = checked && checked.value === '';

        if (counter) {
          if (isAbstain || !checked) {
            counter.textContent = (document.documentElement.lang === 'fil' ? '0 sa 1 ang napili (Nilaktawan)' : '0 of 1 selected (Abstained)');
            counter.classList.remove('is-full');
          } else {
            counter.textContent = (document.documentElement.lang === 'fil' ? '1 sa 1 ang napili' : '1 of 1 selected');
            counter.classList.add('is-full');
          }
        }
      }

      if (checkboxes.length > 0) {
        Array.prototype.forEach.call(checkboxes, function (cb) {
          cb.addEventListener('change', updateMultiChoice);
        });
        updateMultiChoice();
      }

      if (radios.length > 0) {
        Array.prototype.forEach.call(radios, function (rd) {
          rd.addEventListener('change', updateSingleChoice);
        });
        updateSingleChoice();
      }
    });
  }

  /* ======================================================================
     8. Candidate Platform & Bio Expanders
     ==================================================================== */

  function initBioToggles() {
    var buttons = document.querySelectorAll('[data-toggle-bio]');
    Array.prototype.forEach.call(buttons, function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var targetId = btn.getAttribute('aria-controls');
        var target = targetId ? document.getElementById(targetId) : null;
        if (!target) return;

        var isExpanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
        target.hidden = isExpanded;

        var label = btn.querySelector('.bio-toggle-label');
        if (label) {
          label.textContent = document.documentElement.lang === 'fil' ? (isExpanded ? 'Basahin ang plataporma at talambuhay' : 'Itago ang plataporma at talambuhay') : (isExpanded ? 'Read platform & bio' : 'Hide platform & bio');
        }
      });
    });
  }

  /* ======================================================================
     9. 1-Click Clipboard Copy with Live Feedback
     ==================================================================== */

  function initCopyButtons() {
    var buttons = document.querySelectorAll('[data-copy]');
    Array.prototype.forEach.call(buttons, function (btn) {
      btn.addEventListener('click', function () {
        var targetSelector = btn.getAttribute('data-copy');
        var target = targetSelector ? document.querySelector(targetSelector) : null;
        if (!target) return;

        var text = target.value || target.textContent || '';
        text = text.trim();

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(function () {
            showCopySuccess(btn);
          });
        } else {
          var textarea = document.createElement('textarea');
          textarea.value = text;
          textarea.style.position = 'fixed';
          textarea.style.opacity = '0';
          document.body.appendChild(textarea);
          textarea.select();
          try {
            document.execCommand('copy');
            showCopySuccess(btn);
          } catch (err) {}
          document.body.removeChild(textarea);
        }
      });
    });

    function showCopySuccess(btn) {
      var labelSpan = btn.querySelector('[data-copy-label]') || btn.querySelector('span:not(.btn__icon)');
      var originalText = labelSpan ? labelSpan.textContent : '';

      if (labelSpan) labelSpan.textContent = 'Copied!';
      btn.classList.add('btn--success');

      announce('Copied to clipboard');

      window.setTimeout(function () {
        if (labelSpan) labelSpan.textContent = originalText;
        btn.classList.remove('btn--success');
      }, 2000);
    }
  }

  /* ======================================================================
     10. Form Submission Guards & Loading States
     ==================================================================== */

  function initSubmitGuards() {
    var forms = document.querySelectorAll('form');
    Array.prototype.forEach.call(forms, function (form) {
      form.addEventListener('submit', function (e) {
        var submitBtn = form.querySelector('[data-submit-once]');
        if (submitBtn) {
          if (submitBtn.disabled) {
            e.preventDefault();
            return;
          }

          var labelText = submitBtn.getAttribute('data-submitting-label');
          if (labelText) {
            var span = submitBtn.querySelector('span:not(.btn__icon)') || submitBtn;
            span.textContent = labelText;
          }
          submitBtn.disabled = true;
          submitBtn.classList.add('is-submitting');
        }
      });
    });
  }

  /* ======================================================================
     11. Dismissable Alerts
     ==================================================================== */

  function initAlertDismissals() {
    var buttons = document.querySelectorAll('[data-dismiss-alert]');
    Array.prototype.forEach.call(buttons, function (btn) {
      btn.addEventListener('click', function () {
        var alert = btn.closest('.alert');
        if (alert) {
          alert.style.opacity = '0';
          alert.style.transform = 'scale(0.98)';
          window.setTimeout(function () {
            alert.remove();
          }, 180);
        }
      });
    });
  }

  /* ======================================================================
     12. Confirmation Prompts
     ==================================================================== */

  function initConfirmPrompts() {
    var buttons = document.querySelectorAll('[data-confirm]');
    Array.prototype.forEach.call(buttons, function (btn) {
      btn.addEventListener('click', function (e) {
        var message = btn.getAttribute('data-confirm') || 'Are you sure you wish to proceed?';
        if (!window.confirm(message)) {
          e.preventDefault();
        }
      });
    });
  }

  /* ======================================================================
     13. Table Search Filtering Utility
     ==================================================================== */

  function initTableFilters() {
    var searchInputs = document.querySelectorAll('[data-table-filter]');
    Array.prototype.forEach.call(searchInputs, function (input) {
      var targetTableSelector = input.getAttribute('data-table-filter');
      var table = targetTableSelector ? document.querySelector(targetTableSelector) : null;
      if (!table) return;

      input.addEventListener('input', function () {
        var query = input.value.toLowerCase().trim();
        var rows = table.querySelectorAll('tbody tr');
        var matchCount = 0;

        Array.prototype.forEach.call(rows, function (row) {
          var text = row.textContent.toLowerCase();
          if (text.indexOf(query) !== -1) {
            row.style.display = '';
            matchCount++;
          } else {
            row.style.display = 'none';
          }
        });

        announce(matchCount + ' matching entries found.');
      });
    });
  }

  /* ======================================================================
     14. Accessible Dialogs & Backdrop Dismissal
     ==================================================================== */

  function initDialogs() {
    var dialogs = document.querySelectorAll('dialog');
    Array.prototype.forEach.call(dialogs, function (dialog) {
      dialog.addEventListener('click', function (e) {
        var rect = dialog.getBoundingClientRect();
        var isInDialog = (rect.top <= e.clientY && e.clientY <= rect.top + rect.height &&
          rect.left <= e.clientX && e.clientX <= rect.left + rect.width);
        if (!isInDialog) {
          dialog.close();
        }
      });
    });
  }

})();
