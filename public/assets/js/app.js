/* NDC-SMS — progressive enhancement only. Every action works without JS. */
(function () {
  'use strict';

  // Mobile sidebar toggle
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-nav-toggle]');
    if (toggle) {
      var open = document.body.classList.toggle('nav-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      return;
    }
    if (document.body.classList.contains('nav-open') && !e.target.closest('.sidebar')) {
      document.body.classList.remove('nav-open');
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.body.classList.remove('nav-open');
      document.querySelectorAll('details.user-menu[open]').forEach(function (d) { d.removeAttribute('open'); });
    }
  });

  // Confirmation dialog for destructive / irreversible actions:
  // <form data-confirm="Message"> ... </form>
  var dialog = document.getElementById('confirm-dialog');
  var pendingForm = null;
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches('form[data-confirm]') || form.dataset.confirmed === '1') { return; }
    if (!dialog || typeof dialog.showModal !== 'function') {
      if (!window.confirm(form.dataset.confirm)) { e.preventDefault(); }
      return;
    }
    e.preventDefault();
    pendingForm = form;
    dialog.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
    dialog.showModal();
    dialog.querySelector('[data-confirm-cancel]').focus();
  });
  if (dialog) {
    dialog.querySelector('[data-confirm-ok]').addEventListener('click', function () {
      dialog.close();
      if (pendingForm) {
        pendingForm.dataset.confirmed = '1';
        if (pendingForm.requestSubmit) { pendingForm.requestSubmit(); } else { pendingForm.submit(); }
      }
    });
    dialog.querySelector('[data-confirm-cancel]').addEventListener('click', function () {
      pendingForm = null;
      dialog.close();
    });
  }

  // Prevent double submission (duplicate records) on slow connections
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (e.defaultPrevented || form.method.toLowerCase() === 'get') { return; }
    window.setTimeout(function () {
      form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (b) {
        b.disabled = true;
        b.setAttribute('aria-busy', 'true');
      });
    }, 0);
  });

  // Repeatable rows (e.g. purchase request line items):
  // container [data-repeater] with <template data-repeater-template>, rows [data-repeater-row],
  // add button [data-repeater-add], remove button [data-repeater-remove]. "__INDEX__" is replaced.
  document.querySelectorAll('[data-repeater]').forEach(function (container) {
    var template = container.querySelector('template[data-repeater-template]');
    var list = container.querySelector('[data-repeater-list]');
    var next = list.querySelectorAll('[data-repeater-row]').length;
    container.addEventListener('click', function (e) {
      if (e.target.closest('[data-repeater-add]')) {
        e.preventDefault();
        var html = template.innerHTML.replace(/__INDEX__/g, String(next++));
        list.insertAdjacentHTML('beforeend', html);
        var rows = list.querySelectorAll('[data-repeater-row]');
        var first = rows[rows.length - 1].querySelector('input, select, textarea');
        if (first) { first.focus(); }
        recalc(container);
      }
      var remove = e.target.closest('[data-repeater-remove]');
      if (remove) {
        e.preventDefault();
        if (list.querySelectorAll('[data-repeater-row]').length > 1) {
          remove.closest('[data-repeater-row]').remove();
          recalc(container);
        }
      }
    });
    container.addEventListener('input', function () { recalc(container); });
    recalc(container);
  });

  // Display-only running total for rows with [data-qty] and [data-price]. The server recalculates.
  function toHundredths(value) {
    var m = String(value || '').replace(/[,\s]/g, '').match(/^(\d+)(?:\.(\d{0,2}))?/);
    if (!m) { return 0; }
    return parseInt(m[1], 10) * 100 + parseInt(((m[2] || '') + '00').slice(0, 2), 10);
  }
  function recalc(container) {
    var out = container.querySelector('[data-repeater-total]');
    if (!out) { return; }
    var total = 0; // in cents
    container.querySelectorAll('[data-repeater-row]').forEach(function (row) {
      var q = row.querySelector('[data-qty]');
      var p = row.querySelector('[data-price]');
      if (q && p) { total += Math.round(toHundredths(q.value) * toHundredths(p.value) / 100); }
    });
    var whole = Math.floor(total / 100).toLocaleString('en-US');
    out.textContent = whole + '.' + String(total % 100).padStart(2, '0');
  }
})();

// Re-enable buttons when a page is restored from the back/forward cache.
window.addEventListener('pageshow', function (e) {
  if (e.persisted) {
    document.querySelectorAll('button[aria-busy="true"]').forEach(function (b) { b.disabled = false; b.removeAttribute('aria-busy'); });
  }
});
