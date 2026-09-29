<?php

namespace Base\Admin\Router;

use Base\Admin\Attribute\AdminAction;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Generates the conventional CRUD routes for every registered controller:
 *
 *   {prefix}/{slug}            admin_crud_{slug}_index        GET
 *   {prefix}/{slug}/new        admin_crud_{slug}_new          GET|POST
 *   {prefix}/{slug}/{entityId} admin_crud_{slug}_detail       GET
 *   {prefix}/{slug}/{entityId}/edit    ..._edit               GET|POST
 *   {prefix}/{slug}/{entityId}/delete  ..._delete             POST
 *   {prefix}/{slug}/{entityId}/toggle  ..._toggle             PATCH
 *   {prefix}/{slug}/batch      admin_crud_{slug}_batch_delete POST
 *
 * plus one route per #[AdminAction] method a controller declares, named
 * the same way (admin_crud_{slug}_{method}) - see Base\Admin\Attribute\AdminAction.
 *
 * Loaded with: $routes->import('.', 'base_admin') in the app's routing config.
 */
class AdminRouteLoader extends Loader
{
    public const TYPE = 'base_admin';


    public function __construct(protected readonly AdminRouteRegistry $registry)
    {
        parent::__construct();
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return self::TYPE === $type;
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();
        $prefix = rtrim($this->registry->getUrlPrefix(), '/');

        // Deepest slug FIRST. Slugs are hierarchical now, so one entity's
        // collection path is a prefix of another's: "articles" vs
        // "articles/comments". Symfony matches in registration order, so
        // with the natural (registry) order the parent's catch-all detail
        // route "/admin/articles/{entityId}" would match "/admin/articles/
        // comments" and hand the comments controller's whole subtree to
        // the Article detail action with entityId="comments". Registering
        // "articles/comments" first means the literal path wins and only
        // genuinely unmatched segments fall through to {entityId}.
        $controllers = $this->registry->getControllers();
        uasort($controllers, static fn (string $a, string $b) => substr_count($b, '/') <=> substr_count($a, '/'));

        foreach ($controllers as $fqcn => $slug) {
            $add = function (string $action, string $path, array $methods, array $requirements = []) use ($routes, $prefix, $slug, $fqcn) {
                $routes->add(
                    $this->registry->getRouteName($fqcn, $action),
                    new Route(
                        $prefix . '/' . $slug . $path,
                        ['_controller' => $fqcn . '::' . $action],
                        $requirements + ['entityId' => '[^/]+', 'field' => '[A-Za-z0-9_]+'],
                        [],
                        '',
                        [],
                        $methods
                    )
                );
            };

            $add('index', '', ['GET']);
            $add('new', '/new', ['GET', 'POST']);
            $add('batchDelete', '/batch-delete', ['POST']);

            // The controller's own actions, before the {entityId} catch-all
            // below: a "/refresh" is a path of its own, not a record
            // called "refresh".
            foreach ($this->adminActions($fqcn) as $method => $adminAction) {
                $add($method, $adminAction->getPath($method), $adminAction->methods, $adminAction->requirements);
            }
            $routes->addResource(new FileResource((new \ReflectionClass($fqcn))->getFileName()));

            $add('detail', '/{entityId}', ['GET']);
            $add('edit', '/{entityId}/edit', ['GET', 'POST']);
            $add('delete', '/{entityId}/delete', ['POST']);
            $add('toggle', '/{entityId}/toggle', ['PATCH']);
            // Lazy-loaded slice of an embedded collection - see
            // AbstractCrudController::collectionEntries().
            $add('collectionEntries', '/{entityId}/collection/{field}', ['GET']);
        }

        $this->addDashboardRoute($routes, $prefix);
        $this->addLayoutRoute($routes, $prefix);
        $this->addAnalyticsRoute($routes, $prefix);
        $this->addDashboardWidgetRoutes($routes, $prefix);
        $this->addRevisionRoute($routes, $prefix);
        $this->addTrashRoutes($routes, $prefix);

        return $routes;
    }

    /** The routes load() declares itself - an #[AdminAction] cannot replace one. */
    public const BUILT_IN_ACTIONS = ['index', 'new', 'batchDelete', 'detail', 'edit', 'delete', 'toggle', 'collectionEntries'];

    /**
     * The public methods of $fqcn carrying #[AdminAction], inherited ones
     * included (a base CRUD class or a trait can bring them).
     *
     * @return array<string, AdminAction> method name => attribute
     */
    private function adminActions(string $fqcn): array
    {
        $actions = [];
        foreach ((new \ReflectionClass($fqcn))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || [] === $method->getAttributes(AdminAction::class)) {
                continue;
            }

            if (\in_array($method->getName(), self::BUILT_IN_ACTIONS, true)) {
                throw new \LogicException(sprintf('"%s::%s()" is a built-in CRUD action: #[AdminAction] only declares new ones.', $fqcn, $method->getName()));
            }

            $actions[$method->getName()] = AdminAction::of($fqcn, $method->getName());
        }

        return $actions;
    }

    /**
     * The superadmin customize-mode save endpoint - see LayoutController.
     * One route for both scopes (sidebar/dashboard), matching how the CRUD
     * routes above use a single {entityId} placeholder rather than one
     * route per entity.
     */
    private function addLayoutRoute(RouteCollection $routes, string $prefix): void
    {
        $routes->add('admin_layout_save', new Route(
            $prefix . '/layout/{scope}',
            ['_controller' => \Base\Admin\Controller\LayoutController::class . '::save'],
            ['scope' => implode('|', \Base\Admin\Layout\LayoutScope::all())],
            [],
            '',
            [],
            ['POST']
        ));

        // Single-field quick-save (title/text only) - same "one small
        // request on blur" pattern as the sidebar brand title's own
        // admin_settings_quick, extended to dashboard widgets - see
        // LayoutController::quickSave()'s own doc comment for why this
        // stays narrowly scoped to just those two fields, not a general
        // per-field PATCH endpoint.
        // The page-icon picker's search source (GET, read-only, superadmin
        // check inside the action) - see LayoutController::icons().
        $routes->add('admin_icon_search', new Route(
            $prefix . '/layout/icons',
            ['_controller' => \Base\Admin\Controller\LayoutController::class . '::icons'],
            [],
            [],
            '',
            [],
            ['GET']
        ));

        $routes->add('admin_layout_quick', new Route(
            $prefix . '/layout/{scope}/quick',
            ['_controller' => \Base\Admin\Controller\LayoutController::class . '::quickSave'],
            // sidebar too, not just dashboard: page title/description edits
            // persist onto the page's own SIDEBAR menu item (see
            // layout.html.twig's data-page-title handler).
            ['scope' => implode('|', \Base\Admin\Layout\LayoutScope::all())],
            [],
            '',
            [],
            ['POST']
        ));
    }

    /**
     * The trash - see TrashController. The listing is GET; restore, destroy
     * and empty are POST + CSRF because they write, and two of them destroy.
     */
    private function addTrashRoutes(RouteCollection $routes, string $prefix): void
    {
        $routes->add('admin_trash', new Route(
            $prefix . '/trash',
            ['_controller' => \Base\Admin\Controller\TrashController::class . '::index'],
            [], [], '', [], ['GET']
        ));

        $routes->add('admin_trash_restore', new Route(
            $prefix . '/trash/{id}/restore',
            ['_controller' => \Base\Admin\Controller\TrashController::class . '::restore'],
            ['id' => '\d+'], [], '', [], ['POST']
        ));

        $routes->add('admin_trash_destroy', new Route(
            $prefix . '/trash/{id}/destroy',
            ['_controller' => \Base\Admin\Controller\TrashController::class . '::destroy'],
            ['id' => '\d+'], [], '', [], ['POST']
        ));

        $routes->add('admin_trash_empty', new Route(
            $prefix . '/trash/empty',
            ['_controller' => \Base\Admin\Controller\TrashController::class . '::empty'],
            [], [], '', [], ['POST']
        ));
    }

    /**
     * The per-field history badge's value fetch - see RevisionController.
     * GET + no CSRF like the analytics breakdown: it reads back a value the
     * caller can already read, and writes nothing.
     */
    private function addRevisionRoute(RouteCollection $routes, string $prefix): void
    {
        $routes->add('admin_revision_value', new Route(
            $prefix . '/revision/{id}',
            ['_controller' => \Base\Admin\Controller\RevisionController::class . '::value'],
            ['id' => '\d+'],
            [],
            '',
            [],
            ['GET']
        ));
    }

    /**
     * Dashboard analytics card's range picker - see AnalyticsController.
     * GET + no CSRF (read-only), one route for every supported range like
     * addLayoutRoute()'s single route for every scope.
     */
    private function addAnalyticsRoute(RouteCollection $routes, string $prefix): void
    {
        $routes->add('admin_analytics_breakdown', new Route(
            $prefix . '/analytics/breakdown/{range}',
            ['_controller' => \Base\Admin\Controller\AnalyticsController::class . '::breakdown'],
            ['range' => 'today|7d|14d|30d|all'],
            [],
            '',
            [],
            ['GET']
        ));
    }

    /**
     * The dashboard "+ Add widget" palette (types/newInstance), the
     * merge/split gestures, and the restore-a-deleted-widget action - see
     * DashboardWidgetController. All GET + no CSRF except restore, which
     * is POST + CSRF-checked since it's the one that actually persists
     * something (see its own docblock).
     */
    private function addDashboardWidgetRoutes(RouteCollection $routes, string $prefix): void
    {
        $routes->add('admin_dashboard_widget_types', new Route(
            $prefix . '/dashboard/widget-types',
            ['_controller' => \Base\Admin\Controller\DashboardWidgetController::class . '::types'],
            [],
            [],
            '',
            [],
            ['GET']
        ));
        $routes->add('admin_dashboard_widget_new', new Route(
            $prefix . '/dashboard/widget/{blockName}/new',
            ['_controller' => \Base\Admin\Controller\DashboardWidgetController::class . '::newInstance'],
            ['blockName' => '[\w\-]+'],
            [],
            '',
            [],
            ['GET']
        ));
        // Instance picker options for one class, fetched when the class
        // select changes - see DashboardWidgetController::instances() for
        // why the widgets no longer ship them all inline. The FQCN travels
        // as a query param, not a path segment: it contains backslashes.
        $routes->add('admin_dashboard_widget_instances', new Route(
            $prefix . '/dashboard/widget/instances',
            ['_controller' => \Base\Admin\Controller\DashboardWidgetController::class . '::instances'],
            [],
            [],
            '',
            [],
            ['GET']
        ));
        $routes->add('admin_dashboard_widget_merge', new Route(
            $prefix . '/dashboard/widget/merge',
            ['_controller' => \Base\Admin\Controller\DashboardWidgetController::class . '::merge'],
            [],
            [],
            '',
            [],
            ['GET']
        ));
        $routes->add('admin_dashboard_widget_split', new Route(
            $prefix . '/dashboard/widget/split',
            ['_controller' => \Base\Admin\Controller\DashboardWidgetController::class . '::split'],
            [],
            [],
            '',
            [],
            ['GET']
        ));
        // Unlike the three read-only routes above: POST + CSRF-checked,
        // see DashboardWidgetController::restore()'s own docblock.
        $routes->add('admin_dashboard_widget_restore', new Route(
            $prefix . '/dashboard/widget/restore',
            ['_controller' => \Base\Admin\Controller\DashboardWidgetController::class . '::restore'],
            [],
            [],
            '',
            [],
            ['POST']
        ));
    }

    /**
     * Zero-config dashboard: synthesized exactly like every CRUD route
     * above (an App\ dashboard controller, if registered, wins over the
     * package's own Base\Admin\Controller\DashboardController default) -
     * BUT only when the resolved controller doesn't already declare its
     * own #[Route] attribute. Apps that wired their dashboard the
     * original way (an explicit #[Route('/admin', name: 'admin')] on
     * their own controller's index()) keep using that route untouched;
     * this only fills the gap for apps that never wrote one at all.
     */
    private function addDashboardRoute(RouteCollection $routes, string $prefix): void
    {
        $fqcn = $this->registry->getDashboardControllerFqcn();
        if (null === $fqcn || !\class_exists($fqcn)) {
            return;
        }

        $reflection = new \ReflectionClass($fqcn);
        $hasOwnRoute = [] !== $reflection->getAttributes(RouteAttribute::class)
            || (($method = $reflection->hasMethod('index') ? $reflection->getMethod('index') : null) && [] !== $method->getAttributes(RouteAttribute::class));
        if ($hasOwnRoute) {
            return;
        }

        $routes->add('admin', new Route($prefix, ['_controller' => $fqcn . '::index'], [], [], '', [], ['GET']));
    }
}
