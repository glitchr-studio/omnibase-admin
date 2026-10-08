# Vendored assets

`jquery.min.js`, `transparent.js`/`transparent.css`, `sticky-sortable.js` and
`graph.js`/`layout.js`/`graph.css` are plain, unbundled copies — this package has no Encore/webpack build step
of its own. They are loaded as ordinary `<script>`/`<link>` tags so the
admin's own in-page navigation uses the exact same SPA engine as the rest of
the product and the website-in-website overlay, instead of a second library
(Turbo/Hotwired).

To update after a `@glitchr/transparentjs` release (the repository is glitchr-studio/transparentjs;
the package is not on npm, sites install it from the repository):

    cp <transparent-repo>/src/js/transparent.js public/js/transparent.js
    cp <transparent-repo>/src/css/index.scss     public/css/transparent.css
    # jquery.min.js: cp node_modules/jquery/dist/jquery.min.js public/js/jquery.min.js

Currently vendored at transparentjs 3.0.29 (glitchr-studio/transparentjs 0e87a61) / jquery 3.7.1.
Both files carry the version and the commit on their first line, and are the library's own,
unedited: a fix the back office needs goes into transparentjs first (a new version), then is
copied here - the copy had drifted some 2 500 lines from the library through fixes made only here.

To update after a `@glitchr/stickyjs` release (superadmin customize-mode
drag-and-drop only - written directly in the same plain-UMD, global-jQuery
style as this copy, so it's copy-as-is, no build step):

    cp <stickyjs-repo>/src/js/sortable.js public/js/sticky-sortable.js

Currently vendored at stickyjs 1.1.0. Loaded only for `ROLE_SUPERADMIN`
(see `layout.html.twig`'s topbar block) - moderators/admins never download
it.

To update after a `@glitchr/graphjs` release (github.com/glitchr-studio/graphjs;
a layered directed graph that is dragged and zoomed - HTML nodes, SVG edges,
no dependency, no build step). It is two ES modules and a stylesheet, copied
as they are; `graph.js` imports `./layout.js`, so the two stay side by side
under these names:

    cp <graphjs-repo>/src/js/graph.js   public/js/graph.js
    cp <graphjs-repo>/src/js/layout.js  public/js/layout.js
    cp <graphjs-repo>/src/css/index.css public/css/graph.css

Currently vendored at graphjs 1.0.0 (commit 226ddfe). Loaded on every admin
page by `layout.html.twig`, next to transparent.js: `<link>` for the
stylesheet, `<script type="module">` for `graph.js` (deferred). It starts every
`[data-graph]` of the page, and again after each in-admin navigation (it
listens to `transparent:load`); `window.Graph` is the class. See
`docs/graphs.md`.

IMPORTANT: this is not the only copy in this app. The public site (host
page) consumes `@glitchr/transparent` via `assets/app-defer.js` from
`node_modules/@glitchr/transparent`, which is a hand-copy too (the package
has never actually been `npm publish`ed). Both copies must be updated
together or the host and the admin overlay run different versions of the
SAME library at the same time - this caused real, hard-to-diagnose bugs
(missing loading spinner, no close fade, iframe background bleed-through)
because the half that runs on the host page silently lagged behind.
