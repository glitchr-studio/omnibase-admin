# Vendored assets

`jquery.min.js` and `transparent.js`/`transparent.css` are plain, unbundled
copies — this package has no Encore/webpack build step of its own. They are
loaded as ordinary `<script>`/`<link>` tags so the admin's own in-page
navigation uses the exact same SPA engine as the rest of the product and the
website-in-website overlay, instead of a second library (Turbo/Hotwired).

To update after a `@glitchr/transparent` release:

    cp <transparent-repo>/src/js/transparent.js public/js/transparent.js
    cp <transparent-repo>/src/css/index.scss     public/css/transparent.css
    # jquery.min.js: cp node_modules/jquery/dist/jquery.min.js public/js/jquery.min.js

Currently vendored at transparent 1.3.2 / jquery 3.7.1.
