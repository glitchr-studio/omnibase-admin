<?php

namespace Base\Admin\Controller;

use Base\Admin\Config\Menu\MenuItem;
use Base\Admin\Config\MenuItem as MenuItemFactory;
use Base\Admin\Context\AdminContext;
use Base\Admin\Layout\LayoutScope;
use Base\Admin\Menu\MenuBuilder;
use Base\Admin\Router\AdminRouteRegistry;
use Base\Admin\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The admin's mount point: renders the dashboard and owns the menu
 * configuration (before/main/after sections and the user menu).
 */
abstract class AbstractDashboardController extends AbstractController
{
    public const TRANSLATION_DASHBOARD = 'admin';

    protected AdminContext $adminContext;
    protected AdminUrlGenerator $adminUrlGenerator;
    protected AdminRouteRegistry $routeRegistry;
    protected MenuBuilder $menuBuilder;
    protected TranslatorInterface $translator;

    #[Required]
    public function setAdminServices(
        AdminContext $adminContext,
        AdminUrlGenerator $adminUrlGenerator,
        AdminRouteRegistry $routeRegistry,
        MenuBuilder $menuBuilder,
        TranslatorInterface $translator,
    ): void {
        $this->adminContext = $adminContext;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->routeRegistry = $routeRegistry;
        $this->menuBuilder = $menuBuilder;
        $this->translator = $translator;
    }

    public function index(): Response
    {
        $menu = $this->resolveMenu();

        $this->adminContext->setDashboardControllerFqcn(static::class);
        $this->adminContext->setMainMenu($menu);
        $this->adminContext->setUserMenu($this->menuBuilder->resolve($this->toArray($this->configureUserMenu())));

        $widgetItems = array_merge(
            $this->toArray($this->configureWidgetItems()),
            $this->toArray($this->configureDashboardBlockItems()),
        );
        $widgets = $this->menuBuilder->resolve($widgetItems, LayoutScope::DASHBOARD);

        return $this->render('@Admin/dashboard.html.twig', [
            'admin_context' => $this->adminContext,
            'widgets' => $widgets,
            // flat one-card-per-CRUD fallback, only rendered when no
            // widget groups are configured
            'quick_access' => [] === $widgets ? $this->buildQuickAccess($menu) : [],
            'customize_enabled' => $this->isGranted(\Base\Enum\UserRole::SUPERADMIN),
            'dashboard_columns' => $this->menuBuilder->getColumns(LayoutScope::DASHBOARD),
            // same $widgetItems array apply() above just mutated in place -
            // harmless here, a deleted entry is never touched by apply()'s
            // matching loop (see LayoutArranger::applyLevel()'s own comment)
            // so its code-defined label/icon are always still intact
            'deleted_widgets' => $this->menuBuilder->deletedItems($widgetItems, LayoutScope::DASHBOARD),
        ]);
    }

    // -----------------------------------------------------------------
    // configuration hooks (same names as before)
    // -----------------------------------------------------------------

    /**
     * @return iterable<MenuItem>
     */
    public function configureMenuBeforeItems(): iterable
    {
        return [];
    }

    /**
     * @return iterable<MenuItem>
     */
    public function configureMenuItems(): iterable
    {
        return $this->menuBuilder->buildDefault();
    }

    /**
     * @return iterable<MenuItem>
     */
    public function configureMenuAfterItems(): iterable
    {
        return [];
    }

    /**
     * @return iterable<MenuItem>
     */
    public function configureUserMenu(): iterable
    {
        return $this->menuBuilder->buildUserMenuDefault($this->getUser());
    }

    /**
     * The dashboard's central navigation: sections (label + big icon)
     * each carrying sub-items (regular MenuItem crud/route links, plus
     * optional create shortcuts via ->setCrudAction('new')) - the
     * historical configureWidgetItems() card grid. Empty by default:
     * without groups the dashboard falls back to the flat
     * one-card-per-CRUD quick-access listing.
     *
     *     yield MenuItem::section('menu.section.blog', 'fa-solid fa-newspaper')->setSubItems([
     *         MenuItem::linkToCrud(Article::class, 'menu.item.articles', 'fa-solid fa-newspaper'),
     *         MenuItem::linkToCrud(Article::class, 'menu.item.articles', 'fa-solid fa-plus-circle')->setCrudAction('new'),
     *     ]);
     *
     * MenuItem::block() also works here - not just in
     * configureDashboardBlockItems() - so an app can add MORE block-type
     * widgets alongside its link-list groups: another instance of a
     * built-in type (e.g. a second analytics_card with different params),
     * or an app-defined DashboardWidgetTypeInterface implementation
     * (register it as a normal autoconfigured service, no Twig changes
     * needed - see Base\Admin\Widget\DashboardWidgetTypeRegistry). The
     * $instanceKey argument keeps each instance's hide/order/size state
     * independent:
     *
     *     yield MenuItem::block('analytics_card', 'Last 30 days', 'fa-solid fa-chart-line', '30d', ['days' => 30]);
     *
     * @return iterable<MenuItem>
     */
    public function configureWidgetItems(): iterable
    {
        return [];
    }

    /**
     * Non-overridable-in-spirit hook (unlike configureWidgetItems()/
     * configureMenuAfterItems() above, this one isn't meant to be
     * reassigned by app subclasses): supplies the dashboard's built-in
     * block-type widgets (currently just the analytics chart) independently
     * of whatever the app yields from configureWidgetItems(). Both funnel
     * through the same resolve(..., LayoutScope::DASHBOARD) call, so a
     * block is just as orderable/hideable as an app-defined widget group -
     * but an app whose configureWidgetItems() override doesn't call
     * parent:: still gets the built-in blocks, because they never went
     * through that override point to begin with.
     *
     * @return iterable<MenuItem>
     */
    public function configureDashboardBlockItems(): iterable
    {
        return [
            // Full-width, size 1 (the grid's own N columns, not a fixed
            // span) and listed first - a greeting reads oddly squeezed
            // into a partial-width card next to other content, and it's
            // the first thing the historical dashboard showed too.
            // ->setBackground(false): overrides MenuItem's own generic
            // true-by-default (see MenuItem::$background's own docblock) -
            // a fresh/never-customized welcome should still read as part
            // of the page itself, not a boxed card (see welcome.html.twig's
            // own comment); a superadmin can still toggle a real card
            // background on for it later, same as any other widget.
            MenuItemFactory::block('welcome', 'dashboard.welcome_title', 'fa-solid fa-hand-wave')
                ->setSize(5)
                ->setBackground(false),
            MenuItemFactory::block('analytics_card', 'dashboard.analytics_title', 'fa-solid fa-chart-line')
                ->setSize(3), // chart-heavy by default; superadmins can shrink it in customize mode
        ];
    }

    /**
     * Same non-overridable-in-spirit contract as configureDashboardBlockItems(),
     * for the sidebar: the analytics stats block, rendered as a dropdown
     * triggered from the sidebar's bottom icon row (see layout.html.twig).
     *
     * @return iterable<MenuItem>
     */
    public function configureSidebarBlockItems(): iterable
    {
        return [MenuItemFactory::block('analytics_widget')];
    }

    // -----------------------------------------------------------------
    // menu / dashboard resolution
    // -----------------------------------------------------------------

    /**
     * The app's curated menu (dashboard link + configureMenuItems()'s
     * sections/entries), reusable by CRUD/system pages so every /admin/*
     * page shares the same sidebar instead of falling back to a generic
     * unsectioned CRUD listing - see MenuBuilder::buildDefault().
     *
     * @return MenuItem[]
     */
    public function getMenuItems(): array
    {
        return $this->resolveMenu();
    }

    /**
     * @return MenuItem[]
     */
    protected function resolveMenu(): array
    {
        $items = $this->menuBuilder->groupIntoSections(array_merge(
            $this->toArray($this->configureMenuBeforeItems()),
            $this->toArray($this->configureMenuItems()),
            $this->toArray($this->configureMenuAfterItems()),
            $this->toArray($this->configureSidebarBlockItems()),
        ));

        return $this->menuBuilder->resolve($items, LayoutScope::SIDEBAR);
    }

    /**
     * One card per registered CRUD: label, icon and index URL.
     *
     * @param MenuItem[] $menu the already-resolved sidebar menu - its
     *                         hand-written, already-correct labels
     *                         ("Articles", "Comment replies", ...) are
     *                         reused here by entityFqcn instead of deriving
     *                         a label from the URL slug, which produces
     *                         double-barrelled nonsense like "Article
     *                         article" for any entity whose slug repeats
     *                         its own namespace segment (article-article,
     *                         calendar-calendar, destination-destination...)
     * @return array<int, array{label: string, icon: ?string, url: string}>
     */
    protected function buildQuickAccess(array $menu = []): array
    {
        $labelsByFqcn = $this->flattenMenuLabelsByFqcn($menu);

        $cards = [];
        foreach ($this->routeRegistry->getControllers() as $fqcn => $slug) {
            if (!is_subclass_of($fqcn, CrudControllerInterface::class)) {
                continue;
            }

            $entityFqcn = $fqcn::getEntityFqcn();

            $cards[] = [
                'label' => $labelsByFqcn[$entityFqcn] ?? $this->humanize(self::shortClassName($entityFqcn)),
                'icon' => $fqcn::getPreferredIcon(),
                'url' => $this->adminUrlGenerator->setController($fqcn)->setAction('index')->generateUrl(),
            ];
        }

        return $cards;
    }

    /**
     * @param MenuItem[] $items
     * @return array<string, string>
     */
    private function flattenMenuLabelsByFqcn(array $items): array
    {
        $labels = [];
        foreach ($items as $item) {
            if (null !== $item->getEntityFqcn() && is_string($item->getLabel())) {
                $labels[$item->getEntityFqcn()] = $this->translator->trans($item->getLabel(), [], self::TRANSLATION_DASHBOARD);
            }
            $labels += $this->flattenMenuLabelsByFqcn($item->getSubItems());
        }

        return $labels;
    }

    private static function shortClassName(string $fqcn): string
    {
        $segments = explode('\\', $fqcn);

        return end($segments);
    }

    /**
     * Same algorithm Symfony's own `|humanize` Twig filter uses
     * (Symfony\Component\Form\FormRenderer::humanize) - kept identical so a
     * fallback label reads the same as every other humanized label already
     * rendered throughout the admin (column headers, filter labels, ...).
     */
    private function humanize(string $text): string
    {
        $label = ucfirst(trim(strtolower(preg_replace(['/([A-Z])/', '/[_\s]+/'], ['_$1', ' '], $text))));

        return trim(preg_replace('/\s+/', ' ', $label));
    }

    /**
     * @return MenuItem[]
     */
    private function toArray(iterable $items): array
    {
        return is_array($items) ? $items : iterator_to_array($items, false);
    }
}
