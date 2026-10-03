# System pages, nest, reports, site texts

What omnibase/admin gives every site so that none keeps a copy of its own.

## The back office as a website-in-website

`Base\Admin\EventSubscriber\NestHeaderSubscriber` answers `X-Transparent-Nest: overlay`
for every back-office route (`admin`, `admin_crud_*`, `admin_*`) and for any route
that asks for it:

```php
#[Route('/cuisine', name: 'app_kitchen', defaults: ['_nest' => true])]
```

transparentjs opens such a page as a floating, dockable panel over the site when a
link matches its `nest` patterns (`Base.boot({nest: ['/admin*']})`, glitchr/omnibase's
`assets/boot.js`). An application deletes its `App\EventSubscriber\NestHeaderSubscriber`.

## Settings and API keys

`Base\Admin\Controller\SystemController` serves `/admin/settings` (`admin_settings`),
`/admin/api-key` (`admin_apikey`, ROLE_SUPERADMIN) and the sidebar's quick edit of the
title and slogan (`admin_settings_quick`). The routes come from the `base_admin`
loader (`config/routes/base_admin.yaml`, already imported by every site).

What the pages hold is declared by sections, services implementing
`Base\Admin\Settings\SettingsSectionInterface` (autoconfigured, tag
`base.admin.settings_section`):

| Section | Page | Fields |
|---|---|---|
| `Base\Admin\Settings\SiteSettingsSection` (100) | settings | logos, title, slogan, meta, launch date, access, maintenance, security, mail |
| `Base\Admin\Settings\SpamKeySection` (100) | apikey | `api.spam.akismet` |
| `Base\Marketplace\Settings\PaymentKeysSection` (50) | apikey | Stripe's three keys, when omnitrade's `stripe` gateway is there |

An application adds its own in `src/` (autowired and autoconfigured like any service):

```php
namespace App\Admin;

use Base\Admin\Settings\SettingsSectionInterface;

final class SiteSection implements SettingsSectionInterface
{
    public function getPage(): string { return self::SETTINGS; }
    public function getFields(): array
    {
        return [
            'app.site.city' => ['required' => false, 'label' => 'Ville'],
            'app.site.photo_credit' => ['required' => false, 'label' => 'Crédit photo'],
        ];
    }
}
```

Fields are LayoutSettingListType's options plus `roles` (the role a field is shown
to). On the API keys page a field is a revealable password field unless it names
another `form_type`, and its value is stored secure. Sections come in their priority
order (`#[AsTaggedItem(priority: …)]`, an application's at 0); a path declared again
by a later section keeps its place and takes the later options (a label changed).

An application that still routes its own `App\Controller\Admin\SystemController`
under `admin_settings`/`admin_apikey` keeps it (its routes are imported after the
loader's); deleting it hands the pages over to this one.

## Reports (complaints)

`Base\Entity\User\Complaint` (glitchr/omnibase) gets its screen:
`Base\Admin\Controller\Crud\ComplaintCrudController` (`/admin/complaints`, route
`admin_crud_complaints_index`). Read only - a report comes from the site - with three
actions: handled, dismissed, reopened (`Complaint::settle()`, by the signed-in staff
member). Who sees a report is `Base\Security\Voter\ComplaintVoter`'s call
(`COMPLAINT_READ`, `COMPLAINT_HANDLE`): the staff above the person it is about.

The dashboard tile `admin_complaints` (`Base\Admin\Widget\ComplaintsWidgetType`):

```php
// DashboardController::configureMenuItems() / configureWidgetItems()
yield MenuItem::linkToCrud(\Base\Entity\User\Complaint::class, 'Signalements', 'fa-solid fa-hand');
yield MenuItem::block('admin_complaints', 'Signalements');
```

## Site texts

`Base\Admin\Controller\Crud\TextOverrideCrudController` (`/admin/text-overrides`):
any text of the `messages` catalogue rewritten for one language
(`Base\Entity\Layout\TextOverride`, glitchr/omnibase). The list of keys shows each
text's current wording in the site's first enabled locale.

```php
yield MenuItem::linkToCrud(\Base\Entity\Layout\TextOverride::class, 'Textes du site', 'fa-solid fa-font');
```

## Tests

`vendor/bin/phpunit` in this checkout, or from the omnibase harness:
`docker compose -f compose.yml -f compose.checkouts.yml run --rm omnibase test admin`
(`tests/bootstrap.php` registers the test namespace in a host's autoloader). The
widget tests that boot a kernel need a host application's environment.
