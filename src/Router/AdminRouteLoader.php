<?php

namespace Base\Admin\Router;

use Symfony\Component\Config\Loader\Loader;
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

        foreach ($this->registry->getControllers() as $fqcn => $slug) {
            $add = function (string $action, string $path, array $methods) use ($routes, $prefix, $slug, $fqcn) {
                $routes->add(
                    $this->registry->getRouteName($fqcn, $action),
                    new Route(
                        $prefix . '/' . $slug . $path,
                        ['_controller' => $fqcn . '::' . $action],
                        ['entityId' => '[^/]+'],
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
            $add('detail', '/{entityId}', ['GET']);
            $add('edit', '/{entityId}/edit', ['GET', 'POST']);
            $add('delete', '/{entityId}/delete', ['POST']);
            $add('toggle', '/{entityId}/toggle', ['PATCH']);
        }

        $this->addDashboardRoute($routes, $prefix);
        $this->addLayoutRoute($routes, $prefix);
        $this->addAnalyticsRoute($routes, $prefix);
        $this->addDashboardWidgetRoutes($routes, $prefix);

        return $routes;
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
            ['scope' => 'sidebar|dashboard'],
            [],
            '',
            [],
            ['POST']
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
     * The dashboard "+ Add widget" palette - see DashboardWidgetController.
     * GET + no CSRF (read-only, neither action persists anything).
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
        $routes->add('admin_dashboard_widget_merge', new Route(
            $prefix . '/dashboard/widget/merge',
            ['_controller' => \Base\Admin\Controller\DashboardWidgetController::class . '::merge'],
            [],
            [],
            '',
            [],
            ['GET']
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
