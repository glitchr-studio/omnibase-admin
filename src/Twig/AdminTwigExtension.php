<?php

namespace Base\Admin\Twig;

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
        ];
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
