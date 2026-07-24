<?php

namespace Tests\Base\Admin\Router;

use Base\Admin\Controller\DashboardController;
use Base\Admin\Router\AdminRouteRegistry;
use PHPUnit\Framework\TestCase;

class AdminRouteRegistryTest extends TestCase
{
    public function testDashboardControllerDefaultsToTheOnlyRegisteredOne(): void
    {
        $registry = new AdminRouteRegistry([], [DashboardController::class]);

        $this->assertSame(DashboardController::class, $registry->getDashboardControllerFqcn());
    }

    public function testAppDashboardControllerWinsOverThePackageDefault(): void
    {
        $registry = new AdminRouteRegistry([], [DashboardController::class, 'App\Admin\Controller\DashboardController']);

        $this->assertSame('App\Admin\Controller\DashboardController', $registry->getDashboardControllerFqcn());
    }

    public function testAppDashboardControllerWinsRegardlessOfRegistrationOrder(): void
    {
        $registry = new AdminRouteRegistry([], ['App\Admin\Controller\DashboardController', DashboardController::class]);

        $this->assertSame('App\Admin\Controller\DashboardController', $registry->getDashboardControllerFqcn());
    }

    public function testNoDashboardControllerRegisteredReturnsNull(): void
    {
        $registry = new AdminRouteRegistry([]);

        $this->assertNull($registry->getDashboardControllerFqcn());
    }
}
