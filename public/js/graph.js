// Graph - a layered directed graph you can drag and zoom.
//
// Nodes are HTML (cards rendered by the server, links that stay links), edges
// are SVG. Declared in the page:
//
//   <div data-graph data-graph-preset="tree" data-graph-focus="me">
//     <a href="/p/1" data-graph-node="gf">Grandfather</a>
//     <a href="/p/2" data-graph-node="father" data-graph-from="gf">Father</a>
//     <a href="/p/3" data-graph-node="me" data-graph-from="father">Me</a>
//   </div>
//
// or given as data: graph.setData({nodes: [{id, label, href, rank, state}],
// edges: [{from, to}]}).
//
// Drag to move, Ctrl/⌘ + wheel or pinch to zoom (the wheel alone scrolls the
// page), arrows, + - 0 on the keyboard. No dependency.

import { layout, path, lineage } from './layout.js';

const PRESETS = {
    tree: { direction: 'down', edges: 'step', gap: 24, rankGap: 48 },
    pipeline: { direction: 'right', edges: 'curve', gap: 14, rankGap: 64 },
};

const DEFAULTS = {
    preset: null,
    direction: 'down',     // down | right
    edges: 'curve',        // curve | step | line
    radius: 8,             // of a step edge's corners
    gap: 24,
    rankGap: 56,
    align: 'start',
    sort: true,
    focus: null,           // the node the graph opens centred on
    lineage: 'hover',      // hover | select | off
    controls: true,
    keyboard: true,
    remember: true,        // the view, in sessionStorage
    key: null,             // of the remembered view (default: the element's id, else the page's path)
    minZoom: 0.15,
    maxZoom: 2.5,
    zoomStep: 1.3,
    padding: 32,           // around the graph when fitted
    labels: {
        graph: 'Graph',
        zoomIn: 'Zoom in',
        zoomOut: 'Zoom out',
        fit: 'Fit',
        fullscreen: 'Full screen',
    },
    render: null,          // (node) => HTMLElement, for setData()
};

const ICONS = {
    zoomIn: '<path d="M8 3v10M3 8h10"/>',
    zoomOut: '<path d="M3 8h10"/>',
    fit: '<path d="M2.5 6V2.5H6M10 2.5h3.5V6M13.5 10v3.5H10M6 13.5H2.5V10"/>',
    fullscreen: '<path d="M9.5 2.5h4v4M13.5 2.5 9 7M6.5 13.5h-4v-4M2.5 13.5 7 9"/>',
};

const SVG = 'http://www.w3.org/2000/svg';
const instances = new WeakMap();

function dataOptions(root) {
    const d = root.dataset;
    const o = {};
    const bool = (v) => !(v === 'false' || v === '0' || v === 'off' || v === 'no');
    if (d.graphPreset) o.preset = d.graphPreset;
    if (d.graphDirection) o.direction = d.graphDirection;
    if (d.graphEdges) o.edges = d.graphEdges;
    if (d.graphFocus) o.focus = d.graphFocus;
    if (d.graphLineage) o.lineage = d.graphLineage;
    if (d.graphKey) o.key = d.graphKey;
    if (d.graphAlign) o.align = d.graphAlign;
    if (d.graphSort !== undefined) o.sort = bool(d.graphSort);
    if (d.graphControls !== undefined) o.controls = bool(d.graphControls);
    if (d.graphKeyboard !== undefined) o.keyboard = bool(d.graphKeyboard);
    if (d.graphRemember !== undefined) o.remember = bool(d.graphRemember);
    ['gap', 'rankGap', 'radius', 'minZoom', 'maxZoom', 'padding'].forEach((name) => {
        const value = d['graph' + name[0].toUpperCase() + name.slice(1)];
        if (value !== undefined && value !== '' && Number.isFinite(Number(value))) o[name] = Number(value);
    });
    const labels = {};
    Object.keys(DEFAULTS.labels).forEach((name) => {
        const value = d['graphLabel' + name[0].toUpperCase() + name.slice(1)];
        if (value) labels[name] = value;
    });
    if (Object.keys(labels).length) o.labels = labels;
    return o;
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

export class Graph {
    /**
     * @param {HTMLElement} root the [data-graph] element
     * @param {object} options see DEFAULTS; the element's data-graph-* attributes win over the preset, the options over both
     */
    constructor(root, options = {}) {
        if (instances.has(root)) return instances.get(root);
        instances.set(root, this);

        const declared = dataOptions(root);
        const preset = PRESETS[options.preset || declared.preset] || {};
        this.root = root;
        this.options = Object.assign({}, DEFAULTS, preset, declared, options);
        this.options.labels = Object.assign({}, DEFAULTS.labels, declared.labels, options.labels);
        this.view = { x: 0, y: 0, scale: 1 };
        this.data = { nodes: [], edges: [] };
        this.result = null;
        this.elements = new Map();    // node id → element
        this.paths = [];              // {element, from, to, index}
        this.pinned = null;           // the node whose line stays lit
        this._placed = false;         // a first view has been set
        this._listeners = [];
        this._frame = 0;

        this._build();
        this._bind();
        this.refresh();
    }

    // -- static ----------------------------------------------------------

    /** Starts every [data-graph] under `root` that is not started yet. */
    static ready(root = document, options = {}) {
        if (typeof document === 'undefined') return [];
        const scope = root && root.querySelectorAll ? root : document;
        const found = [];
        if (scope.matches && scope.matches('[data-graph]')) found.push(scope);
        scope.querySelectorAll('[data-graph]').forEach((el) => found.push(el));
        return found.map((el) => instances.get(el) || new Graph(el, options));
    }

    static get(element) {
        return instances.get(element) || null;
    }

    // -- structure -------------------------------------------------------

    _build() {
        const root = this.root;
        const o = this.options;
        root.classList.add('graph');
        if (o.preset && !root.dataset.graphPreset) root.dataset.graphPreset = o.preset;
        root.dataset.graphDirection = o.direction;

        this.viewport = document.createElement('div');
        this.viewport.className = 'graph-viewport';
        if (o.keyboard) this.viewport.tabIndex = 0;
        this.viewport.setAttribute('role', 'group');
        this.viewport.setAttribute('aria-label', root.getAttribute('aria-label') || o.labels.graph);

        this.canvas = document.createElement('div');
        this.canvas.className = 'graph-canvas';

        this.svg = document.createElementNS(SVG, 'svg');
        this.svg.setAttribute('class', 'graph-edges');
        this.svg.setAttribute('aria-hidden', 'true');
        this.svg.setAttribute('focusable', 'false');

        this.canvas.appendChild(this.svg);
        // the nodes the server rendered move into the canvas, in their order
        Array.from(root.querySelectorAll('[data-graph-node]')).forEach((el) => this.canvas.appendChild(el));
        this.viewport.appendChild(this.canvas);
        root.insertBefore(this.viewport, root.firstChild);

        if (o.controls) {
            this.controls = document.createElement('div');
            this.controls.className = 'graph-controls';
            const button = (name, action) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'graph-control graph-control-' + name;
                b.title = o.labels[name];
                b.setAttribute('aria-label', o.labels[name]);
                b.innerHTML = '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' + ICONS[name] + '</svg>';
                b.addEventListener('click', action);
                this.controls.appendChild(b);
                return b;
            };
            button('zoomIn', () => this.zoomIn());
            button('zoomOut', () => this.zoomOut());
            button('fit', () => this.fit());
            button('fullscreen', () => this.fullscreen());
            root.appendChild(this.controls);
        }
    }

    _on(target, type, handler, options) {
        target.addEventListener(type, handler, options);
        this._listeners.push([target, type, handler, options]);
    }

    _bind() {
        const viewport = this.viewport;
        const pointers = new Map();
        let drag = null;
        let pinch = null;
        let swallow = false;   // the click that ends a drag is not a click

        const local = (clientX, clientY) => {
            const box = viewport.getBoundingClientRect();
            return { x: clientX - box.left, y: clientY - box.top };
        };

        this._on(viewport, 'pointerdown', (e) => {
            if (e.pointerType === 'mouse' && e.button !== 0) return;
            if (e.target.closest && e.target.closest('input, textarea, select, [contenteditable=""], [contenteditable="true"], [data-graph-nodrag]')) return;
            swallow = false;
            pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
            if (pointers.size === 1) {
                drag = { id: e.pointerId, x: e.clientX, y: e.clientY, viewX: this.view.x, viewY: this.view.y, moved: false };
            } else if (pointers.size === 2) {
                const [a, b] = Array.from(pointers.values());
                pinch = { distance: Math.hypot(a.x - b.x, a.y - b.y) || 1, scale: this.view.scale };
                if (drag) drag.moved = true;
                swallow = true;
            }
        });

        this._on(viewport, 'pointermove', (e) => {
            if (!pointers.has(e.pointerId)) return;
            const previous = pointers.get(e.pointerId);
            pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
            if (pinch && pointers.size >= 2) {
                const [a, b] = Array.from(pointers.values());
                const distance = Math.hypot(a.x - b.x, a.y - b.y) || 1;
                const centre = local((a.x + b.x) / 2, (a.y + b.y) / 2);
                // the middle of the fingers also drags the graph
                this.view.x += (e.clientX - previous.x) / 2;
                this.view.y += (e.clientY - previous.y) / 2;
                this.zoomTo(pinch.scale * distance / pinch.distance, centre);
                return;
            }
            if (!drag || drag.id !== e.pointerId) return;
            const dx = e.clientX - drag.x;
            const dy = e.clientY - drag.y;
            if (!drag.moved) {
                if (Math.hypot(dx, dy) < 4) return;   // under 4 px, it is still a click
                drag.moved = true;
                swallow = true;
                this.root.classList.add('is-dragging');
                try { viewport.setPointerCapture(e.pointerId); } catch (error) { /* gone already */ }
            }
            this._setView(drag.viewX + dx, drag.viewY + dy, this.view.scale);
        });

        const release = (e) => {
            if (!pointers.has(e.pointerId)) return;
            pointers.delete(e.pointerId);
            if (pointers.size < 2) pinch = null;
            if (drag && drag.id === e.pointerId) {
                drag = null;
                this.root.classList.remove('is-dragging');
            }
            if (pointers.size === 1) {   // one finger left after a pinch: it drags on
                const [id, p] = Array.from(pointers.entries())[0];
                drag = { id, x: p.x, y: p.y, viewX: this.view.x, viewY: this.view.y, moved: true };
            }
            if (swallow) setTimeout(() => { swallow = false; }, 0);
        };
        this._on(viewport, 'pointerup', release);
        this._on(viewport, 'pointercancel', release);

        this._on(viewport, 'click', (e) => {
            if (swallow) {
                swallow = false;
                e.preventDefault();
                e.stopPropagation();
                return;
            }
            const element = e.target.closest ? e.target.closest('[data-graph-node]') : null;
            if (!element || !this.canvas.contains(element)) {
                if (this.options.lineage === 'select' && this.pinned !== null) this.highlight(null);
                return;
            }
            const id = element.dataset.graphNode;
            const event = new CustomEvent('graph:select', {
                bubbles: true, cancelable: true,
                detail: { id, element, graph: this, originalEvent: e },
            });
            if (!this.root.dispatchEvent(event)) { e.preventDefault(); return; }
            if (this.options.lineage === 'select') this.highlight(this.pinned === id ? null : id);
        }, true);

        // a link or a picture inside a node must not start the browser's own drag
        this._on(viewport, 'dragstart', (e) => e.preventDefault());

        this._on(viewport, 'wheel', (e) => {
            const full = this.isFullscreen();
            if (!(e.ctrlKey || e.metaKey)) {
                if (!full) return;   // the wheel alone belongs to the page
                e.preventDefault();
                this._setView(this.view.x - e.deltaX, this.view.y - e.deltaY, this.view.scale);
                return;
            }
            e.preventDefault();
            // a pinch on a trackpad comes as small steps, a mouse wheel as notches of 100 or so
            const delta = e.deltaY * (e.deltaMode === 1 ? 16 : 1);
            const notch = Number.isInteger(delta) && Math.abs(delta) >= 50;
            const factor = Math.exp(-clamp(delta, -240, 240) * (notch ? 0.002 : 0.01));
            this.zoomTo(this.view.scale * factor, local(e.clientX, e.clientY));
        }, { passive: false });

        if (this.options.lineage === 'hover') {
            this._on(this.canvas, 'pointerover', (e) => {
                if (e.pointerType === 'touch') return;
                const element = e.target.closest ? e.target.closest('[data-graph-node]') : null;
                if (element) this._light(element.dataset.graphNode);
            });
            this._on(this.canvas, 'pointerout', (e) => {
                const element = e.target.closest ? e.target.closest('[data-graph-node]') : null;
                if (element && !element.contains(e.relatedTarget)) this._light(this.pinned);
            });
        }

        this._on(this.canvas, 'focusin', (e) => {
            const element = e.target.closest ? e.target.closest('[data-graph-node]') : null;
            // the browser scrolls a focused element into view: the graph moves instead
            viewport.scrollLeft = 0;
            viewport.scrollTop = 0;
            if (!element) return;
            if (this.options.lineage === 'hover') this._light(element.dataset.graphNode);
            if (element.matches(':focus-visible') || e.target.matches(':focus-visible')) this.reveal(element.dataset.graphNode);
        });
        this._on(this.canvas, 'focusout', () => {
            if (this.options.lineage === 'hover') this._light(this.pinned);
        });
        this._on(viewport, 'scroll', () => { viewport.scrollLeft = 0; viewport.scrollTop = 0; });

        if (this.options.keyboard) {
            this._on(viewport, 'keydown', (e) => {
                if (e.ctrlKey || e.metaKey || e.altKey) return;
                if (e.target.closest && e.target.closest('input, textarea, select, [contenteditable=""], [contenteditable="true"]')) return;
                const step = e.shiftKey ? 240 : 60;
                const v = this.view;
                switch (e.key) {
                    case 'ArrowLeft': this._setView(v.x + step, v.y, v.scale); break;
                    case 'ArrowRight': this._setView(v.x - step, v.y, v.scale); break;
                    case 'ArrowUp': this._setView(v.x, v.y + step, v.scale); break;
                    case 'ArrowDown': this._setView(v.x, v.y - step, v.scale); break;
                    case '+': case '=': this.zoomIn(); break;
                    case '-': case '_': this.zoomOut(); break;
                    case '0': this.fit(); break;
                    case 'Home': if (this.options.focus) this.center(this.options.focus); else this.fit(); break;
                    case 'f': case 'F': this.fullscreen(); break;
                    default: return;
                }
                e.preventDefault();
            });
        }

        if (typeof ResizeObserver !== 'undefined') {
            let width = 0;
            let height = 0;
            this._observer = new ResizeObserver(() => {
                const w = viewport.clientWidth;
                const h = viewport.clientHeight;
                if (!w || !h) return;
                if (!this._placed || this._unmeasured) {
                    // shown for the first time (it was hidden, or had no size yet)
                    this.layout();
                } else if (width && height && (w !== width || h !== height)) {
                    // what was in the middle stays in the middle
                    this._setView(this.view.x + (w - width) / 2, this.view.y + (h - height) / 2, this.view.scale);
                }
                width = w;
                height = h;
            });
            this._observer.observe(viewport);
        }

        // the cards change size once the page's fonts are in
        if (document.fonts && document.fonts.ready && document.fonts.status !== 'loaded') {
            document.fonts.ready.then(() => { if (this.root.isConnected && !this._destroyed) this.layout(); });
        }

        this._on(document, 'fullscreenchange', () => {
            this.root.classList.toggle('is-fullscreen', document.fullscreenElement === this.root);
        });
    }

    // -- data ------------------------------------------------------------

    /** Reads the nodes the server rendered (data-graph-node, -from, -rank) and lays them out. */
    refresh() {
        const nodes = [];
        const edges = [];
        this.elements.clear();
        Array.from(this.canvas.querySelectorAll('[data-graph-node]')).forEach((el) => {
            const id = el.dataset.graphNode;
            if (this.elements.has(id)) return;
            this.elements.set(id, el);
            el.classList.add('graph-node');
            const node = { id };
            if (el.dataset.graphRank !== undefined && el.dataset.graphRank !== '') node.rank = Number(el.dataset.graphRank);
            if (el.dataset.graphState) node.state = el.dataset.graphState;
            nodes.push(node);
            (el.dataset.graphFrom || '').split(/[\s,]+/).filter(Boolean).forEach((from) => edges.push({ from, to: id }));
        });
        this.data = { nodes, edges };
        this.layout();
        return this;
    }

    /**
     * Replaces the graph: {nodes: [{id, label, sub, href, html, rank, state,
     * kind, title, className}], edges: [{from, to, state, kind}]}. `html` is
     * inserted as is: it is the caller's to escape.
     */
    setData(data, options = {}) {
        this.elements.forEach((el) => el.remove());
        this.elements.clear();
        const nodes = (data && data.nodes || []).map((n) => Object.assign({}, n, { id: String(n.id) }));
        const edges = (data && data.edges || []).map((e) => Object.assign({}, e, { from: String(e.from), to: String(e.to) }));
        nodes.forEach((n) => {
            if (this.elements.has(n.id)) return;
            const el = this._element(n);
            el.dataset.graphNode = n.id;
            el.classList.add('graph-node');
            if (n.rank !== undefined && n.rank !== null) el.dataset.graphRank = n.rank;
            if (n.state) el.dataset.graphState = n.state;
            if (n.kind) el.dataset.graphKind = n.kind;
            this.canvas.appendChild(el);
            this.elements.set(n.id, el);
        });
        this.data = { nodes, edges };
        if (options.focus !== undefined) this.options.focus = options.focus;
        if (options.keepView !== true) this._placed = false;
        this.pinned = null;
        this.layout();
        return this;
    }

    _element(node) {
        if (typeof this.options.render === 'function') {
            const made = this.options.render(node, this);
            if (made) return made;
        }
        const el = document.createElement(node.href ? 'a' : 'div');
        if (node.href) el.href = node.href;
        if (node.title) el.title = node.title;
        if (node.className) el.className = node.className;
        if (node.html !== undefined && node.html !== null) {
            el.innerHTML = node.html;
        } else if (node.kind === 'junction' && (node.label === undefined || node.label === null)) {
            // a junction is a dot: it has no text unless one is given
        } else {
            const label = document.createElement('span');
            label.className = 'graph-node-label';
            label.textContent = node.label === undefined || node.label === null ? node.id : node.label;
            el.appendChild(label);
            if (node.sub) {
                const sub = document.createElement('span');
                sub.className = 'graph-node-sub';
                sub.textContent = node.sub;
                el.appendChild(sub);
            }
        }
        return el;
    }

    /** The state of a node (a job that turns green): data-graph-state, and the edges that reach it. */
    setState(id, state) {
        const el = this.elements.get(String(id));
        if (!el) return this;
        if (state) el.dataset.graphState = state; else delete el.dataset.graphState;
        const node = this.data.nodes.find((n) => n.id === String(id));
        if (node) node.state = state || undefined;
        this.paths.forEach((p) => {
            if (p.to !== String(id)) return;
            if (state) p.element.dataset.graphState = state; else delete p.element.dataset.graphState;
        });
        return this;
    }

    // -- layout ----------------------------------------------------------

    /** Measures the nodes, places them and draws the edges. */
    layout() {
        const o = this.options;
        let measured = true;
        const nodes = this.data.nodes.map((n) => {
            const el = this.elements.get(n.id);
            const width = el ? el.offsetWidth : 0;
            const height = el ? el.offsetHeight : 0;
            if (!width || !height) measured = false;
            return { id: n.id, rank: n.rank, width: width || undefined, height: height || undefined };
        });
        this._unmeasured = !measured && nodes.length > 0;

        const result = layout({ nodes, edges: this.data.edges }, {
            direction: o.direction, gap: o.gap, rankGap: o.rankGap, sort: o.sort, align: o.align,
        });
        this.result = result;

        result.nodes.forEach((n, id) => {
            const el = this.elements.get(id);
            el.style.left = n.x + 'px';
            el.style.top = n.y + 'px';
            el.dataset.graphPlacedRank = n.rank;
        });
        this.canvas.style.width = result.width + 'px';
        this.canvas.style.height = result.height + 'px';
        this.svg.setAttribute('width', result.width);
        this.svg.setAttribute('height', result.height);
        this.svg.setAttribute('viewBox', '0 0 ' + result.width + ' ' + result.height);

        while (this.svg.firstChild) this.svg.removeChild(this.svg.firstChild);
        this.paths = result.edges.map((edge) => {
            const source = this.data.edges[edge.index] || {};
            const el = document.createElementNS(SVG, 'path');
            el.setAttribute('class', 'graph-edge' + (edge.flat ? ' graph-edge-flat' : ''));
            el.setAttribute('d', path(edge.points, { direction: result.direction, shape: o.edges, radius: o.radius }));
            el.setAttribute('fill', 'none');
            el.dataset.graphEdge = edge.from + ' ' + edge.to;
            const target = this.elements.get(edge.to);
            const state = source.state || (target && target.dataset.graphState);
            if (state) el.dataset.graphState = state;
            if (source.kind) el.dataset.graphKind = source.kind;
            this.svg.appendChild(el);
            return { element: el, from: edge.from, to: edge.to, index: edge.index };
        });

        this.root.classList.add('is-ready');
        this.elements.forEach((el, id) => el.classList.toggle('is-focus', o.focus !== null && String(o.focus) === id));
        this._light(this.pinned);

        if (!this._placed && this.viewport.clientWidth && this.viewport.clientHeight && measured) {
            this._placed = true;
            const stored = this._restore();
            if (stored) this._setView(stored.x, stored.y, stored.scale, { silent: true });
            // a graph too large to be read whole opens on its focus, at its natural size
            else if (o.focus !== null && this.elements.has(String(o.focus)) && this._fitScale(1) < 0.7) this.center(o.focus, { animate: false, scale: 1 });
            else this.fit({ animate: false });
        } else {
            this._apply();
        }

        this.root.dispatchEvent(new CustomEvent('graph:layout', { bubbles: true, detail: { graph: this, layout: result } }));
        return this;
    }

    // -- view ------------------------------------------------------------

    _fitScale(max = 1) {
        const o = this.options;
        const w = this.viewport.clientWidth - 2 * o.padding;
        const h = this.viewport.clientHeight - 2 * o.padding;
        if (!this.result || !this.result.width || !this.result.height || w <= 0 || h <= 0) return 1;
        return clamp(Math.min(w / this.result.width, h / this.result.height, max), o.minZoom, o.maxZoom);
    }

    /** Shows the whole graph, never larger than its natural size. */
    fit(options = {}) {
        if (!this.result) return this;
        const scale = this._fitScale(1);
        const x = (this.viewport.clientWidth - this.result.width * scale) / 2;
        const y = (this.viewport.clientHeight - this.result.height * scale) / 2;
        return this._setView(x, y, scale, options);
    }

    /** Puts a node in the middle of the view; `scale` changes the zoom too. */
    center(id, options = {}) {
        const n = this.result && this.result.nodes.get(String(id));
        if (!n) return this;
        const scale = clamp(options.scale || this.view.scale, this.options.minZoom, this.options.maxZoom);
        const x = this.viewport.clientWidth / 2 - (n.x + n.width / 2) * scale;
        const y = this.viewport.clientHeight / 2 - (n.y + n.height / 2) * scale;
        return this._setView(x, y, scale, options);
    }

    /** Moves the view just enough for a node to be seen whole. */
    reveal(id, margin = 16) {
        const n = this.result && this.result.nodes.get(String(id));
        if (!n) return this;
        const v = this.view;
        const left = n.x * v.scale + v.x;
        const top = n.y * v.scale + v.y;
        const right = left + n.width * v.scale;
        const bottom = top + n.height * v.scale;
        let dx = 0;
        let dy = 0;
        if (left < margin) dx = margin - left;
        else if (right > this.viewport.clientWidth - margin) dx = this.viewport.clientWidth - margin - right;
        if (top < margin) dy = margin - top;
        else if (bottom > this.viewport.clientHeight - margin) dy = this.viewport.clientHeight - margin - bottom;
        if (dx || dy) this._setView(v.x + dx, v.y + dy, v.scale, { animate: true });
        return this;
    }

    /** Zooms to a scale, the point under `at` (px in the viewport; default: its middle) staying where it is. */
    zoomTo(scale, at, options = {}) {
        const v = this.view;
        const next = clamp(scale, this.options.minZoom, this.options.maxZoom);
        const p = at || { x: this.viewport.clientWidth / 2, y: this.viewport.clientHeight / 2 };
        const x = p.x - (p.x - v.x) * next / v.scale;
        const y = p.y - (p.y - v.y) * next / v.scale;
        return this._setView(x, y, next, options);
    }

    zoomIn() { return this.zoomTo(this.view.scale * this.options.zoomStep, null, { animate: true }); }

    zoomOut() { return this.zoomTo(this.view.scale / this.options.zoomStep, null, { animate: true }); }

    /** Moves the view by dx, dy px. */
    pan(dx, dy) { return this._setView(this.view.x + dx, this.view.y + dy, this.view.scale); }

    isFullscreen() {
        return document.fullscreenElement === this.root || this.root.classList.contains('is-fullscreen');
    }

    /** Full screen, or back. Where the browser has no Fullscreen API (iPhone), the graph covers the window. */
    fullscreen(on) {
        const now = this.isFullscreen();
        const wanted = on === undefined ? !now : !!on;
        if (wanted === now) return this;
        if (wanted) {
            if (this.root.requestFullscreen) this.root.requestFullscreen().catch(() => this.root.classList.add('is-fullscreen'));
            else this.root.classList.add('is-fullscreen');
        } else if (document.fullscreenElement === this.root) {
            document.exitFullscreen();
        } else {
            this.root.classList.remove('is-fullscreen');
        }
        return this;
    }

    _setView(x, y, scale, options = {}) {
        this.view = { x, y, scale };
        this._placed = true;
        if (options.animate) {
            this.root.classList.add('is-gliding');
            clearTimeout(this._gliding);
            this._gliding = setTimeout(() => this.root.classList.remove('is-gliding'), 320);
        } else if (options.animate === false) {
            this.root.classList.remove('is-gliding');
        }
        this._apply();
        if (!options.silent) this._announce();
        return this;
    }

    _apply() {
        const v = this.view;
        this.canvas.style.transform = 'translate(' + v.x + 'px,' + v.y + 'px) scale(' + v.scale + ')';
        this.root.style.setProperty('--graph-scale', v.scale);
    }

    _announce() {
        if (this._frame) return;
        const run = () => {
            this._frame = 0;
            if (this._destroyed) return;
            this._remember();
            this.root.dispatchEvent(new CustomEvent('graph:view', {
                bubbles: true,
                detail: { x: this.view.x, y: this.view.y, scale: this.view.scale, graph: this },
            }));
        };
        this._frame = typeof requestAnimationFrame === 'function' ? requestAnimationFrame(run) : setTimeout(run, 16);
    }

    _key() {
        const o = this.options;
        return 'graphjs:' + (o.key || this.root.id || (location.pathname + location.search));
    }

    // What is kept is the point of the graph in the middle of the view, and the
    // zoom: the view comes back right in a window of another size.
    _remember() {
        if (!this.options.remember) return;
        const w = this.viewport.clientWidth;
        const h = this.viewport.clientHeight;
        if (!w || !h) return;
        try {
            sessionStorage.setItem(this._key(), JSON.stringify({
                cx: Math.round((w / 2 - this.view.x) / this.view.scale),
                cy: Math.round((h / 2 - this.view.y) / this.view.scale),
                scale: this.view.scale,
                focus: this.options.focus === null ? null : String(this.options.focus),
                nodes: this.data.nodes.length,
            }));
        } catch (error) { /* private window, storage full: the view is just not kept */ }
    }

    _restore() {
        if (!this.options.remember) return null;
        try {
            const stored = JSON.parse(sessionStorage.getItem(this._key()) || 'null');
            const focus = this.options.focus === null ? null : String(this.options.focus);
            if (!stored || stored.focus !== focus || stored.nodes !== this.data.nodes.length) return null;
            if (![stored.cx, stored.cy, stored.scale].every(Number.isFinite)) return null;
            const scale = clamp(stored.scale, this.options.minZoom, this.options.maxZoom);
            return {
                x: this.viewport.clientWidth / 2 - stored.cx * scale,
                y: this.viewport.clientHeight / 2 - stored.cy * scale,
                scale,
            };
        } catch (error) {
            return null;
        }
    }

    // -- lineage ---------------------------------------------------------

    /** Lights the line of a node (what it descends from, what descends from it) and keeps it lit; null puts it out. */
    highlight(id) {
        this.pinned = id === null || id === undefined ? null : String(id);
        this._light(this.pinned);
        return this;
    }

    _light(id) {
        const on = id !== null && id !== undefined && this.elements.has(String(id));
        this.root.classList.toggle('has-lineage', on);
        const line = on ? lineage(this.data, id) : null;
        this.elements.forEach((el, key) => el.classList.toggle('is-lineage', on && line.nodes.has(key)));
        this.paths.forEach((p) => p.element.classList.toggle('is-lineage', on && line.edges.has(p.index)));
    }

    // -- end -------------------------------------------------------------

    destroy() {
        this._destroyed = true;
        this._listeners.forEach(([target, type, handler, options]) => target.removeEventListener(type, handler, options));
        this._listeners = [];
        if (this._observer) this._observer.disconnect();
        clearTimeout(this._gliding);
        instances.delete(this.root);
    }
}

Graph.layout = layout;
Graph.path = path;
Graph.lineage = lineage;
Graph.presets = PRESETS;

if (typeof window !== 'undefined') {
    window.Graph = window.Graph || Graph;
    const start = () => Graph.ready();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
    // transparent.js swaps the page without loading it: the new graphs start then
    window.addEventListener('transparent:load', start);
}

export default Graph;
