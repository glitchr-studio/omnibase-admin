# Vendored assets

`jquery.min.js`, `transparent.js`/`transparent.css`, and `sticky-sortable.js`
are plain, unbundled copies — this package has no Encore/webpack build step
of its own. They are loaded as ordinary `<script>`/`<link>` tags so the
admin's own in-page navigation uses the exact same SPA engine as the rest of
the product and the website-in-website overlay, instead of a second library
(Turbo/Hotwired).

To update after a `@glitchr/transparent` release:

    cp <transparent-repo>/src/js/transparent.js public/js/transparent.js
    cp <transparent-repo>/src/css/index.scss     public/css/transparent.css
    # jquery.min.js: cp node_modules/jquery/dist/jquery.min.js public/js/jquery.min.js

Currently vendored at transparent 3.0.0 / jquery 3.7.1.

To update after a `@glitchr/stickyjs` release (superadmin customize-mode
drag-and-drop only - written directly in the same plain-UMD, global-jQuery
style as this copy, so it's copy-as-is, no build step):

    cp <stickyjs-repo>/src/js/sortable.js public/js/sticky-sortable.js

Currently vendored at stickyjs 1.1.0. Loaded only for `ROLE_SUPERADMIN`
(see `layout.html.twig`'s topbar block) - moderators/admins never download
it.

IMPORTANT: this is not the only copy in this app. The public site (host
page) consumes `@glitchr/transparent` via `assets/app-defer.js` from
`node_modules/@glitchr/transparent`, which is a hand-copy too (the package
has never actually been `npm publish`ed). Both copies must be updated
together or the host and the admin overlay run different versions of the
SAME library at the same time - this caused real, hard-to-diagnose bugs
(missing loading spinner, no close fade, iframe background bleed-through)
because the half that runs on the host page silently lagged behind.
