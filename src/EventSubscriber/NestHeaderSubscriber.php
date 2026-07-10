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

        $route = (string) $event->getRequest()->attributes->get('_route');
        if ('admin' === $route || str_starts_with($route, AdminRouteRegistry::ROUTE_PREFIX)) {
            $event->getResponse()->headers->set(self::HEADER, 'overlay');
        }
    }
}
