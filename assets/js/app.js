/* ==========================================================================
   SecureVote - Secure Online Voting System
   assets/js/app.js (2026 Modernized & Accessible Suite)

   Progressive enhancement only. The system works with JavaScript switched
   off: every form posts normally and the server re-validates everything.

   WCAG 2.2 Level AA Accessible Enhancements:
     - Accessible Authentication: Show/Hide Password toggles
     - Focus trapping & focus restoration for modals and mobile drawer
     - Screen-reader friendly live announcements (table search, copy tokens)
     - Touch-target ergonomics & keyboard trap avoidance
     - Progressive submission guards with animated loading indicators
   ========================================================================== */

(function () {
  'use strict';

  var STORAGE_KEY = 'securevote.theme';

  /* ======================================================================
     1. Theme Management (Instant Flash-Free Execution)
     ==================================================================== */

  function storedTheme() {
    try {
      return window.localStorage.getItem(STORAGE_KEY);
    } catch (error) {
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
      toggle.setAttribute(
        'aria-label',
        theme === 'dark' ? 'Switch to light appearance' : 'Switch to dark appearance'
      );
      toggle.setAttribute(
        'title',
        theme === 'dark' ? 'Switch to light appearance' : 'Switch to dark appearance'
      );
    }
  }

  // Runs immediately in <head> to prevent FOUC / wrong color flash
  var initialTheme = storedTheme() || systemTheme();
  applyTheme(initialTheme);

  /* ======================================================================
     2. Live Screen Reader Announcer Utility
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
    var toggle = document.querySelector('[data-theme-toggle]');

    if (toggle) {
      toggle.addEventListener('click', function () {
        var current = document.documentElement.getAttribute('data-theme') || 'light';
        var next = current === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        try {
          window.localStorage.setItem(STORAGE_KEY, next);
        } catch (error) {}
        announce('Color appearance switched to ' + next + ' mode');
      });
    }

    if (window.matchMedia) {
      var query = window.matchMedia('(prefers-color-scheme: dark)');
      var onChange = function (event) {
        if (!storedTheme()) {
          applyTheme(event.matches ? 'dark' : 'light');
        }
      };
      if (query.addEventListener) {
        query.addEventListener('change', onChange);
      } else if (query.addListener) {
        query.addListener(onChange);
      }
    }

    initSidebar();
    initPasswordToggles();
    initConfirms();
    initBallot();
    initCandidateToggles();
    initCopyButtons();
    initTableFilters();
    initModals();
    initAutoSubmit();
    initSubmitGuard();
    initAlertDismissals();
  });

  /* ======================================================================
     4. Password Visibility Toggles (WCAG 2.2 Accessible Authentication)
     ==================================================================== */

  function initPasswordToggles() {
    var toggleButtons = document.querySelectorAll('[data-toggle-password]');

    Array.prototype.forEach.call(toggleButtons, function (button) {
      button.addEventListener('click', function () {
        var targetSelector = button.getAttribute('data-toggle-password');
        var input = targetSelector
          ? document.querySelector(targetSelector)
          : button.parentElement.querySelector('input[type="password"], input[type="text"]');

        if (!input) return;

        var isPassword = input.getAttribute('type') === 'password';
        var newType = isPassword ? 'text' : 'password';
        input.setAttribute('type', newType);

        button.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
        button.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        button.setAttribute('title', isPassword ? 'Hide password' : 'Show password');

        var eyeIcon = button.querySelector('.icon-eye');
        var eyeOffIcon = button.querySelector('.icon-eye-off');

        if (eyeIcon && eyeOffIcon) {
          eyeIcon.hidden = isPassword;
          eyeOffIcon.hidden = !isPassword;
        }

        // Keep cursor position at end of input
        if (typeof input.selectionStart === 'number') {
          var len = input.value.length;
          input.setSelectionRange(len, len);
        }

        announce(isPassword ? 'Password is now shown.' : 'Password is now hidden.');
      });
    });
  }

  /* ======================================================================
     5. Mobile Navigation Rail & Drawer (Accessible Focus Trap)
     ==================================================================== */

  function initSidebar() {
    var sidebar = document.getElementById('sidebar');
    if (!sidebar) return;

    var backdrop = document.querySelector('.sidebar-backdrop');
    var toggles = document.querySelectorAll('[data-sidebar-toggle]');
    var closers = document.querySelectorAll('[data-sidebar-close]');
    var menuButton = document.querySelector('[data-sidebar-toggle]');
    var lastFocusedBeforeOpen = null;

    function getFocusableElements(container) {
      return container.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
      );
    }

    function open() {
      lastFocusedBeforeOpen = document.activeElement;
      sidebar.classList.add('is-open');
      sidebar.setAttribute('aria-hidden', 'false');

      if (backdrop) {
        backdrop.hidden = false;
      }
      if (menuButton) {
        menuButton.setAttribute('aria-expanded', 'true');
        menuButton.setAttribute('aria-label', 'Close navigation');
      }

      var focusables = getFocusableElements(sidebar);
      if (focusables.length > 0) {
        focusables[0].focus();
      }
      announce('Navigation drawer opened.');
    }

    function close() {
      sidebar.classList.remove('is-open');
      sidebar.setAttribute('aria-hidden', 'true');

      if (backdrop) {
        backdrop.hidden = true;
      }
      if (menuButton) {
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.setAttribute('aria-label', 'Open navigation');
      }

      if (lastFocusedBeforeOpen && typeof lastFocusedBeforeOpen.focus === 'function') {
        lastFocusedBeforeOpen.focus();
      } else if (menuButton) {
        menuButton.focus();
      }
    }

    Array.prototype.forEach.call(toggles, function (button) {
      button.addEventListener('click', function () {
        if (sidebar.classList.contains('is-open')) {
          close();
        } else {
          open();
        }
      });
    });

    Array.prototype.forEach.call(closers, function (button) {
      button.addEventListener('click', close);
    });

    // Close on mobile nav click
    var navLinks = sidebar.querySelectorAll('.nav__item');
    Array.prototype.forEach.call(navLinks, function (link) {
      link.addEventListener('click', function () {
        if (window.innerWidth <= 1024 && sidebar.classList.contains('is-open')) {
          close();
        }
      });
    });

    // Focus trapping & Escape key
    sidebar.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        close();
        return;
      }

      if (event.key === 'Tab' && sidebar.classList.contains('is-open') && window.innerWidth <= 1024) {
        var focusables = getFocusableElements(sidebar);
        if (focusables.length === 0) return;

        var first = focusables[0];
        var last = focusables[focusables.length - 1];

        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
        close();
      }
    });
  }

  /* ======================================================================
     6. Confirmation for Destructive Actions
     ==================================================================== */

  function initConfirms() {
    Array.prototype.forEach.call(
      document.querySelectorAll('[data-confirm]'),
      function (node) {
        var message = node.getAttribute('data-confirm') || 'Are you sure you want to proceed?';

        if (node.tagName === 'FORM') {
          node.addEventListener('submit', function (event) {
            if (!window.confirm(message)) {
              event.preventDefault();
            }
          });
        } else if (node.tagName === 'A') {
          node.addEventListener('click', function (event) {
            if (!window.confirm(message)) {
              event.preventDefault();
            }
          });
        } else if (node.form) {
          node.form.addEventListener('submit', function (event) {
            if (event.submitter !== node) return;
            if (!window.confirm(message)) {
              event.preventDefault();
            }
          });
        }
      }
    );
  }

  /* ======================================================================
     7. Ballot: Selection Limits & Live Accessible Counters
     ==================================================================== */

  function initBallot() {
    var positions = document.querySelectorAll('.ballot__position[data-max]');

    Array.prototype.forEach.call(positions, function (position) {
      var max = parseInt(position.getAttribute('data-max'), 10) || 1;
      var inputs = position.querySelectorAll('.choice__input');
      var counter = position.querySelector('.ballot__counter');
      var warning = position.querySelector('[data-ballot-warning]');
      var titleElem = position.querySelector('.ballot__position-title');
      var positionName = titleElem ? titleElem.textContent.trim() : 'this position';

      function selected() {
        return Array.prototype.filter.call(inputs, function (input) {
          return input.checked && input.value !== '';
        });
      }

      function refresh(announceChange) {
        var count = selected().length;

        if (counter) {
          counter.textContent = count + ' of ' + max + ' selected';
          counter.classList.toggle('is-full', count >= max);
        }
        if (warning && count <= max) {
          warning.hidden = true;
        }

        if (announceChange) {
          announce(positionName + ': ' + count + ' of ' + max + ' selected.');
        }
      }

      Array.prototype.forEach.call(inputs, function (input) {
        input.addEventListener('click', function (event) {
          var count = selected().length;

          // If checking an input exceeds max selections:
          if (input.checked && input.value !== '' && count > max) {
            event.preventDefault();
            input.checked = false;
            if (warning) {
              warning.hidden = false;
              warning.textContent =
                'Selection limit reached for ' + positionName + ' (maximum ' + max +
                ' candidate' + (max > 1 ? 's' : '') + '). Please uncheck one candidate first.';
              announce(warning.textContent);
            }
          }
          refresh(false);
        });

        input.addEventListener('change', function () {
          refresh(true);
        });
      });

      refresh(false);
    });
  }

  /* ======================================================================
     8. Candidate Biography / Platform Expanders
     ==================================================================== */

  function initCandidateToggles() {
    var toggles = document.querySelectorAll('[data-toggle-bio]');
    Array.prototype.forEach.call(toggles, function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var targetId = btn.getAttribute('data-toggle-bio');
        var target = document.getElementById(targetId);
        if (!target) return;

        var isExpanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
        target.hidden = isExpanded;
        var label = btn.querySelector('.bio-toggle-label');
        if (label) {
          label.textContent = isExpanded ? 'Show platform' : 'Hide platform';
        }
      });
    });
  }

  /* ======================================================================
     9. Copy to Clipboard (Cryptographic Receipt Verification Tokens)
     ==================================================================== */

  function initCopyButtons() {
    Array.prototype.forEach.call(
      document.querySelectorAll('[data-copy]'),
      function (button) {
        button.addEventListener('click', function () {
          var selector = button.getAttribute('data-copy');
          var source = document.querySelector(selector);
          if (!source) return;

          var text = source.textContent.trim();
          var label = button.querySelector('[data-copy-label]');
          var originalText = label ? label.textContent : button.textContent;

          function done() {
            button.classList.add('is-copied');
            if (label) {
              label.textContent = 'Copied!';
            }
            announce('Receipt verification token copied to clipboard: ' + text);

            window.setTimeout(function () {
              button.classList.remove('is-copied');
              if (label) {
                label.textContent = originalText;
              }
            }, 2000);
          }

          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, function () {
              window.prompt('Copy this token:', text);
            });
          } else {
            window.prompt('Copy this token:', text);
          }
        });
      }
    );
  }

  /* ======================================================================
     10. Table Filter: Instant Live Search with Screen Reader Status
     ==================================================================== */

  function initTableFilters() {
    Array.prototype.forEach.call(
      document.querySelectorAll('[data-table-filter]'),
      function (input) {
        var table = document.querySelector(input.getAttribute('data-table-filter'));
        if (!table) return;

        var tbody = table.tBodies[0];
        if (!tbody) return;

        var rows = Array.prototype.slice.call(tbody.rows);
        var emptyRow = tbody.querySelector('[data-filter-empty]');
        var debounceTimer = null;

        function apply() {
          var needle = input.value.trim().toLowerCase();
          var visible = 0;

          rows.forEach(function (row) {
            if (row === emptyRow) return;
            var text = row.textContent.toLowerCase();
            var hit = needle === '' || text.indexOf(needle) !== -1;
            row.hidden = !hit;
            if (hit) {
              visible += 1;
            }
          });

          if (emptyRow) {
            emptyRow.hidden = visible > 0 || needle === '';
          }

          if (needle !== '') {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
              announce(visible + ' matching item' + (visible === 1 ? '' : 's') + ' found.');
            }, 300);
          }
        }

        input.addEventListener('input', apply);
        apply();
      }
    );
  }

  /* ======================================================================
     11. Native <dialog> Modal Windows (Focus Trap & Restoration)
     ==================================================================== */

  function initModals() {
    var lastFocused = null;

    function getFocusableElements(container) {
      return container.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
      );
    }

    Array.prototype.forEach.call(
      document.querySelectorAll('[data-modal-open]'),
      function (trigger) {
        trigger.addEventListener('click', function () {
          lastFocused = trigger;
          var dialog = document.querySelector(trigger.getAttribute('data-modal-open'));
          if (dialog && typeof dialog.showModal === 'function') {
            dialog.showModal();
            dialog.setAttribute('aria-modal', 'true');

            var focusables = getFocusableElements(dialog);
            if (focusables.length > 0) {
              focusables[0].focus();
            }
          }
        });
      }
    );

    Array.prototype.forEach.call(
      document.querySelectorAll('[data-modal-close]'),
      function (closer) {
        closer.addEventListener('click', function () {
          var dialog = closer.closest('dialog');
          if (dialog) {
            dialog.close();
            if (lastFocused && typeof lastFocused.focus === 'function') {
              lastFocused.focus();
            }
          }
        });
      }
    );

    // Backdrop click closes dialog & traps tab
    Array.prototype.forEach.call(document.querySelectorAll('dialog.modal'), function (dialog) {
      dialog.addEventListener('click', function (event) {
        if (event.target === dialog) {
          dialog.close();
          if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
          }
        }
      });

      dialog.addEventListener('close', function () {
        if (lastFocused && typeof lastFocused.focus === 'function') {
          lastFocused.focus();
        }
      });

      dialog.addEventListener('keydown', function (event) {
        if (event.key === 'Tab') {
          var focusables = getFocusableElements(dialog);
          if (focusables.length === 0) return;

          var first = focusables[0];
          var last = focusables[focusables.length - 1];

          if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
          } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
          }
        }
      });
    });
  }

  /* ======================================================================
     12. Auto-Submit Filter Selects
     ==================================================================== */

  function initAutoSubmit() {
    Array.prototype.forEach.call(
      document.querySelectorAll('[data-auto-submit]'),
      function (control) {
        control.addEventListener('change', function () {
          if (control.form) {
            control.form.submit();
          }
        });
      }
    );
  }

  /* ======================================================================
     13. Accessible Submit Guard with Loading Spinner
     ==================================================================== */

  function initSubmitGuard() {
    Array.prototype.forEach.call(document.querySelectorAll('form'), function (form) {
      form.addEventListener('submit', function (e) {
        var button = form.querySelector('[data-submit-once]');
        if (!button) return;

        // If form has invalid fields, don't trigger busy state
        if (form.checkValidity && !form.checkValidity()) {
          return;
        }

        form.setAttribute('aria-busy', 'true');
        var label = button.getAttribute('data-submitting-label') || 'Processing…';

        window.setTimeout(function () {
          button.disabled = true;
          button.classList.add('is-loading');
          button.innerHTML =
            '<svg class="btn__spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10"></path></svg><span>' +
            label +
            '</span>';
        }, 0);
      });
    });
  }

  /* ======================================================================
     14. Accessible Alert Dismissals
     ==================================================================== */

  function initAlertDismissals() {
    Array.prototype.forEach.call(document.querySelectorAll('.alert__close'), function (btn) {
      btn.addEventListener('click', function () {
        var alert = btn.closest('.alert');
        if (alert) {
          alert.style.opacity = '0';
          alert.style.transform = 'scale(0.98)';
          window.setTimeout(function () {
            alert.remove();
          }, 200);
        }
      });
    });
  }
})();
