// Layered layout of a directed graph - no DOM, no dependency.
//
//   layout({nodes: [{id, rank?, width?, height?}], edges: [{from, to}]}, options)
//
// gives every node a place: a rank (its layer), an order inside the rank, and
// x / y in px, without two nodes overlapping. Four steps:
//
//  1. cycles: an edge that closes a cycle is set aside (`ignored`), so are an
//     edge to a node that does not exist, a loop and a repeated edge;
//  2. ranks: the rank given on a node is kept; otherwise a node sits one rank
//     past its farthest parent (longest path). A node WITHOUT parent sits just
//     before what it precedes - not at the top: a spouse who joins the tree at
//     a union stands beside the partner, not five generations above;
//  3. order: barycentric sweeps, down then up, keeping the order with the
//     fewest crossings (`sort: false` keeps the order the nodes came in);
//  4. places: each node is drawn towards its neighbours of the ranks above and
//     below, the order and a gap being kept inside each rank.
//
// An edge that spans several ranks is led through each rank it crosses
// (`points`), so it never runs under a node.
//
// `direction: 'down'` stacks the ranks from top to bottom (a tree),
// `'right'` from left to right (a pipeline).

const DEFAULTS = {
    direction: 'down',
    nodeWidth: 160,   // of a node that gives none
    nodeHeight: 48,
    gap: 24,          // between two neighbours of a rank
    rankGap: 56,      // between two ranks
    sort: true,
    sweeps: 12,
    align: 'start',   // of a node in its rank, when the rank is deeper than it: start, center, end
};

function number(value, fallback) {
    if (value === null || value === undefined || value === '') return fallback;
    const n = Number(value);
    return Number.isFinite(n) ? n : fallback;
}

function round(n) {
    return Math.round(n * 100) / 100;
}

/**
 * Lays a graph out. Returns {nodes: Map(id → {id, x, y, width, height, rank,
 * order}), edges: [{from, to, index, points, flat}], ignored: [{from, to,
 * index, reason}], ranks: [{rank, start, size}], width, height, direction}.
 */
export function layout(data, options = {}) {
    const o = Object.assign({}, DEFAULTS, options);
    const horizontal = o.direction === 'right';

    // -- nodes -----------------------------------------------------------
    const byId = new Map();
    const list = [];
    (data && data.nodes || []).forEach((n) => {
        const id = String(n.id);
        if (byId.has(id)) return;
        const width = Math.max(0, number(n.width, o.nodeWidth));
        const height = Math.max(0, number(n.height, o.nodeHeight));
        const fixed = number(n.rank, null);
        const node = {
            id, index: list.length, width, height,
            fixed: fixed === null ? null : Math.round(fixed),
            size: horizontal ? height : width,    // along the rank
            depth: horizontal ? width : height,   // across the ranks
            in: [], out: [], ups: [], downs: [],
            rank: 0, order: 0, pos: 0, dummy: false,
        };
        byId.set(id, node);
        list.push(node);
    });

    // -- edges -----------------------------------------------------------
    const edges = [];
    const ignored = [];
    const seen = new Set();
    (data && data.edges || []).forEach((e, index) => {
        const from = byId.get(String(e.from));
        const to = byId.get(String(e.to));
        const key = String(e.from) + '\u0000' + String(e.to);
        let reason = null;
        if (!from || !to) reason = 'unknown';
        else if (from === to) reason = 'loop';
        else if (seen.has(key)) reason = 'duplicate';
        if (reason) {
            ignored.push({ from: String(e.from), to: String(e.to), index, reason });
            return;
        }
        seen.add(key);
        edges.push({ from, to, index, cyclic: false });
    });

    breakCycles(list, edges);
    const acyclic = [];
    edges.forEach((e) => {
        if (e.cyclic) ignored.push({ from: e.from.id, to: e.to.id, index: e.index, reason: 'cycle' });
        else { acyclic.push(e); e.from.out.push(e); e.to.in.push(e); }
    });
    ignored.sort((a, b) => a.index - b.index);

    // -- ranks -----------------------------------------------------------
    const free = (n) => n.fixed === null && n.in.length === 0;   // no parent, no rank given
    topological(list).forEach((n) => {
        if (n.fixed !== null) { n.rank = n.fixed; return; }
        if (free(n)) { n.rank = 0; return; }
        let max = -Infinity;
        n.in.forEach((e) => { if (!free(e.from) && e.from.rank > max) max = e.from.rank; });
        n.rank = max === -Infinity ? 1 : max + 1;
    });
    list.forEach((n) => {
        if (!free(n) || !n.out.length) return;
        let min = Infinity;
        n.out.forEach((e) => { if (e.to.rank < min) min = e.to.rank; });
        n.rank = min - 1;
    });
    let first = Infinity;
    let last = -Infinity;
    list.forEach((n) => { if (n.rank < first) first = n.rank; if (n.rank > last) last = n.rank; });
    if (!list.length) { first = 0; last = -1; }

    // -- layers, an edge over several ranks led through each -------------
    const layers = [];
    for (let r = first; r <= last; r++) layers.push([]);
    list.forEach((n) => layers[n.rank - first].push(n));

    const link = (up, down) => { up.downs.push(down); down.ups.push(up); };
    acyclic.forEach((e) => {
        const span = e.to.rank - e.from.rank;
        e.chain = [e.from];
        e.flat = span < 1;
        if (span >= 1) {
            let previous = e.from;
            for (let r = e.from.rank + 1; r < e.to.rank; r++) {
                const dummy = {
                    id: null, dummy: true, index: e.from.index + 0.5, size: 0, depth: 0,
                    ups: [], downs: [], rank: r, order: 0, pos: 0,
                };
                layers[r - first].push(dummy);
                link(previous, dummy);
                e.chain.push(dummy);
                previous = dummy;
            }
            link(previous, e.to);
        }
        e.chain.push(e.to);
    });
    layers.forEach((layer) => {
        layer.forEach((n, i) => { n.seq = i; });
        layer.sort((a, b) => (a.index - b.index) || (a.seq - b.seq));
    });

    // -- order -----------------------------------------------------------
    const number_ = () => layers.forEach((layer) => layer.forEach((n, i) => { n.order = i; }));
    number_();
    if (o.sort) {
        let best = layers.map((layer) => layer.slice());
        let fewest = crossings(layers);
        for (let i = 0; i < o.sweeps && fewest > 0; i++) {
            if (i % 2 === 0) {
                for (let r = 1; r < layers.length; r++) reorder(layers[r], 'ups');
            } else {
                for (let r = layers.length - 2; r >= 0; r--) reorder(layers[r], 'downs');
            }
            const count = crossings(layers);
            if (count < fewest) { fewest = count; best = layers.map((layer) => layer.slice()); }
        }
        best.forEach((layer, r) => { layers[r] = layer; });
        number_();
    }

    // -- places along the rank -------------------------------------------
    const separation = (a, b) => (a.size + b.size) / 2 + (a.dummy && b.dummy ? o.gap / 2 : o.gap);
    layers.forEach((layer) => {
        let at = 0;
        layer.forEach((n, i) => {
            if (i) at += separation(layer[i - 1], n);
            n.pos = at;
        });
    });
    // A link between two nodes that have only each other on that side is a
    // straight line worth keeping: it weighs more.
    const weight = (up, down) => (up.downs.length === 1 && down.ups.length === 1 ? 8 : 1);
    const wish = (n, side) => {
        let sum = 0;
        let total = 0;
        if (side !== 'downs') n.ups.forEach((u) => { const w = weight(u, n); sum += w * u.pos; total += w; });
        if (side !== 'ups') n.downs.forEach((d) => { const w = weight(n, d); sum += w * d.pos; total += w; });
        return total ? sum / total : n.pos;
    };
    const settle = (layer, side) => {
        const wishes = layer.map((n) => wish(n, side));
        return place(layer, wishes, separation);
    };
    for (let i = 0; i < 2; i++) {
        for (let r = 1; r < layers.length; r++) settle(layers[r], 'ups');
        for (let r = layers.length - 2; r >= 0; r--) settle(layers[r], 'downs');
    }
    for (let i = 0; i < 40; i++) {
        let moved = 0;
        if (i % 2 === 0) for (let r = 0; r < layers.length; r++) moved = Math.max(moved, settle(layers[r], 'both'));
        else for (let r = layers.length - 1; r >= 0; r--) moved = Math.max(moved, settle(layers[r], 'both'));
        if (moved < 0.25) break;
    }
    // last, what has a single link down to a node that has a single link up
    // stands straight under it, where there is room
    for (let r = 1; r < layers.length; r++) {
        const layer = layers[r];
        if (!layer.some((n) => n.ups.length === 1 && n.ups[0].downs.length === 1)) continue;
        place(layer, layer.map((n) => (n.ups.length === 1 && n.ups[0].downs.length === 1 ? n.ups[0].pos : n.pos)), separation);
    }
    let left = Infinity;
    let right = -Infinity;
    layers.forEach((layer) => layer.forEach((n) => {
        if (n.pos - n.size / 2 < left) left = n.pos - n.size / 2;
        if (n.pos + n.size / 2 > right) right = n.pos + n.size / 2;
    }));
    if (left === Infinity) { left = 0; right = 0; }
    layers.forEach((layer) => layer.forEach((n) => { n.pos -= left; }));

    // -- ranks across ----------------------------------------------------
    const bands = [];
    let at = 0;
    layers.forEach((layer, r) => {
        let size = 0;
        layer.forEach((n) => { if (n.depth > size) size = n.depth; });
        bands.push({ rank: r + first, start: at, size });
        at += size + o.rankGap;
    });
    const depth = bands.length ? bands[bands.length - 1].start + bands[bands.length - 1].size : 0;
    const band = (n) => bands[n.rank - first];
    const start = (n) => {
        const b = band(n);
        if (o.align === 'center') return b.start + (b.size - n.depth) / 2;
        if (o.align === 'end') return b.start + b.size - n.depth;
        return b.start;
    };
    const between = (r) => {   // the middle of the gap before rank r
        const b = bands[r - first];
        const a = bands[r - first - 1];
        return a ? (a.start + a.size + b.start) / 2 : b.start - o.rankGap / 2;
    };
    // a point given along the rank (`pos`) and across the ranks (`at`)
    const point = (pos, across, via) => {
        const p = horizontal ? { x: round(across), y: round(pos) } : { x: round(pos), y: round(across) };
        if (via !== undefined) p.via = round(via);
        return p;
    };

    // -- result ----------------------------------------------------------
    const nodes = new Map();
    list.forEach((n) => {
        const across = start(n);
        nodes.set(n.id, {
            id: n.id,
            x: round(horizontal ? across : n.pos - n.size / 2),
            y: round(horizontal ? n.pos - n.size / 2 : across),
            width: n.width, height: n.height,
            rank: n.rank, order: n.order,
        });
    });

    const drawn = acyclic.map((e) => {
        const from = e.from;
        const to = e.to;
        const points = [];
        if (e.flat) {
            if (from.rank === to.rank) {   // side to side
                const forward = from.pos <= to.pos;
                const middle = (n) => start(n) + n.depth / 2;
                points.push(point(from.pos + (forward ? 1 : -1) * from.size / 2, middle(from)));
                points.push(point(to.pos + (forward ? -1 : 1) * to.size / 2, middle(to)));
            } else {                        // against the direction
                points.push(point(from.pos, start(from)));
                points.push(point(to.pos, start(to) + to.depth, between(from.rank)));
            }
        } else {
            points.push(point(from.pos, start(from) + from.depth));
            for (let i = 1; i < e.chain.length - 1; i++) {
                const dummy = e.chain[i];
                const b = band(dummy);
                points.push(point(dummy.pos, b.start, between(dummy.rank)));
                if (b.size) points.push(point(dummy.pos, b.start + b.size));
            }
            points.push(point(to.pos, start(to), between(to.rank)));
        }
        return { from: from.id, to: to.id, index: e.index, points, flat: e.flat };
    });

    const breadth = right - left;
    return {
        nodes,
        edges: drawn,
        ignored,
        ranks: bands.map((b) => ({ rank: b.rank, start: round(b.start), size: round(b.size) })),
        width: round(horizontal ? depth : breadth),
        height: round(horizontal ? breadth : depth),
        direction: horizontal ? 'right' : 'down',
    };
}

// Marks the edges that close a cycle, by a depth-first walk in the order the
// nodes came in (no recursion: a chain of thousands of nodes is fine).
function breakCycles(list, edges) {
    const out = new Map(list.map((n) => [n, []]));
    edges.forEach((e) => out.get(e.from).push(e));
    const state = new Map();   // 1: being walked, 2: done
    list.forEach((root) => {
        if (state.get(root)) return;
        const stack = [[root, 0]];
        state.set(root, 1);
        while (stack.length) {
            const top = stack[stack.length - 1];
            const next = out.get(top[0]);
            if (top[1] < next.length) {
                const e = next[top[1]++];
                const s = state.get(e.to);
                if (s === 1) e.cyclic = true;
                else if (!s) { state.set(e.to, 1); stack.push([e.to, 0]); }
            } else {
                state.set(top[0], 2);
                stack.pop();
            }
        }
    });
}

function topological(list) {
    const waiting = new Map(list.map((n) => [n, n.in.length]));
    const queue = list.filter((n) => n.in.length === 0);
    const order = [];
    for (let i = 0; i < queue.length; i++) {
        const n = queue[i];
        order.push(n);
        n.out.forEach((e) => {
            const left = waiting.get(e.to) - 1;
            waiting.set(e.to, left);
            if (left === 0) queue.push(e.to);
        });
    }
    return order;
}

// Sorts the nodes of a layer by the mean order of their neighbours on one
// side; a node without neighbour there keeps its slot.
function reorder(layer, side) {
    const movable = [];
    layer.forEach((n) => {
        const neighbours = n[side];
        if (!neighbours.length) { n.bary = null; return; }
        let sum = 0;
        neighbours.forEach((m) => { sum += m.order; });
        n.bary = sum / neighbours.length;
        movable.push(n);
    });
    movable.sort((a, b) => (a.bary - b.bary) || (a.order - b.order));
    let next = 0;
    for (let i = 0; i < layer.length; i++) {
        if (layer[i].bary !== null) layer[i] = movable[next++];
    }
    layer.forEach((n, i) => { n.order = i; });
}

function crossings(layers) {
    let total = 0;
    for (let r = 1; r < layers.length; r++) {
        const pairs = [];
        layers[r].forEach((n) => n.ups.forEach((u) => pairs.push([u.order, n.order])));
        pairs.sort((a, b) => (a[0] - b[0]) || (a[1] - b[1]));
        total += inversions(pairs.map((p) => p[1]));
    }
    return total;
}

function inversions(values) {
    if (values.length < 2) return 0;
    const buffer = new Array(values.length);
    const sort = (lo, hi) => {
        if (hi - lo < 2) return 0;
        const mid = (lo + hi) >> 1;
        let count = sort(lo, mid) + sort(mid, hi);
        let i = lo;
        let j = mid;
        let k = lo;
        while (i < mid && j < hi) {
            if (values[i] <= values[j]) buffer[k++] = values[i++];
            else { buffer[k++] = values[j++]; count += mid - i; }
        }
        while (i < mid) buffer[k++] = values[i++];
        while (j < hi) buffer[k++] = values[j++];
        for (k = lo; k < hi; k++) values[k] = buffer[k];
        return count;
    };
    return sort(0, values.length);
}

// Puts the nodes of a layer as close to their wishes as their order and their
// gaps allow (least squares: pool adjacent violators). Returns the largest move.
function place(layer, wishes, separation) {
    const offsets = [];
    let offset = 0;
    layer.forEach((n, i) => {
        if (i) offset += separation(layer[i - 1], n);
        offsets.push(offset);
    });
    const blocks = [];
    layer.forEach((n, i) => {
        let block = { sum: wishes[i] - offsets[i], count: 1 };
        while (blocks.length && blocks[blocks.length - 1].sum / blocks[blocks.length - 1].count > block.sum / block.count) {
            const previous = blocks.pop();
            block = { sum: previous.sum + block.sum, count: previous.count + block.count };
        }
        blocks.push(block);
    });
    let moved = 0;
    let i = 0;
    blocks.forEach((block) => {
        const mean = block.sum / block.count;
        for (let k = 0; k < block.count; k++, i++) {
            const pos = mean + offsets[i];
            moved = Math.max(moved, Math.abs(pos - layer[i].pos));
            layer[i].pos = pos;
        }
    });
    return moved;
}

/**
 * The SVG path of an edge through its points. `shape`: 'curve' (an S between
 * two ranks), 'step' (right angles, rounded by `radius`) or 'line'.
 */
export function path(points, options = {}) {
    const horizontal = options.direction === 'right';
    const shape = options.shape || 'curve';
    const radius = options.radius === undefined ? 8 : options.radius;
    if (!points || !points.length) return '';
    // a: across the ranks, b: along the rank
    const a = (p) => (horizontal ? p.x : p.y);
    const b = (p) => (horizontal ? p.y : p.x);
    const xy = (along, across) => (horizontal ? round(across) + ' ' + round(along) : round(along) + ' ' + round(across));
    let d = 'M' + round(points[0].x) + ' ' + round(points[0].y);
    for (let i = 1; i < points.length; i++) {
        const p = points[i - 1];
        const q = points[i];
        const straight = q.via === undefined || shape === 'line' || Math.abs(b(q) - b(p)) < 0.01;
        if (straight) {
            d += 'L' + round(q.x) + ' ' + round(q.y);
        } else if (shape === 'step') {
            const s = Math.sign(a(q) - a(p)) || 1;
            const t = Math.sign(b(q) - b(p));
            const r = Math.max(0, Math.min(radius, Math.abs(b(q) - b(p)) / 2, Math.abs(q.via - a(p)), Math.abs(a(q) - q.via)));
            d += 'L' + xy(b(p), q.via - s * r)
                + 'Q' + xy(b(p), q.via) + ' ' + xy(b(p) + t * r, q.via)
                + 'L' + xy(b(q) - t * r, q.via)
                + 'Q' + xy(b(q), q.via) + ' ' + xy(b(q), q.via + s * r)
                + 'L' + round(q.x) + ' ' + round(q.y);
        } else {
            d += 'C' + xy(b(p), q.via) + ' ' + xy(b(q), q.via) + ' ' + round(q.x) + ' ' + round(q.y);
        }
    }
    return d;
}

/**
 * The line a node belongs to: itself, everything it descends from and
 * everything that descends from it. Returns {nodes: Set(id), edges:
 * Set(index in data.edges)}.
 */
export function lineage(data, id) {
    const start = String(id);
    const nodes = new Set([start]);
    const edges = new Set();
    const up = new Map();
    const down = new Map();
    (data && data.edges || []).forEach((e, index) => {
        const from = String(e.from);
        const to = String(e.to);
        if (!down.has(from)) down.set(from, []);
        if (!up.has(to)) up.set(to, []);
        down.get(from).push([to, index]);
        up.get(to).push([from, index]);
    });
    [up, down].forEach((map) => {
        const visited = new Set([start]);
        const queue = [start];
        for (let i = 0; i < queue.length; i++) {
            (map.get(queue[i]) || []).forEach(([other, index]) => {
                edges.add(index);
                if (visited.has(other)) return;
                visited.add(other);
                nodes.add(other);
                queue.push(other);
            });
        }
    });
    return { nodes, edges };
}

export default layout;
