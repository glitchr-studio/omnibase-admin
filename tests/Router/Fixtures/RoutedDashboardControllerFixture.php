<?php

namespace Tests\Base\Admin\Router\Fixtures;

use Base\Admin\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Stands in for an app that wired its dashboard the original way (own
 * #[Route] attribute) - AdminRouteLoader must never synthesize a
 * duplicate 'admin' route on top of a controller like this one.
 */
class RoutedDashboardControllerFixture extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        return parent::index();
    }
}
