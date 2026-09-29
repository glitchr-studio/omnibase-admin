<?php

namespace Tests\Base\Admin\Router;

use Base\Admin\Controller\DashboardController;
use Base\Admin\Router\AdminRouteLoader;
use Base\Admin\Router\AdminRouteRegistry;
use PHPUnit\Framework\TestCase;
use Tests\Base\Admin\Router\Fixtures\Crud\ClashingCrudController;
use Tests\Base\Admin\Router\Fixtures\Crud\ReportCrudController;
use Tests\Base\Admin\Router\Fixtures\RoutedDashboardControllerFixture;

// run under the host app's PHPUnit too, whose autoloader knows no Tests\Base\Admin\
require_once __DIR__ . '/Fixtures/Crud/ReportCrudController.php';
require_once __DIR__ . '/Fixtures/Crud/ClashingCrudController.php';

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

    public function testRegistersTheAdminActionsOfACrudUnderItsSlug(): void
    {
        $registry = new AdminRouteRegistry([ReportCrudController::class]);
        $routes = (new AdminRouteLoader($registry))->load('.', AdminRouteLoader::TYPE);

        $refresh = $routes->get('admin_crud_reports_refresh');
        $this->assertNotNull($refresh);
        $this->assertSame('/admin/reports/refresh', $refresh->getPath());
        $this->assertSame(['POST'], $refresh->getMethods());
        $this->assertSame(ReportCrudController::class . '::refresh', $refresh->getDefault('_controller'));

        $publish = $routes->get('admin_crud_reports_publish');
        $this->assertSame('/admin/reports/{entityId}/publish', $publish->getPath());
        $this->assertSame('[^/]+', $publish->getRequirement('entityId'));

        // no path: the method's name, kebab-cased
        $this->assertSame('/admin/reports/export-all', $routes->get('admin_crud_reports_exportAll')->getPath());
        $this->assertSame(['GET'], $routes->get('admin_crud_reports_exportAll')->getMethods());

        $this->assertNull($routes->get('admin_crud_reports_notAnAction'));
    }

    public function testMountsAdminActionsBeforeTheRecordCatchAll(): void
    {
        $registry = new AdminRouteRegistry([ReportCrudController::class]);
        $names = array_keys((new AdminRouteLoader($registry))->load('.', AdminRouteLoader::TYPE)->all());

        // "/reports/export-all" is a path of its own, not a record called "export-all"
        $this->assertLessThan(array_search('admin_crud_reports_detail', $names, true), array_search('admin_crud_reports_exportAll', $names, true));
    }

    public function testRefusesToRedeclareABuiltInAction(): void
    {
        $this->expectException(\LogicException::class);

        (new AdminRouteLoader(new AdminRouteRegistry([ClashingCrudController::class])))->load('.', AdminRouteLoader::TYPE);
    }
}
