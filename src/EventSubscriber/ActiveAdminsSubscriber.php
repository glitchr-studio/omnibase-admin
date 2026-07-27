<?php

namespace Base\Admin\EventSubscriber;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Exposes "admin_active_users" (a Twig global: every OTHER admin/
 * superadmin active within User::getOnlineDelay(), most recent first,
 * excluding the current viewer) for the admin topbar's presence indicator
 * - admin-panel requests only, not every page on the site.
 *
 * Deliberately separate from the older, currently-inert Base\Subscriber\
 * AnalyticsSubscriber (public-site "online users" + Google Analytics
 * puller, gated on a narrower/legacy admin check) rather than reviving or
 * depending on it - this is scoped to exactly what the admin topbar needs.
 */
class ActiveAdminsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly Environment $twig,
        private readonly Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 4]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = (string) $event->getRequest()->attributes->get('_route');
        if ('' === $route || !str_starts_with($route, 'admin')) {
            return;
        }

        $currentUser = $this->security->getUser();
        $activeAdmins = $this->userRepository->findActiveAdmins(
            User::getOnlineDelay(),
            $currentUser instanceof User ? $currentUser->getId() : null,
        );
        $this->twig->addGlobal('admin_active_users', $activeAdmins);
    }
}
