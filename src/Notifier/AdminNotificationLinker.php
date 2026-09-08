<?php

namespace Base\Admin\Notifier;

use Base\Admin\Router\AdminRouteRegistry;
use Base\Admin\Router\AdminUrlGenerator;
use Base\Entity\User\Notification;
use Base\Notifier\NotificationLinkerInterface;

/**
 * A notification's target, seen from the back-office: the edit page of the
 * CRUD that manages its class. Nothing when the class has no CRUD here.
 */
final class AdminNotificationLinker implements NotificationLinkerInterface
{
    public function __construct(
        private readonly AdminRouteRegistry $registry,
        private readonly AdminUrlGenerator $urlGenerator,
        private readonly ?\Doctrine\ORM\EntityManagerInterface $entityManager = null,
    ) {
    }

    public function adminUrl(Notification $notification): ?string
    {
        $class = $notification->getTargetClass();
        $id = $notification->getTargetId();
        if (!$class || $id === null || $id === '' || !class_exists($class)) {
            return null;
        }

        $controller = $this->registry->getControllerForEntity($class);
        if (!$controller) {
            return null;
        }

        // A non-numeric target id is a uuid (the entity had no id yet when
        // the notification was built): resolve it to the record's own id.
        if (!ctype_digit($id) && $this->entityManager) {
            try {
                $entity = $this->entityManager->getRepository($class)->findOneBy(['uuid' => $id]);
                $id = $entity && method_exists($entity, 'getId') && $entity->getId() !== null ? (string) $entity->getId() : null;
            } catch (\Throwable) {
                $id = null;
            }
            if ($id === null) {
                return null;
            }
        }

        try {
            return $this->urlGenerator->unsetAll()->setController($controller)->setAction('edit')->setEntityId($id)->generateUrl();
        } catch (\Throwable) {
            return null;
        }
    }
}
