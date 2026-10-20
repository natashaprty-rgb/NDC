/*
 * NDC Somalia Management System - interface behaviour.
 * Plain JavaScript, no build step. Loaded on every page; the Content Security
 * Policy forbids inline scripts, so behaviour is attached via data-attributes:
 *
 *   data-nav-toggle / data-nav-close   open/close the sidebar on small screens
 *   data-dropdown / data-dropdown-toggle
 *   form[data-confirm="Message"]       ask before submitting
 *   [data-repeater]                    add/remove rows from a <template data-repeater-template>
 *                                      (use __INDEX__ in names; buttons: data-repeater-add / data-repeater-remove)
 *   select[data-autosubmit]            submit the parent form on change
 *   [data-print]                       print the page
 */
(function () {
    'use strict';

    var app = document.querySelector('[data-app]');

    document.addEventListener('click', function (event) {
        var target = event.target;

        if (target.closest('[data-nav-toggle]') && app) {
            app.classList.toggle('nav-open');
            return;
        }
        if (target.closest('[data-nav-close]') && app) {
            app.classList.remove('nav-open');
            return;
        }

        var toggle = target.closest('[data-dropdown-toggle]');
        document.querySelectorAll('[data-dropdown].is-open').forEach(function (dd) {
            if (!toggle || dd !== toggle.closest('[data-dropdown]')) {
                dd.classList.remove('is-open');
                var t = dd.querySelector('[data-dropdown-toggle]');
                if (t) { t.setAttribute('aria-expanded', 'false'); }
            }
        });
        if (toggle) {
            var dropdown = toggle.closest('[data-dropdown]');
            var open = dropdown.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            return;
        }

        var add = target.closest('[data-repeater-add]');
        if (add) {
            event.preventDefault();
            var repeater = add.closest('[data-repeater]');
            var template = repeater.querySelector('template[data-repeater-template]');
            var rows = repeater.querySelector('[data-repeater-rows]') || repeater;
            var index = parseInt(repeater.getAttribute('data-repeater-next') || rows.children.length, 10);
            var html = template.innerHTML.replace(/__INDEX__/g, String(index));
            repeater.setAttribute('data-repeater-next', String(index + 1));
            rows.insertAdjacentHTML('beforeend', html);
            var first = rows.lastElementChild && rows.lastElementChild.querySelector('input, select, textarea');
            if (first) { first.focus(); }
            return;
        }

        var remove = target.closest('[data-repeater-remove]');
        if (remove) {
            event.preventDefault();
            var row = remove.closest('[data-repeater-row]');
            if (row) { row.remove(); }
            return;
        }

        if (target.closest('[data-print]')) {
            event.preventDefault();
            window.print();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            if (app) { app.classList.remove('nav-open'); }
            document.querySelectorAll('[data-dropdown].is-open').forEach(function (dd) { dd.classList.remove('is-open'); });
        }
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        var message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
            return;
        }
        // Avoid double submission.
        if (form.method.toLowerCase() === 'post') {
            form.querySelectorAll('button[type="submit"], button:not([type])').forEach(function (btn) {
                setTimeout(function () { btn.disabled = true; }, 0);
            });
        }
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('select[data-autosubmit]') && event.target.form) {
            event.target.form.submit();
        }
    });
})();
