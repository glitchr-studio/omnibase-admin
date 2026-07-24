<?php

namespace Tests\Base\Admin\Router;

use Base\Admin\Controller\DashboardController;
use Base\Admin\Router\AdminRouteLoader;
use Base\Admin\Router\AdminRouteRegistry;
use PHPUnit\Framework\TestCase;
use Tests\Base\Admin\Router\Fixtures\RoutedDashboardControllerFixture;

class AdminRouteLoaderTest extends TestCase
{
    public function testSynthesizesTheAdminRouteForTheZeroConfigDefault(): void
    {
        $registry = new AdminRouteRegistry([], [DashboardController::class]);
        $loader = new AdminRouteLoader($registry);

        $routes = $loader->load('.', AdminRouteLoader::TYPE);
        $route = $routes->get('admin');

        $this->assertNotNull($route, 'A default dashboard with no #[Route] of its own must get one synthesized.');
        $this->assertSame('/admin', $route->getPath());
        $this->assertSame(DashboardController::class . '::index', $route->getDefault('_controller'));
    }

    public function testDoesNotDuplicateAnAlreadyRoutedDashboardController(): void
    {
        $registry = new AdminRouteRegistry([], [RoutedDashboardControllerFixture::class]);
        $loader = new AdminRouteLoader($registry);

        $routes = $loader->load('.', AdminRouteLoader::TYPE);

        $this->assertNull(
            $routes->get('admin'),
            'A dashboard controller that already declares its own #[Route] must not get a duplicate synthesized on top of it.'
        );
    }

    public function testNoDashboardControllerRegisteredSynthesizesNothing(): void
    {
        $registry = new AdminRouteRegistry([]);
        $loader = new AdminRouteLoader($registry);

        $routes = $loader->load('.', AdminRouteLoader::TYPE);

        $this->assertNull($routes->get('admin'));
    }
}
