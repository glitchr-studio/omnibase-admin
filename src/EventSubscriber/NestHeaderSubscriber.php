<?php

namespace Base\Admin\EventSubscriber;

use Base\Admin\Router\AdminRouteRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Declares every admin page "nestable": transparentJS only mounts a page
 * into its overlay when the response carries the X-Transparent-Nest header,
 * so a plain visit (or a site without transparentJS) keeps getting the
 * ordinary full page.
 *
 * An admin page is one whose path is under the back office's prefix
 * (/admin): the CRUDs and the system pages routed by this bundle, and any
 * screen an application or a bundle routes there itself
 * (#[Route('/admin/outils/canva')]) - it needs nothing more. A route named
 * admin, admin_* is one too, wherever it is routed.
 *
 * Any other route opts in with `defaults: ['_nest' => true]` (a screen of
 * the site that opens over the page the same way): the one subscriber for
 * every site - none keeps a copy of its own.
 */
class NestHeaderSubscriber implements EventSubscriberInterface
{
    public const HEADER = 'X-Transparent-Nest';

    private string $prefix;

    public function __construct(?AdminRouteRegistry $registry = null)
    {
        $this->prefix = '/'.trim($registry?->getUrlPrefix() ?? '/admin', '/');
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onKernelResponse'];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($this->isAdminPath($request->getPathInfo()) || $this->isAdminRoute((string) $request->attributes->get('_route')) || $request->attributes->getBoolean('_nest')) {
            $event->getResponse()->headers->set(self::HEADER, 'overlay');
        }
    }

    /** /admin and everything under it - not /administration, which only starts the same. */
    private function isAdminPath(string $path): bool
    {
        return '/' !== $this->prefix && ($path === $this->prefix || str_starts_with($path, $this->prefix.'/'));
    }

    /** admin_crud_*, and the back office's own screens (admin_settings, admin_apikey, admin_trash...). */
    private function isAdminRoute(string $route): bool
    {
        return 'admin' === $route || str_starts_with($route, AdminRouteRegistry::ROUTE_PREFIX) || str_starts_with($route, 'admin_');
    }
}
