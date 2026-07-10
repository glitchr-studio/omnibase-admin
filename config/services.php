<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Base\Admin\Context\AdminContext;
use Base\Admin\Field\FieldValueResolver;
use Base\Admin\Form\FieldFormBuilder;
use Base\Admin\Router\AdminRouteLoader;
use Base\Admin\Router\AdminRouteRegistry;
use Base\Admin\Router\AdminUrlGenerator;
use Base\Admin\Security\SecurityVoter;

/*
 * This file is part of the Glitchr package.
 *
 * (c) Marco Meyer <marco.meyer@glitchr.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return function (ContainerConfigurator $configurator) {

    $services = $configurator->services();
    $services->defaults()
        ->autowire(false)
        ->autoconfigure(false)
        ->public(false);

    $services->set(AdminContext::class);

    $services->set(FieldValueResolver::class);

    $services->set(FieldFormBuilder::class)
        ->args([service('form.factory')]);

    $services->set(AdminRouteRegistry::class)
        ->arg('$controllerFqcns', [])
        ->arg('$urlPrefix', '/admin');

    $services->set(AdminRouteLoader::class)
        ->args([service(AdminRouteRegistry::class)])
        ->tag('routing.loader');

    $services->set(AdminUrlGenerator::class)
        ->args([service('router'), service(AdminRouteRegistry::class)]);

    $services->set(SecurityVoter::class)
        ->args([service('security.authorization_checker')])
        ->tag('security.voter');

    $services->set(\Base\Admin\Twig\AdminTwigExtension::class)
        ->args([service(AdminUrlGenerator::class)])
        ->tag('twig.extension');

    $services->set(\Base\Admin\EventSubscriber\NestHeaderSubscriber::class)
        ->tag('kernel.event_subscriber');
};
