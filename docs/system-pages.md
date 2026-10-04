# System pages, nest, reports, site texts

What omnibase/admin gives every site so that none keeps a copy of its own.

## The back office as a website-in-website

`Base\Admin\EventSubscriber\NestHeaderSubscriber` answers `X-Transparent-Nest: overlay`
for every page of the back office:

- any path under its prefix (`/admin`, `/admin/...`), whoever routed it - the
  CRUDs and the system pages of this bundle, and a screen an application or a
  bundle routes there itself (`#[Route('/admin/outils/canva', name: 'app_admin_canva')]`):
  nothing to add to the route;
- a route named `admin`, `admin_crud_*` or `admin_*`, wherever it is routed.

A page of the site that should open the same way, outside `/admin`, asks for it:

```php
#[Route('/cuisine', name: 'app_kitchen', defaults: ['_nest' => true])]
```

transparentjs opens such a page as a floating, dockable panel over the site when a
link matches its `nest` patterns (`Base.boot({nest: ['/admin*']})`, glitchr/omnibase's
`assets/boot.js`). An application deletes its `App\EventSubscriber\NestHeaderSubscriber`.

## The sidebar, on every screen

The sidebar is the dashboard's whole menu, the same on the dashboard, the
CRUD pages, the system pages and any screen of an application or a bundle:

1. `configureMenuBeforeItems()`;
2. the dashboard's groups - each section (or submenu) of
   `configureWidgetItems()` with its links (CRUD, route, URL); its blocks and
   its "create" shortcuts (`->setCrudAction('new')`) stay on the dashboard;
3. `configureMenuItems()` and `configureMenuAfterItems()`: the system pages,
   last. A link the groups already offer is not repeated; a group named like
   one of these sections adds its links to it.

An application declares its screens once, as the dashboard's groups, and
keeps `configureMenuItems()` for the "System" section. (The sidebar used to
show `configureMenuItems()` alone.) Left as it is, `configureMenuItems()`
adds nothing when the dashboard has groups, and lists every registered CRUD
when it has none.

A screen of its own - a controller routed outside the CRUD loader, whose
template extends `@Admin/layout.html.twig` - needs nothing to get it:

```php
#[Route('/admin/outils/canva', name: 'app_admin_canva')]   // under /admin: nested without asking
public function canva(): Response
{
    return $this->render('admin/tools/canva.html.twig', [...]);   // {% extends '@Admin/layout.html.twig' %}
}
```

The layout takes the `admin_context` the controller passed, or builds the
menus itself (Twig `admin_context()`: `MenuBuilder::buildDefault()` for the
sidebar, `buildUserMenuDefault()` for the account menu). A controller that
seeds the context itself keeps what it set. The item whose URL is the longest
prefix of the current path is the selected one, so a screen listed in the menu
(`MenuItem::linkToRoute('app_admin_canva', ...)`) is highlighted on its page.

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
| `Base\Admin\Settings\BusinessSettingsSection` (90) | settings | phone, address |
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

## Opening hours

`Base\Admin\Controller\HoursController` serves `/admin/hours` (`admin_hours`, ROLE_ADMIN;
routed by the `base_admin` loader): what glitchr/omnibase's `OpeningHours` reads
everywhere - the footer, the day an order is for, the time slots, the page's JSON-LD.

- **The usual week**: seven days, up to three slots each (`Base\Entity\Hours\WeekDayHours`);
  a day left empty is closed. Until a week is saved, the page shows the configured one
  (`base.opening_hours.week`).
- **Special days**: closed, or open at other hours, for one day or a run of days
  (`Base\Entity\Hours\SpecialDay`), added and deleted here.
- **One place** of a site that has several: `/admin/hours?scope=store:12` (or
  `path('admin_hours', {scope: opening_hours(store).scope})`) edits that place's own
  week (`ScopedWeek`) and days off; "Follow the site's week" gives its week up.

Plain forms, a POST and a CSRF token each (`admin_hours_week`, `admin_hours_special`,
`admin_hours_special_delete`); hours that make no sense (a closing before its opening,
two slots overlapping) are refused with a message.

```php
yield MenuItem::linkToRoute('admin_hours', [], 'Horaires', 'fa-solid fa-clock');
```

## Redirections

`Base\Admin\Controller\Crud\RedirectionCrudController` (`/admin/redirections`): the old
addresses of a site taken over and where each leads now (`Base\Entity\Layout\Redirection`,
glitchr/omnibase - see its `docs/40-commons/redirections.md`). An old address is typed
or pasted whole and kept as its path; the list is sorted by visitors carried, with the
date each was last used.

```php
yield MenuItem::linkToCrud(\Base\Entity\Layout\Redirection::class, 'Redirections', 'fa-solid fa-diamond-turn-right');
```

## The business's phone and address

`Base\Admin\Settings\BusinessSettingsSection` (90) adds to the settings page
`base.settings.phone` and `base.settings.address.{street, postal_code, locality, region, country}`:
what glitchr/omnibase's `local_business_jsonld()` prints, ahead of `base.local_business`
in the configuration.

## Tests

`vendor/bin/phpunit` in this checkout, or from the omnibase harness:
`docker compose -f compose.yml -f compose.checkouts.yml run --rm omnibase test admin`
(`tests/bootstrap.php` registers the test namespace in a host's autoloader). The
widget tests that boot a kernel need a host application's environment: the
harness gives them one (its test environment, a fresh SQLite database), as does
an application's own suite. They take the host's content types as they find
them - the first `Thread` subtype the picker offers (`HostContentType`: an
Article in glitchr, a forum Topic or forge Software in the harness) - and skip
what needs rows when the database has none.
