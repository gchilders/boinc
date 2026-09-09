/* ============================================================================
   nfs2-theme.js - Auto / Light / Dark control for NFS@Home
   ============================================================================
   Install as html/user/nfs2-theme.js.

   nfs2.css already follows the operating system with no JavaScript at all.
   This file adds an explicit choice and remembers it across pages.

   Two things happen here:

     1. The stored choice is applied to <html> as soon as this file parses.
        Load it as early as possible. If it loads inside <body> there will be
        a brief flash of the system theme on first paint; see the note in
        project-inc-changes.md for how to avoid that.

     2. On pages that do not already contain a .theme-switch (that is, every
        page except index2.php), a control is injected into the right-hand
        navbar list that sample_navbar() emits.

     3. Any table wrapper that is actually scrolling sideways is made
        keyboard-reachable. nfs2.css turns start_table()'s <div class="table">
        into a horizontal scroll container, and a scroll container that cannot
        be focused is unreachable without a mouse.

   No jQuery. Safe to load before or after bootstrap.min.js.
   ============================================================================ */

(function () {
    'use strict';

    var KEY = 'nfs-theme';
    var MODES = ['auto', 'light', 'dark'];

    function stored() {
        try {
            var v = localStorage.getItem(KEY);
            return MODES.indexOf(v) === -1 ? 'auto' : v;
        } catch (e) {
            // Private browsing, or cookies/storage blocked.
            return 'auto';
        }
    }

    function apply(mode) {
        document.documentElement.setAttribute('data-theme', mode);
        try { localStorage.setItem(KEY, mode); } catch (e) {}
    }

    // Step 1: apply immediately, before waiting for the DOM.
    var current = stored();
    document.documentElement.setAttribute('data-theme', current);

    // Step 2: wire up or inject the control.
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function wire(root) {
        var buttons = root.querySelectorAll('[data-theme-set]');
        Array.prototype.forEach.call(buttons, function (btn) {
            var mode = btn.getAttribute('data-theme-set');
            btn.setAttribute('aria-pressed', String(mode === stored()));
            btn.addEventListener('click', function () {
                apply(mode);
                Array.prototype.forEach.call(buttons, function (b) {
                    b.setAttribute('aria-pressed', String(b === btn));
                });
            });
        });
    }

    // Returns the button group itself. Callers wrap it if they need to.
    function buildGroup() {
        var group = document.createElement('div');
        group.className = 'theme-switch';
        group.setAttribute('role', 'group');
        group.setAttribute('aria-label', 'Colour theme');

        var labels = { auto: 'Auto', light: 'Light', dark: 'Dark' };
        MODES.forEach(function (mode) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'theme-btn';
            b.setAttribute('data-theme-set', mode);
            b.setAttribute('aria-pressed', String(mode === current));
            b.appendChild(document.createTextNode(labels[mode]));
            group.appendChild(b);
        });
        return group;
    }

    ready(function () {
        // index2.php ships its own control in the masthead.
        if (document.querySelector('.theme-switch')) {
            wire(document);
            return;
        }

        // Preferred home: the right-hand list sample_navbar() emits.
        var nav = document.querySelector('.navbar-nav.navbar-right')
               || document.querySelector('.navbar-nav');
        if (nav) {
            var li = document.createElement('li');
            li.className = 'theme-switch-nav';
            li.appendChild(buildGroup());
            nav.appendChild(li);
            wire(nav);
            return;
        }

        // No navbar at all. This happens when project_banner() in
        // project.inc does not call sample_navbar(), and on pages that build
        // their own HTML. Rather than leave those with no way to switch, put
        // a small control in the top corner. Nothing here depends on the
        // banner markup, so dark mode stays switchable everywhere.
        var group = buildGroup();
        group.classList.add('theme-switch-floating');
        document.body.appendChild(group);
        wire(group);
    });

    // ---- keyboard access for horizontally scrolling tables ---------------
    //
    // nfs2.css sets `div.table { overflow-x: auto }`, so a wide table scrolls
    // inside its wrapper instead of stretching the page. A div that scrolls
    // but cannot take focus is a keyboard trap in reverse: the content is
    // simply unreachable. WAI's pattern for this is tabindex="0" plus
    // role="region" and an accessible name.
    //
    // Applied only to wrappers that are genuinely overflowing. A focusable
    // element with nothing to scroll is just an extra tab stop, and whether a
    // table overflows depends on the viewport, so this is re-evaluated on
    // resize.

    function tableLabel(wrap) {
        var caption = wrap.querySelector('caption');
        if (caption && caption.textContent.trim()) {
            return caption.textContent.trim();
        }
        // Nearest preceding heading, which on the crunching pages is the
        // "Now sieving" / "Completed" style subheading.
        var el = wrap.previousElementSibling;
        while (el) {
            if (/^H[1-6]$/.test(el.tagName) && el.textContent.trim()) {
                return el.textContent.trim();
            }
            el = el.previousElementSibling;
        }
        return 'Table';
    }

    function updateScrollableTables() {
        var wraps = document.querySelectorAll('div.table');
        Array.prototype.forEach.call(wraps, function (wrap) {
            // 2px of slack: sub-pixel layout should not count as overflow.
            var scrolls = wrap.scrollWidth > wrap.clientWidth + 2;

            if (scrolls) {
                if (wrap.getAttribute('data-nfs-scrollable') === '1') {
                    return;
                }
                wrap.setAttribute('data-nfs-scrollable', '1');
                wrap.setAttribute('tabindex', '0');
                wrap.setAttribute('role', 'region');
                wrap.setAttribute('aria-label',
                    tableLabel(wrap) + ' (scrollable)');
            } else if (wrap.getAttribute('data-nfs-scrollable') === '1') {
                wrap.removeAttribute('data-nfs-scrollable');
                wrap.removeAttribute('tabindex');
                wrap.removeAttribute('role');
                wrap.removeAttribute('aria-label');
            }
        });
    }

    ready(function () {
        updateScrollableTables();

        // Widths shift once webfonts finish, so check again after load.
        window.addEventListener('load', updateScrollableTables);

        var t = null;
        window.addEventListener('resize', function () {
            if (t) clearTimeout(t);
            t = setTimeout(updateScrollableTables, 150);
        });
    });
})();
