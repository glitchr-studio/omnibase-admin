<?php

namespace Base\Admin\EventSubscriber;

use Base\Admin\Attribute\AdminAction;
use Base\Admin\Controller\AbstractCrudController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Runs a CRUD's gate in front of each of its #[AdminAction] methods, the
 * way index()/edit()/... run it at their top - but before the method, so a
 * controller cannot forget it: entity permission, the action's own
 * permission, disabled action, CSRF token (see
 * AbstractCrudController::guardAdminAction()).
 */
class AdminActionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onKernelController'];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();
        if (!\is_array($controller) || !$controller[0] instanceof AbstractCrudController || !\is_string($controller[1] ?? null)) {
            return;
        }

        $adminAction = AdminAction::of($controller[0]::class, $controller[1]);
        if (null === $adminAction) {
            return;
        }

        $controller[0]->guardAdminAction($controller[1], $adminAction, $event->getRequest());
    }
}
