/**
 * Shared UI utilities — Papir ERP
 *
 * makeDraggable(el, opts)
 *   Makes a modal draggable by its header.
 *   el — .modal-overlay, .modal-box, or any container with a box+handle inside.
 *   opts.box      — selector for the draggable box  (default: '.modal-box')
 *   opts.handle   — selector for the drag handle    (default: '.modal-head')
 *   Resets position when the parent overlay hides.
 *
 * Usage:
 *   makeDraggable(document.getElementById('myModal'));
 *   makeDraggable(el, { box: '.ttn-modal-box', handle: '.ttn-mh' });
 */
(function() {
    'use strict';

    window.makeDraggable = function(el, opts) {
        if (!el) return;
        opts = opts || {};

        var boxSel    = opts.box    || '.modal-box';
        var handleSel = opts.handle || '.modal-head';

        var box = el.matches && el.matches(boxSel) ? el : el.querySelector(boxSel);
        if (!box) box = el; // el itself is the box

        var head = box.querySelector(handleSel);
        if (!head) return;

        head.style.cursor = 'move';
        head.style.userSelect = 'none';

        var startX, startY, origLeft, origTop, dragging = false;

        head.addEventListener('mousedown', function(e) {
            if (e.target.closest('button, input, select, a')) return;

            dragging = true;
            var rect = box.getBoundingClientRect();
            startX = e.clientX;
            startY = e.clientY;

            if (!box.style.left || box.style.left === '') {
                box.style.left = rect.left + 'px';
                box.style.top  = rect.top  + 'px';
            }

            origLeft = parseInt(box.style.left, 10);
            origTop  = parseInt(box.style.top,  10);

            box.style.position = 'fixed';
            box.style.margin   = '0';

            e.preventDefault();
        });

        document.addEventListener('mousemove', function(e) {
            if (!dragging) return;
            var dx = e.clientX - startX;
            var dy = e.clientY - startY;
            box.style.left = (origLeft + dx) + 'px';
            box.style.top  = (origTop  + dy) + 'px';
        });

        document.addEventListener('mouseup', function() {
            dragging = false;
        });

        // Reset position when overlay hides — re-centers next open
        var overlay = box.parentElement;
        if (overlay) {
            var observer = new MutationObserver(function() {
                var isOpen = overlay.classList.contains('open') ||
                             (!overlay.classList.contains('hidden') &&
                              (overlay.style.display === 'flex' || overlay.style.display === 'block'));
                if (!isOpen) {
                    box.style.left     = '';
                    box.style.top      = '';
                    box.style.position = '';
                    box.style.margin   = '';
                }
            });
            observer.observe(overlay, { attributes: true, attributeFilter: ['class', 'style'] });
        }
    };
}());
