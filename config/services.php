<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Base\Admin\Context\AdminContext;
use Base\Admin\Field\FieldValueResolver;
use Base\Admin\Form\FieldFormBuilder;
use Base\Admin\Controller\AnalyticsController;
use Base\Admin\Controller\LayoutController;
use Base\Admin\Layout\LayoutArranger;
use Base\Admin\Layout\LayoutStore;
use Base\Admin\Router\AdminRouteLoader;
use Base\Admin\Router\AdminRouteRegistry;
use Base\Admin\Router\AdminUrlGenerator;
use Base\Admin\Security\SecurityVoter;
use Base\Admin\Widget\DashboardWidgetTypeRegistry;

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
        ->arg('$dashboardControllerFqcns', [])
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
        ->args([service(AdminUrlGenerator::class), service(\Base\Admin\Field\FieldValueResolver::class)])
        ->tag('twig.extension');

    $services->set(\Base\Admin\EventSubscriber\NestHeaderSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(\Base\Admin\EventSubscriber\ActiveAdminsSubscriber::class)
        ->args([service(\App\Repository\UserRepository::class), service('twig'), service('security.helper')])
        ->tag('kernel.event_subscriber');

    $services->set(LayoutStore::class)
        ->args([service('setting_bag')]);

    $services->set(LayoutArranger::class);

    // ->call('setContainer', ...) below is a manual stand-in for what
    // autoconfigure() would normally wire for any AbstractController
    // subclass (the #[Required] setContainer() setter + the
    // container.service_subscriber tag) - both are autoconfigure-only
    // mechanisms, so with autoconfigure(false) set file-wide above, a
    // controller registered here never gets its container set and 500s
    // the instant it calls $this->isGranted()/$this->json()/etc. Found
    // live via curl against beta while wiring AnalyticsController - the
    // exact same gap was already latent in LayoutController, just never
    // exercised end-to-end before now.
    $services->set(LayoutController::class)
        ->args([service(LayoutStore::class)])
        ->call('setContainer', [service('service_container')])
        ->public(true)
        ->tag('controller.service_arguments');

    $services->set(AnalyticsController::class)
        ->args([service(\Base\Service\Analytics::class), service('translator'), service(\Base\Admin\Widget\TimelineEventRegistry::class)])
        ->call('setContainer', [service('service_container')])
        ->public(true)
        ->tag('controller.service_arguments');

    // Built-in widget types registered here don't get auto-tagged by
    // AdminBundle's registerForAutoconfiguration() - that's an
    // autoconfigure-only mechanism and this file sets autoconfigure(false)
    // file-wide (same gotcha class as LayoutController/AnalyticsController's
    // setContainer() above). An app-defined widget type needs no such
    // thing - its own autoconfigured services pick up the tag for free.
    $services->set(\Base\Admin\Widget\AnalyticsCardWidgetType::class)
        ->args([service(\Base\Service\Analytics::class), service(\Base\Admin\Widget\TimelineEventRegistry::class)])
        ->tag('base.admin.dashboard_widget_type');

    $services->set(DashboardWidgetTypeRegistry::class)
        ->args([tagged_iterator('base.admin.dashboard_widget_type')]);

    $services->set(\Base\Admin\Widget\TimelineEventRegistry::class)
        ->args([tagged_iterator('base.admin.timeline_event_provider')]);

    $services->set(\Base\Admin\Twig\DashboardWidgetTwigExtension::class)
        ->args([service(DashboardWidgetTypeRegistry::class)])
        ->tag('twig.extension');

    $services->set(\Base\Admin\Menu\MenuBuilder::class)
        ->args([
            service(AdminRouteRegistry::class),
            service(AdminUrlGenerator::class),
            service('router'),
            service('request_stack'),
            service('translator'),
            service('security.authorization_checker'),
            service(LayoutStore::class),
            service(LayoutArranger::class),
            tagged_iterator('base.admin.dashboard_controller'),
        ]);
};
