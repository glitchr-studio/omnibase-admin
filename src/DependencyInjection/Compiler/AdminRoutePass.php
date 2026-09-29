<?php

namespace Base\Admin\DependencyInjection\Compiler;

use Base\Admin\Router\AdminRouteRegistry;
use Base\Admin\Security\CrudAccessChecker;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects every service tagged base.admin.crud_controller into the
 * AdminRouteRegistry constructor argument, so routes and URLs resolve
 * from a compiled list instead of runtime reflection - and into a service
 * locator keyed by class for CrudAccessChecker, which reads a controller's
 * entity permission without routing a request through it.
 */
class AdminRoutePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(AdminRouteRegistry::class)) {
            return;
        }

        $controllers = [];
        $locator = [];
        foreach ($container->findTaggedServiceIds('base.admin.crud_controller') as $serviceId => $tags) {
            $class = $container->getDefinition($serviceId)->getClass() ?? $serviceId;
            if ($container->getDefinition($serviceId)->isAbstract()) {
                continue;
            }
            $controllers[] = $class;
            $locator[$class] ??= new Reference($serviceId);
        }

        $dashboardControllers = [];
        foreach ($container->findTaggedServiceIds('base.admin.dashboard_controller') as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            if ($definition->isAbstract()) {
                continue;
            }
            $dashboardControllers[] = $definition->getClass() ?? $serviceId;
        }

        $container->getDefinition(AdminRouteRegistry::class)->setArgument('$controllerFqcns', array_unique($controllers));
        $container->getDefinition(AdminRouteRegistry::class)->setArgument('$dashboardControllerFqcns', array_unique($dashboardControllers));

        if ($container->hasDefinition(CrudAccessChecker::class)) {
            $container->getDefinition(CrudAccessChecker::class)->setArgument('$controllers', ServiceLocatorTagPass::register($container, $locator));
        }
    }
}
