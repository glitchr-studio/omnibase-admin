<?php

namespace Base\Admin\DependencyInjection\Compiler;

use Base\Admin\Router\AdminRouteRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Collects every service tagged base.admin.crud_controller into the
 * AdminRouteRegistry constructor argument, so routes and URLs resolve
 * from a compiled list instead of runtime reflection.
 */
class AdminRoutePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(AdminRouteRegistry::class)) {
            return;
        }

        $controllers = [];
        foreach ($container->findTaggedServiceIds('base.admin.crud_controller') as $serviceId => $tags) {
            $class = $container->getDefinition($serviceId)->getClass() ?? $serviceId;
            if ($container->getDefinition($serviceId)->isAbstract()) {
                continue;
            }
            $controllers[] = $class;
        }

        $container->getDefinition(AdminRouteRegistry::class)->setArgument('$controllerFqcns', array_unique($controllers));
    }
}
