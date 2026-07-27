// StickySortable - drag-and-drop reordering, built to stand alone (no
// dependency on sticky.js's own scroll/hash machinery) so a consumer who
// only wants sorting doesn't pay for sticky.js's DOMContentLoaded-triggered
// global scroll/wheel/hashchange handlers. Pointer Events (not HTML5 drag-
// and-drop) so a custom placeholder and touch both work with one code path -
// requires touch-action: none on the handle in CSS.
(function (root, factory) {

    if (typeof define === 'function' && define.amd) {
        define(['jquery'], factory);
    } else if (typeof exports === 'object') {
        module.exports = factory(require('jquery'));
    } else {
        root.StickySortable = factory(root.jQuery);
    }

})(this, function ($) {

    var defaults = {
        handle: null,          // selector, relative to the item, or null to drag the whole item
        items: '[data-sortable-item]',
        axis: 'y',
        dragThreshold: 4,      // px of pointer movement before a press counts as a drag, not a click
        autoscroll: true,
        autoscrollEdge: 40,    // px from the scroll container's edge that triggers scrolling
        autoscrollSpeed: 12,   // px per animation frame at full deflection
        draggingClass: 'is-dragging',
        placeholderClass: 'is-sortable-placeholder',
        onChange: null,        // function(order, container) - fires once, on drop, if the order changed
    };

    function closestScrollable(el) {
        var node = el.parentElement;
        while (node && node !== document.body) {
            var style = getComputedStyle(node);
            var overflowY = style.overflowY;
            if ((overflowY === 'auto' || overflowY === 'scroll') && node.scrollHeight > node.clientHeight) {
                return node;
            }
            node = node.parentElement;
        }
        return document.scrollingElement || document.documentElement;
    }

    function StickySortable(container, options) {
        this.container = container;
        this.options = $.extend({}, defaults, options);
        this.scroller = closestScrollable(container);
        this.dragging = null;
        this.placeholder = null;
        this.startY = 0;
        this.startIndex = -1;
        this.pointerId = null;
        this.autoscrollFrame = null;
        this.autoscrollDirection = 0;

        this.onPointerDown = this.onPointerDown.bind(this);
        this.onPointerMove = this.onPointerMove.bind(this);
        this.onPointerUp = this.onPointerUp.bind(this);

        container.addEventListener('pointerdown', this.onPointerDown);
        container.addEventListener('keydown', this.onKeyDown.bind(this));
    }

    StickySortable.prototype.items = function () {
        return Array.prototype.slice.call(this.container.querySelectorAll(this.options.items))
            .filter(function (el) { return el.parentElement === this.container; }.bind(this));
    };

    StickySortable.prototype.onPointerDown = function (e) {
        if (this.dragging || e.button !== undefined && e.button !== 0) {
            return;
        }

        var handle = this.options.handle ? e.target.closest(this.options.handle) : e.target;
        if (!handle) {
            return;
        }

        var item = e.target.closest(this.options.items);
        if (!item || item.parentElement !== this.container) {
            return;
        }

        this.pendingItem = item;
        this.pendingPointerId = e.pointerId;
        this.pendingStartX = e.clientX;
        this.pendingStartY = e.clientY;

        this.container.addEventListener('pointermove', this.onPendingMove = function (moveEvent) {
            if (moveEvent.pointerId !== this.pendingPointerId) {
                return;
            }
            var dx = moveEvent.clientX - this.pendingStartX;
            var dy = moveEvent.clientY - this.pendingStartY;
            if (Math.sqrt(dx * dx + dy * dy) >= this.options.dragThreshold) {
                this.beginDrag(this.pendingItem, this.pendingPointerId, moveEvent);
            }
        }.bind(this));

        this.container.addEventListener('pointerup', this.onPendingUp = function () {
            this.container.removeEventListener('pointermove', this.onPendingMove);
            this.container.removeEventListener('pointerup', this.onPendingUp);
        }.bind(this), { once: true });
    };

    StickySortable.prototype.beginDrag = function (item, pointerId, e) {
        this.container.removeEventListener('pointermove', this.onPendingMove);

        this.dragging = item;
        this.pointerId = pointerId;
        this.startIndex = this.items().indexOf(item);

        item.setPointerCapture(pointerId);
        item.classList.add(this.options.draggingClass);

        this.placeholder = document.createElement(item.tagName);
        this.placeholder.className = this.options.placeholderClass;
        this.placeholder.style.height = item.offsetHeight + 'px';
        item.parentElement.insertBefore(this.placeholder, item.nextSibling);

        var rect = item.getBoundingClientRect();
        this.offsetY = e.clientY - rect.top;
        item.style.position = 'fixed';
        item.style.width = rect.width + 'px';
        item.style.zIndex = 1000;
        item.style.pointerEvents = 'none';
        this.moveItemTo(e.clientY);

        document.addEventListener('pointermove', this.onPointerMove);
        document.addEventListener('pointerup', this.onPointerUp);
    };

    StickySortable.prototype.moveItemTo = function (clientY) {
        this.dragging.style.top = (clientY - this.offsetY) + 'px';
        this.dragging.style.left = this.dragging.getBoundingClientRect().left + 'px';
    };

    StickySortable.prototype.onPointerMove = function (e) {
        if (!this.dragging || e.pointerId !== this.pointerId) {
            return;
        }

        this.moveItemTo(e.clientY);

        var midY = e.clientY;
        var siblings = Array.prototype.slice.call(this.container.children).filter(function (el) {
            return el !== this.dragging && el !== this.placeholder && el.matches(this.options.items);
        }.bind(this));

        for (var i = 0; i < siblings.length; i++) {
            var rect = siblings[i].getBoundingClientRect();
            var siblingMid = rect.top + rect.height / 2;
            if (midY < siblingMid) {
                this.container.insertBefore(this.placeholder, siblings[i]);
                break;
            }
            if (i === siblings.length - 1) {
                this.container.insertBefore(this.placeholder, siblings[i].nextSibling);
            }
        }

        if (this.options.autoscroll) {
            this.updateAutoscroll(e.clientY);
        }
    };

    StickySortable.prototype.updateAutoscroll = function (clientY) {
        var rect = this.scroller === document.scrollingElement || this.scroller === document.documentElement
            ? { top: 0, bottom: window.innerHeight }
            : this.scroller.getBoundingClientRect();

        var edge = this.options.autoscrollEdge;
        if (clientY < rect.top + edge) {
            this.autoscrollDirection = -1;
        } else if (clientY > rect.bottom - edge) {
            this.autoscrollDirection = 1;
        } else {
            this.autoscrollDirection = 0;
        }

        if (this.autoscrollDirection !== 0 && !this.autoscrollFrame) {
            this.runAutoscroll();
        }
    };

    StickySortable.prototype.runAutoscroll = function () {
        if (!this.dragging || this.autoscrollDirection === 0) {
            this.autoscrollFrame = null;
            return;
        }
        this.scroller.scrollTop += this.autoscrollDirection * this.options.autoscrollSpeed;
        this.autoscrollFrame = requestAnimationFrame(this.runAutoscroll.bind(this));
    };

    StickySortable.prototype.onPointerUp = function (e) {
        if (!this.dragging || e.pointerId !== this.pointerId) {
            return;
        }

        var item = this.dragging;
        item.releasePointerCapture(this.pointerId);
        item.classList.remove(this.options.draggingClass);
        item.style.position = '';
        item.style.top = '';
        item.style.left = '';
        item.style.width = '';
        item.style.zIndex = '';
        item.style.pointerEvents = '';

        this.container.insertBefore(item, this.placeholder);
        this.placeholder.remove();
        this.placeholder = null;

        this.autoscrollDirection = 0;
        if (this.autoscrollFrame) {
            cancelAnimationFrame(this.autoscrollFrame);
            this.autoscrollFrame = null;
        }

        document.removeEventListener('pointermove', this.onPointerMove);
        document.removeEventListener('pointerup', this.onPointerUp);

        var newIndex = this.items().indexOf(item);
        this.dragging = null;
        this.pointerId = null;

        if (newIndex !== this.startIndex && typeof this.options.onChange === 'function') {
            this.options.onChange(this.items(), this.container);
        }
    };

    /** Keyboard fallback: focus a handle, ArrowUp/ArrowDown moves its item, no pointer needed. */
    StickySortable.prototype.onKeyDown = function (e) {
        if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') {
            return;
        }

        var handle = this.options.handle ? e.target.closest(this.options.handle) : null;
        if (!handle) {
            return;
        }

        var item = e.target.closest(this.options.items);
        if (!item || item.parentElement !== this.container) {
            return;
        }

        var items = this.items();
        var index = items.indexOf(item);
        var swapWith = e.key === 'ArrowUp' ? items[index - 1] : items[index + 1];
        if (!swapWith) {
            return;
        }

        e.preventDefault();
        if (e.key === 'ArrowUp') {
            this.container.insertBefore(item, swapWith);
        } else {
            this.container.insertBefore(item, swapWith.nextSibling);
        }
        handle.focus();

        if (typeof this.options.onChange === 'function') {
            this.options.onChange(this.items(), this.container);
        }
    };

    $.fn.stickySortable = function (options) {
        return this.each(function () {
            if (!this._stickySortable) {
                this._stickySortable = new StickySortable(this, options);
            }
        });
    };

    return StickySortable;
});
