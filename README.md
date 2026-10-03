# Base Bundle — Admin

Administration backoffice extension for [`glitchr/omnibase`](https://gitlab.glitchr.dev/public-repository/symfony/bundle/base): dynamic CRUD controllers, dashboard, menus and field-based form generation — with no dependency on `easycorp/easyadmin-bundle`.

This package owns the `Base\Admin\` namespace. It is a from-scratch replacement for EasyAdmin, designed around three ideas:

- **One field system, everywhere.** `Base\Admin\Field\XxxField` descriptors pre-wire `Base\Field\Type\XxxType` — genuine Symfony Form types living in `base-bundle` — so the exact same form fields work inside the admin and in any regular controller or frontend form.
- **A simple, direct CRUD pipeline.** `AbstractCrudController` actions are plain, linear, overridable controller methods (`index`, `detail`, `edit`, `new`, `delete`) — no event-dispatch indirection, no builder/DTO duplication.
- **Website-in-website UX.** The admin mounts as a [transparentJS](https://gitlab.glitchr.dev/public-repository/javascript/transparent) overlay over the current page: opening the admin is a real navigation to a real URL, and the top-left "×" instantly returns to the exact page you were on. Without transparentJS, every link degrades to a regular full page load.

## Installation

```bash
composer require omnibase/admin
```

Then register the bundle (if not using Flex auto-discovery):

```php
// config/bundles.php
return [
    // ...
    Base\Admin\AdminBundle::class => ['all' => true],
];
```

## Requirements

- PHP 8.1+
- Symfony 6.0+ / 7.0+ / 8.0+
- `glitchr/omnibase` 3.0+

## Documentation

Contextual, on-the-fly documentation for backoffice users is provided by the companion package [`omnibase/docs`](https://gitlab.glitchr.dev/public-repository/symfony/bundle/base/extension/wikidoc), which plugs into this bundle's help panel.

## Tests

```bash
make tests
```

## License

LGPL-3.0-or-later: the text of the license is in [LICENSE](LICENSE), and the GNU GPL v3 it supplements in [COPYING](COPYING). See [SECURITY.md](SECURITY.md) for the vulnerability disclosure policy.
