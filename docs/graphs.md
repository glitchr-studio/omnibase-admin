# Graphs in the back office

[`@glitchr/graphjs`](https://github.com/glitchr-studio/graphjs) 1.0.0 - a
layered directed graph that is dragged and zoomed: HTML nodes, SVG edges, no
dependency - is vendored in this bundle (`public/js/graph.js`,
`public/js/layout.js`, `public/css/graph.css`) and loaded by
`@Admin/layout.html.twig` on every admin page, the way transparent.js is. A
screen draws a graph without adding a script or a stylesheet.

## Declared in the page

```twig
{% extends '@Admin/layout.html.twig' %}

{% block content %}
<div class="card">
    <div data-graph data-graph-preset="pipeline" id="order-{{ order.id }}-steps" aria-label="{{ 'order.steps'|trans }}">
        <a href="{{ path('admin_crud_orders_detail', {entityId: order.id}) }}" data-graph-node="order" data-graph-state="success">{{ order }}</a>
        {% for shipment in order.shipments %}
            <span data-graph-node="shipment-{{ shipment.id }}" data-graph-from="order" data-graph-state="{{ shipment.delivered ? 'success' : 'running' }}">{{ shipment }}</span>
        {% endfor %}
    </div>
</div>
{% endblock %}
```

Every `[data-graph]` starts by itself when the page loads, and again after an
in-admin navigation (the library listens to `transparent:load`). A node is any
element with `data-graph-node="id"`; `data-graph-from="a b"` draws an edge
from each. Presets: `tree` (top to bottom, right-angled edges) and `pipeline`
(left to right, curved edges, a state on each node).

## Given as data

`window.Graph` is the class, for a screen's own script
(`{% block body_javascript %}`):

```js
const graph = Graph.get(document.querySelector('#pipeline')) ?? new Graph(document.querySelector('#pipeline'), { preset: 'pipeline' });
graph.setData({
    nodes: [{ id: 'build', label: 'build', state: 'success' }, { id: 'test', label: 'tests', state: 'running', href: '/admin/jobs/12' }],
    edges: [{ from: 'build', to: 'test' }],
});
graph.setState('test', 'success');
```

`graph.js` is an ES module, so it runs after the page's classic scripts: a
script that needs `Graph` at once is a module too (`<script type="module">`),
or waits for `DOMContentLoaded`.

## Look

Everything is a `--graph-*` custom property set on the `[data-graph]` element
or above it (`--graph-height`, `--graph-bg`, `--graph-accent`,
`--graph-node-bg`...). The graph follows the system's light or dark scheme;
`data-graph-theme="light"` or `"dark"` on the element forces one.

The attributes, the API, the events (`graph:select`, `graph:view`,
`graph:layout`) and the variables are in the library's own README and `docs/`.
Updating the copy: `public/js/README.md`.
