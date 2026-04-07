/* Othello search box UX:
 *   - "/" focuses the search box from anywhere on the page
 *   - clear button (×) empties the box and submits the form
 */
(function () {
    'use strict';
    function init() {
        const input = document.getElementById('searchq');
        if (!input) return;
        const form = input.closest('form');
        const clear = form ? form.querySelector('.clearq') : null;

        document.addEventListener('keydown', function (e) {
            if (e.key !== '/') return;
            const active = document.activeElement;
            if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA')) return;
            e.preventDefault();
            input.focus();
            input.select();
        });

        if (clear) {
            clear.addEventListener('click', function () {
                input.value = '';
                if (form) form.submit();
            });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
