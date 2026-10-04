<?php

namespace Base\Admin\Menu;

use Base\Admin\Config\Menu\MenuItem;
use Base\Admin\Config\MenuItem as MenuItemFactory;
use Base\Admin\Controller\CrudControllerInterface;
use Base\Admin\Layout\LayoutArranger;
use Base\Admin\Layout\LayoutScope;
use Base\Admin\Layout\LayoutStore;
use Base\Admin\Router\AdminRouteRegistry;
use Base\Admin\Router\AdminUrlGenerator;
use Base\Admin\Security\CrudAccessChecker;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Resolves menu items (URLs + selected state) and provides the default
 * menu — dashboard link plus one entry per registered CRUD controller —
 * shared by the dashboard and every CRUD page.
 */
class MenuBuilder
{
    /**
     * @param iterable<\Base\Admin\Controller\AbstractDashboardController> $dashboardControllers
     */
    public function __construct(
        protected readonly AdminRouteRegistry $registry,
        protected readonly AdminUrlGenerator $adminUrlGenerator,
        protected readonly UrlGeneratorInterface $urlGenerator,
        protected readonly RequestStack $requestStack,
        protected readonly TranslatorInterface $translator,
        protected readonly AuthorizationCheckerInterface $authorizationChecker,
        protected readonly LayoutStore $layoutStore,
        protected readonly LayoutArranger $layoutArranger,
        protected readonly ?\Base\Service\LocalizerInterface $localizer = null,
        protected readonly iterable $dashboardControllers = [],
        protected readonly ?CrudAccessChecker $crudAccessChecker = null,
    ) {
    }

    /**
     * Whether a link to this CRUD controller may be shown to the current
     * user: the entity permission its configureCrud() declares, the same
     * one the controller enforces once reached (see CrudAccessChecker).
     * The dashboard's quick access and the CRUD pages' own cross-links ask
     * here too, so every listing agrees with the menu.
     */
    public function isCrudGranted(string $controllerFqcn): bool
    {
        return $this->crudAccessChecker?->isGranted($controllerFqcn) ?? true;
    }

    /**
     * The app's own curated menu (dashboard link + configureMenuItems()'s
     * sections/entries) when a DashboardController is registered - the same
     * menu the dashboard itself shows - falling back to a generic
     * unsectioned "one entry per registered CRUD" listing only when no app
     * DashboardController exists yet (e.g. mid-migration).
     *
     * @return MenuItem[]
     */
    public function buildDefault(): array
    {
        // A dashboard that keeps the default configureMenuItems() asks back
        // here while resolving its menu: that inner call gets the generic
        // listing, rather than recursing until the stack gives out.
        $dashboardController = $this->building ? null : $this->dashboardController();
        if (null !== $dashboardController) {
            $this->building = true;
            try {
                // already resolved (URLs + selected state) by the dashboard
                // controller's own resolveMenu() - no need to redo it here
                return $dashboardController->getMenuItems();
            } finally {
                $this->building = false;
            }
        }

        $items = [MenuItemFactory::linkToDashboard($this->translator->trans('menu.dashboard', [], 'admin'), 'fa-solid fa-home')];

        foreach ($this->registry->getControllers() as $fqcn => $slug) {
            if (is_subclass_of($fqcn, CrudControllerInterface::class)) {
                $items[] = MenuItemFactory::linkToCrud($fqcn::getEntityFqcn(), ucfirst(str_replace('-', ' ', $slug)), $fqcn::getPreferredIcon());
            }
        }

        return $this->resolve($items, LayoutScope::SIDEBAR);
    }

    private bool $building = false;

    /**
     * The application's dashboard (any outside omnibase/admin's own
     * Base\Admin\Controller namespace), else the first one registered:
     * omnibase/admin's zero-config DashboardController is tagged too, and
     * the order of a tagged iterator is not the application's to choose.
     */
    protected function dashboardController(): ?\Base\Admin\Controller\AbstractDashboardController
    {
        $first = null;
        foreach ($this->dashboardControllers as $dashboardController) {
            $first ??= $dashboardController;
            if (!str_starts_with($dashboardController::class, 'Base\\Admin\\Controller\\')) {
                return $dashboardController;
            }
        }

        return $first;
    }

    /**
     * Profile link (if the user object exposes a getId()) + sign out -
     * the historical "usually better to call the parent method" default.
     *
     * @return MenuItem[]
     */
    public function buildUserMenuDefault(mixed $user): array
    {
        $items = [];

        if (\is_object($user) && method_exists($user, 'getId')) {
            $items[] = MenuItemFactory::linkToRoute('user_profile', ['id' => $user->getId()], $this->translator->trans('menu.profile', [], 'admin'), 'fa-solid fa-id-card');
        }

        $items[] = MenuItemFactory::linkToRoute('user_settings', [], $this->translator->trans('menu.settings', [], 'admin'), 'fa-solid fa-user-gear');

        $items[] = MenuItemFactory::linkToRoute('security_logout', [], $this->translator->trans('menu.logout', [], 'admin'), 'fa-solid fa-arrow-right-from-bracket');

        return $this->resolve($items);
    }

    /**
     * Nests each section's directly-following items under it as subItems,
     * turning configureMenuItems()'s flat "section, item, item, section,
     * item" authoring convention (the same flat/marker style EasyAdmin
     * uses, kept deliberately unchanged so it stays familiar) into the tree
     * filterGranted()/resolveUrl()/markSelected()/LayoutArranger already
     * all recurse into via getSubItems() - none of those needed a single
     * change to support this.
     *
     * The practical effect: a section and its members become one DOM
     * subtree in the sidebar (an outer <li> containing a nested
     * data-sortable <ul>), so dragging the section by its own handle
     * carries its members along for free, while members are still
     * independently reorderable/hideable within their section. Items
     * before the first section, and TYPE_BLOCK items (rendered separately
     * in the sidebar footer, never part of the scrollable menu list), stay
     * top-level and ungrouped.
     *
     * @param MenuItem[] $items
     * @return MenuItem[]
     */
    public function groupIntoSections(array $items): array
    {
        $result = [];
        $currentSection = null;

        foreach ($items as $item) {
            if ($item->isSection()) {
                $currentSection = $item;
                $result[] = $item;
                continue;
            }

            if (null !== $currentSection && MenuItem::TYPE_BLOCK !== $item->getType()) {
                $currentSection->setSubItems([...$currentSection->getSubItems(), $item]);
                continue;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param iterable<MenuItem> $items
     * @return MenuItem[]
     */
    /**
     * @param iterable<MenuItem> $items
     * @param string|null $layoutScope pass a LayoutScope::* constant to
     *        apply the superadmin-customized order/visibility on top of the
     *        code-defined items (see the customizable dashboard/sidebar
     *        feature) - null (the default) skips this entirely, so every
     *        pre-existing caller of resolve() is unaffected.
     * @return MenuItem[]
     */
    /**
     * How many columns the given scope's grid is currently configured for
     * (dashboard.html.twig needs this outside resolve()'s own return value,
     * to set the --widget-columns CSS custom property and the resize
     * control's own upper bound).
     */
    public function getColumns(string $layoutScope): int
    {
        return $this->layoutStore->get($layoutScope)->getColumns();
    }

    /**
     * Code-defined items the superadmin has deleted from the given scope -
     * the "restore a widget" picker's data source (see dashboard.html.twig).
     * Same filterGranted() pass as resolve() so a permission-gated item
     * never shows up as "deleted" just because the current user can't see
     * it in the first place.
     *
     * @param iterable<MenuItem> $items
     * @return MenuItem[]
     */
    public function deletedItems(iterable $items, string $layoutScope): array
    {
        $items = is_array($items) ? $items : iterator_to_array($items, false);
        $items = $this->filterGranted($items);

        return $this->layoutArranger->deletedItems($items, $this->layoutStore->get($layoutScope));
    }

    public function resolve(iterable $items, ?string $layoutScope = null): array
    {
        $items = is_array($items) ? $items : iterator_to_array($items, false);
        $items = $this->filterGranted($items);
        foreach ($items as $item) {
            $this->resolveUrl($item);
        }
        if (null !== $layoutScope) {
            // Locale pair drives per-locale title/description overrides
            // (see LayoutArranger::applyLevel()) - null localizer (pure
            // unit-test setups) degrades to locale-agnostic behavior.
            $items = $this->layoutArranger->apply(
                $items,
                $this->layoutStore->get($layoutScope),
                $this->localizer?->getLocale(),
                $this->localizer ? $this->localizer::getDefaultLocale() : null,
            );
        }
        $this->markSelected($items);

        return $items;
    }

    /**
     * Drops items whose declared permission isn't granted to the current
     * user, recursing into sub-items first - a section/submenu left with
     * nothing after its children are filtered is dropped too, rather than
     * showing an orphaned header.
     *
     * A CRUD link is also dropped when its controller's own entity
     * permission (Crud::setEntityPermission()) is not granted: the page
     * would refuse the user anyway, and the dashboard need not repeat the
     * role on every linkToCrud().
     *
     * @param MenuItem[] $items
     * @return MenuItem[]
     */
    protected function filterGranted(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $permission = $item->getPermission();
            if (null !== $permission && !$this->authorizationChecker->isGranted($permission)) {
                continue;
            }

            if (MenuItem::TYPE_CRUD === $item->getType() && null !== ($controllerFqcn = $this->crudControllerFor($item)) && !$this->isCrudGranted($controllerFqcn)) {
                continue;
            }

            $subItems = $item->getSubItems();
            if ([] !== $subItems) {
                $subItems = $this->filterGranted($subItems);
                if ([] === $subItems && ($item->isSection() || $item->isSubMenu())) {
                    continue;
                }
                $item->setSubItems($subItems);
            }

            $result[] = $item;
        }

        return $result;
    }

    protected function resolveUrl(MenuItem $item): void
    {
        foreach ($item->getSubItems() as $subItem) {
            $this->resolveUrl($subItem);
        }

        $item->setLinkUrl(match ($item->getType()) {
            MenuItem::TYPE_CRUD => $this->generateCrudUrl($item),
            MenuItem::TYPE_ROUTE => $this->urlGenerator->generate($item->getRouteName(), $item->getRouteParameters()),
            MenuItem::TYPE_URL, MenuItem::TYPE_SUBMENU => $item->getUrl(),
            MenuItem::TYPE_DASHBOARD => $this->urlGenerator->generate('admin'),
            MenuItem::TYPE_LOGOUT => $this->urlGenerator->generate('security_logout'),
            default => null,
        });
    }

    protected function generateCrudUrl(MenuItem $item): ?string
    {
        $fqcn = $this->crudControllerFor($item);
        if (null === $fqcn) {
            return null;
        }

        return $this->adminUrlGenerator
            ->setController($fqcn)
            ->setAction($item->getCrudActionName() ?? 'index')
            ->generateUrl();
    }

    /** @var array<string, string>|null entity FQCN => controller FQCN */
    protected ?array $controllersByEntity = null;

    /**
     * The registered controller managing exactly the item's entity (first
     * registered wins, App\ overrides first) - no parent-class fallback, a
     * menu item names the CRUD it wants. Mapped once: the filter and the
     * URL both ask, for every CRUD item of every page.
     */
    protected function crudControllerFor(MenuItem $item): ?string
    {
        if (null === $this->controllersByEntity) {
            $this->controllersByEntity = [];
            foreach ($this->registry->getControllers() as $fqcn => $slug) {
                if (is_subclass_of($fqcn, CrudControllerInterface::class)) {
                    $this->controllersByEntity[$fqcn::getEntityFqcn()] ??= $fqcn;
                }
            }
        }

        return $this->controllersByEntity[$item->getEntityFqcn() ?? ''] ?? null;
    }

    /**
     * The item whose URL is the longest prefix of the current path wins,
     * so /admin/article-tag/12/edit still highlights "Article tag" while
     * /admin only highlights the dashboard.
     *
     * @param MenuItem[] $items
     */
    protected function markSelected(array $items): void
    {
        $currentPath = $this->requestStack->getCurrentRequest()?->getPathInfo();
        if (null === $currentPath) {
            return;
        }

        $best = null;
        $bestLength = -1;

        $visit = function (array $items) use (&$visit, &$best, &$bestLength, $currentPath) {
            foreach ($items as $item) {
                $url = $item->getLinkUrl();
                if (null !== $url) {
                    $path = parse_url($url, PHP_URL_PATH) ?? $url;
                    $isPrefix = $currentPath === $path || str_starts_with($currentPath, rtrim($path, '/') . '/');
                    if ($isPrefix && \strlen($path) > $bestLength) {
                        $best = $item;
                        $bestLength = \strlen($path);
                    }
                }
                $visit($item->getSubItems());
            }
        };
        $visit($items);

        $best?->setSelected(true);
    }
}
