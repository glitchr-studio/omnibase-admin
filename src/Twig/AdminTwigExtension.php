<?php

namespace Base\Admin\Twig;

use Base\Admin\Config\Action;
use Base\Admin\Router\AdminUrlGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminTwigExtension extends AbstractExtension
{
    public function __construct(protected readonly AdminUrlGenerator $adminUrlGenerator)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_url', $this->adminUrl(...)),
            new TwigFunction('admin_action_url', $this->adminActionUrl(...)),
        ];
    }

    /**
     * An entity-row action's href: a per-entity callable url (set via
     * Action::linkToUrl(fn($entity) => ...), e.g. the "view on the live
     * site" action) takes priority, then a pre-set static linkUrl, and
     * only then the normal admin_url(controller, crudActionName) route -
     * this is the one place all three of those possibilities are resolved
     * together, since the index/detail templates only ever render one
     * href per row.
     */
    public function adminActionUrl(Action $action, object $entity, string $controllerFqcn): ?string
    {
        $url = $action->getUrl();
        if (\is_callable($url)) {
            return $url($entity);
        }
        if (\is_string($url) && '' !== $url) {
            return $url;
        }

        if (null !== $action->getLinkUrl()) {
            return $action->getLinkUrl();
        }

        return $this->adminUrl($controllerFqcn, $action->getCrudActionName() ?? $action->getName(), $entity->getId());
    }

    public function adminUrl(string $controllerFqcn, string $action = 'index', mixed $entityId = null, array $parameters = []): string
    {
        $generator = $this->adminUrlGenerator
            ->setController($controllerFqcn)
            ->setAction($action);

        if (null !== $entityId) {
            $generator = $generator->setEntityId($entityId);
        }

        foreach ($parameters as $name => $value) {
            $generator = $generator->set($name, $value);
        }

        return $generator->generateUrl();
    }
}
