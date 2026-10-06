/*
 * Language Labels: move each item's label list over its thumbnail.
 *
 * The list is positioned over the image's measured box rather than wrapping
 * the image, so the theme's layout is untouched. Lists that can't be placed
 * stay inline. Call window.LanguageLabels.refresh() after loading items
 * dynamically.
 */
(function () {
    'use strict';

    var config = window.LanguageLabels || {};
    var containerSelector = (config.containers || ['.item', '.record', '.hentry', 'tr', 'article', 'li']).join(',');
    var imageSelectors = config.images || ['.item-img img', '.item-thumbnail img', 'a img', 'img'];

    // Images narrower than this (px) get compact labels.
    var COMPACT_BELOW = 90;

    var placed = [];
    var observer = window.ResizeObserver ? new ResizeObserver(function () { schedule(); }) : null;
    var pending = false;

    function matches(el, selector) {
        var fn = el.matches || el.msMatchesSelector || el.webkitMatchesSelector;
        return fn ? fn.call(el, selector) : false;
    }

    function closest(el, selector) {
        while (el && el.nodeType === 1) {
            if (matches(el, selector)) {
                return el;
            }
            el = el.parentNode;
        }
        return null;
    }

    function findImage(container) {
        for (var i = 0; i < imageSelectors.length; i++) {
            var candidates = container.querySelectorAll(imageSelectors[i]);
            for (var j = 0; j < candidates.length; j++) {
                var img = candidates[j];
                if (img.tagName === 'IMG' && !closest(img, '.language-labels')) {
                    return img;
                }
            }
        }
        return null;
    }

    // Nearest ancestor that generates a box, to position against.
    function findHost(img) {
        var el = img.parentNode;
        while (el && el !== document.body) {
            var display = window.getComputedStyle(el).display;
            if (display !== 'inline' && display !== 'contents') {
                return el;
            }
            el = el.parentNode;
        }
        return el;
    }

    function attach(list) {
        // Only use a wrapper that holds exactly this one item.
        var container = closest(list.parentNode, containerSelector);
        if (!container || container.querySelectorAll('.language-labels').length !== 1) {
            return;
        }
        var img = findImage(container);
        if (!img) {
            return;
        }

        var host = findHost(img);
        if (window.getComputedStyle(host).position === 'static') {
            host.classList.add('language-labels-host');
        }

        list.classList.remove('language-labels--inline');
        list.classList.add('language-labels--overlay');
        host.appendChild(list);

        var entry = { list: list, img: img, host: host };
        placed.push(entry);

        if (!img.complete) {
            img.addEventListener('load', schedule);
        }
        if (observer) {
            observer.observe(img);
        }
        position(entry);
    }

    function position(entry) {
        var img = entry.img;
        var list = entry.list;
        var host = entry.host;

        if (!img.offsetWidth || !img.offsetHeight) {
            list.classList.remove('language-labels--placed');
            return;
        }

        var imgBox = img.getBoundingClientRect();
        var hostBox = host.getBoundingClientRect();

        list.style.left = (imgBox.left - hostBox.left - host.clientLeft + host.scrollLeft) + 'px';
        list.style.top = (imgBox.top - hostBox.top - host.clientTop + host.scrollTop) + 'px';
        list.style.width = imgBox.width + 'px';
        list.style.height = imgBox.height + 'px';

        list.classList.toggle('language-labels--compact', imgBox.width < COMPACT_BELOW);
        list.classList.add('language-labels--placed');
    }

    function positionAll() {
        pending = false;
        for (var i = 0; i < placed.length; i++) {
            position(placed[i]);
        }
    }

    function schedule() {
        if (!pending) {
            pending = true;
            (window.requestAnimationFrame || setTimeout)(positionAll);
        }
    }

    function refresh(root) {
        var lists = (root || document).querySelectorAll('.language-labels--inline:not([hidden])');
        for (var i = 0; i < lists.length; i++) {
            attach(lists[i]);
        }
        schedule();
    }

    config.refresh = refresh;
    window.LanguageLabels = config;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { refresh(); });
    } else {
        refresh();
    }
    // Fonts and late images can move images without resizing them.
    window.addEventListener('load', schedule);
    window.addEventListener('resize', schedule);
}());
