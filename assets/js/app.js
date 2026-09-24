/* ==========================================================================
   SecureVote - Secure Online Voting System
   assets/js/app.js

   Progressive enhancement only. The system works with JavaScript switched
   off: every form posts normally and the server re-validates everything.

   Nothing here is a security control. The selection limits, the confirmation
   dialogs and the duplicate-submit guard are conveniences; the server checks
   the same rules again, because client-side rules can be bypassed.

   This file is loaded in <head> without defer so the theme is applied before
   the first paint (no flash of the wrong colour).
   ========================================================================== */

(function () {
  'use strict';

  var STORAGE_KEY = 'securevote.theme';

  /* ======================================================================
     Theme
     ==================================================================== */

  function storedTheme() {
    try {
      return window.localStorage.getItem(STORAGE_KEY);
    } catch (error) {
      // Private mode or storage disabled: fall back to the OS preference.
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
        'title',
        theme === 'dark' ? 'Switch to light appearance' : 'Switch to dark appearance'
      );
    }
  }

  // Runs immediately, before the body is parsed.
  var initialTheme = storedTheme() || systemTheme();
  applyTheme(initialTheme);

  document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('[data-theme-toggle]');

    if (toggle) {
      toggle.addEventListener('click', function () {
        var next =
          document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        try {
          window.localStorage.setItem(STORAGE_KEY, next);
        } catch (error) {
          /* preference simply will not persist */
        }
      });
    }

    // Follow the operating system while the user has not chosen explicitly.
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
    initConfirms();
    initBallot();
    initCopyButtons();
    initTableFilters();
    initModals();
    initAutoSubmit();
    initSubmitGuard();
  });

  /* ======================================================================
     Sidebar drawer (small screens)
     ==================================================================== */

  function initSidebar() {
    var sidebar = document.getElementById('sidebar');
    if (!sidebar) {
      return;
    }

    var backdrop = document.querySelector('.sidebar-backdrop');
    var toggles = document.querySelectorAll('[data-sidebar-toggle]');
    var closers = document.querySelectorAll('[data-sidebar-close]');
    var menuButton = document.querySelector('[data-sidebar-toggle]');

    function open() {
      sidebar.classList.add('is-open');
      if (backdrop) {
        backdrop.hidden = false;
      }
      if (menuButton) {
        menuButton.setAttribute('aria-expanded', 'true');
      }
      var firstLink = sidebar.querySelector('a, button');
      if (firstLink) {
        firstLink.focus();
      }
    }

    function close() {
      sidebar.classList.remove('is-open');
      if (backdrop) {
        backdrop.hidden = true;
      }
      if (menuButton) {
        menuButton.setAttribute('aria-expanded', 'false');
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

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
        close();
      }
    });
  }

  /* ======================================================================
     Confirmation for destructive actions
     ==================================================================== */

  function initConfirms() {
    Array.prototype.forEach.call(
      document.querySelectorAll('[data-confirm]'),
      function (node) {
        var message = node.getAttribute('data-confirm') || 'Are you sure?';

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
          // A submit button inside a form.
          node.form.addEventListener('submit', function (event) {
            if (event.submitter !== node) {
              return;
            }
            if (!window.confirm(message)) {
              event.preventDefault();
            }
          });
        }
      }
    );
  }

  /* ======================================================================
     Ballot: selection limits and the running counter
     ==================================================================== */

  function initBallot() {
    var positions = document.querySelectorAll('.ballot__position[data-max]');

    Array.prototype.forEach.call(positions, function (position) {
      var max = parseInt(position.getAttribute('data-max'), 10) || 1;
      var inputs = position.querySelectorAll('.choice__input');
      var counter = position.querySelector('.ballot__counter');
      var warning = position.querySelector('[data-ballot-warning]');

      function selected() {
        return Array.prototype.filter.call(inputs, function (input) {
          return input.checked;
        });
      }

      function refresh() {
        var count = selected().length;

        if (counter) {
          counter.textContent = count + ' of ' + max + ' selected';
          counter.classList.toggle('is-full', count >= max);
        }
        if (warning) {
          warning.hidden = true;
        }
      }

      Array.prototype.forEach.call(inputs, function (input) {
        input.addEventListener('click', function (event) {
          var count = selected().length;

          // Let an already-checked box be unchecked freely.
          if (input.checked && count > max) {
            event.preventDefault();
            input.checked = false;
            if (warning) {
              warning.hidden = false;
              warning.textContent =
                'You may select at most ' + max + ' candidate' + (max > 1 ? 's' : '') +
                ' for this position. Uncheck one first.';
            }
          }
          refresh();
        });

        input.addEventListener('change', refresh);
      });

      refresh();
    });
  }

  /* ======================================================================
     Copy to clipboard (ballot reference on the confirmation page)
     ==================================================================== */

  function initCopyButtons() {
    Array.prototype.forEach.call(
      document.querySelectorAll('[data-copy]'),
      function (button) {
        button.addEventListener('click', function () {
          var selector = button.getAttribute('data-copy');
          var source = document.querySelector(selector);
          if (!source) {
            return;
          }

          var text = source.textContent.trim();
          var label = button.querySelector('[data-copy-label]');
          var original = label ? label.textContent : button.textContent;

          function done() {
            button.classList.add('is-copied');
            if (label) {
              label.textContent = 'Copied';
            }
            window.setTimeout(function () {
              button.classList.remove('is-copied');
              if (label) {
                label.textContent = original;
              }
            }, 1600);
          }

          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, function () {
              window.prompt('Copy this reference:', text);
            });
          } else {
            window.prompt('Copy this reference:', text);
          }
        });
      }
    );
  }

  /* ======================================================================
     Table filter: instant client-side search over a rendered table
     ==================================================================== */

  function initTableFilters() {
    Array.prototype.forEach.call(
      document.querySelectorAll('[data-table-filter]'),
      function (input) {
        var table = document.querySelector(input.getAttribute('data-table-filter'));
        if (!table) {
          return;
        }

        var tbody = table.tBodies[0];
        if (!tbody) {
          return;
        }

        var rows = Array.prototype.slice.call(tbody.rows);
        var emptyRow = tbody.querySelector('[data-filter-empty]');

        function apply() {
          var needle = input.value.trim().toLowerCase();
          var visible = 0;

          rows.forEach(function (row) {
            if (row === emptyRow) {
              return;
            }
            var hit = needle === '' || row.textContent.toLowerCase().indexOf(needle) !== -1;
            row.hidden = !hit;
            if (hit) {
              visible += 1;
            }
          });

          if (emptyRow) {
            emptyRow.hidden = visible > 0 || needle === '';
          }
        }

        input.addEventListener('input', apply);
        apply();
      }
    );
  }

  /* ======================================================================
     Native <dialog> modals
     ==================================================================== */

  function initModals() {
    Array.prototype.forEach.call(
      document.querySelectorAll('[data-modal-open]'),
      function (trigger) {
        trigger.addEventListener('click', function () {
          var dialog = document.querySelector(trigger.getAttribute('data-modal-open'));
          if (dialog && typeof dialog.showModal === 'function') {
            dialog.showModal();
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
          }
        });
      }
    );

    // Clicking the backdrop closes the dialog.
    Array.prototype.forEach.call(document.querySelectorAll('dialog.modal'), function (dialog) {
      dialog.addEventListener('click', function (event) {
        if (event.target === dialog) {
          dialog.close();
        }
      });
    });
  }

  /* ======================================================================
     Filter selects that submit their form on change
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
     Duplicate-submit guard
     Prevents an impatient double click from posting a ballot twice. The
     database unique constraints are the real protection.
     ==================================================================== */

  function initSubmitGuard() {
    Array.prototype.forEach.call(document.querySelectorAll('form'), function (form) {
      form.addEventListener('submit', function () {
        var button = form.querySelector('[data-submit-once]');
        if (!button) {
          return;
        }

        window.setTimeout(function () {
          button.disabled = true;
          button.textContent = button.getAttribute('data-submitting-label') || 'Working…';
        }, 0);
      });
    });
  }
})();
