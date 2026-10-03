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
 * Any other route opts in with `defaults: ['_nest' => true]` (a screen of
 * the site that opens over the page the same way): the one subscriber for
 * every site - none keeps a copy of its own.
 */
class NestHeaderSubscriber implements EventSubscriberInterface
{
    public const HEADER = 'X-Transparent-Nest';

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
        $route = (string) $request->attributes->get('_route');
        // admin_crud_*, and the back office's own screens (admin_settings, admin_apikey, admin_trash...).
        if ('admin' === $route || str_starts_with($route, AdminRouteRegistry::ROUTE_PREFIX) || str_starts_with($route, 'admin_') || $request->attributes->getBoolean('_nest')) {
            $event->getResponse()->headers->set(self::HEADER, 'overlay');
        }
    }
}
