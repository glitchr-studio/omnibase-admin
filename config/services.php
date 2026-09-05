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

    // All three declared explicitly (rather than relying on constructor
    // defaults) so AdminExtension can override the last two by NAME from
    // admin.url_identifier config: named arguments only resolve cleanly
    // when no earlier positional slot is left as a gap.
    $services->set(FieldValueResolver::class)
        ->arg('$accessor', null)
        ->arg('$identifierFields', null)
        ->arg('$identifierFieldsByEntity', [])
        ->arg('$lowercaseIdentifiers', false);

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
        // Registry: lets admin_entity_crud() resolve a related entity to the
        // CRUD that manages it, so association chips link themselves.
        ->args([service(AdminUrlGenerator::class), service(\Base\Admin\Field\FieldValueResolver::class), service(AdminRouteRegistry::class), service(AdminContext::class)])
        ->tag('twig.extension');

    $services->set(\Base\Admin\EventSubscriber\NestHeaderSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(\Base\Admin\EventSubscriber\ActiveAdminsSubscriber::class)
        ->args([service(\App\Repository\UserRepository::class), service('twig'), service('security.helper')])
        ->tag('kernel.event_subscriber');

    $services->set(LayoutStore::class)
        ->args([service('setting_bag')]);

    $services->set(LayoutArranger::class);

    // Shared by every page that hangs its title/description/action-row
    // customization off the CRUD scope: the CRUD controllers (keyed by
    // slug) and the host's system pages (keyed by "system/<page>").
    $services->set(\Base\Admin\Layout\PageCustomization::class)
        ->args([service(LayoutStore::class)]);

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
    //
    // A raw `service('service_container')` reference (the first attempt
    // at this fix) turned out to be fragile in a way that only surfaced
    // later: AbstractController::isGranted() calls
    // $this->container->has('security.authorization_checker'), which is
    // a PRIVATE service (confirmed via debug:container) - Symfony's
    // compiled container only keeps a private service's getter method at
    // all if something ELSE happens to reference it directly at compile
    // time, so whether `service_container`->has() finds it depends on
    // incidental compilation luck, not anything guaranteed. Caught live:
    // after a full cache rebuild, every isGranted() call in this file
    // started throwing "The SecurityBundle is not registered" - the
    // private-service getter had been pruned. A service_locator() is
    // Symfony's actual intended mechanism for this exact situation: it's
    // explicitly allowed to reference private services directly
    // (that's the whole point), independent of compile-time pruning.
    // Built to match AbstractController::getSubscribedServices()'s own
    // contract exactly, so every $this->container->get(...)/has(...)
    // call anywhere in AbstractController (not just isGranted()) keeps
    // working.
    $controllerServiceLocator = service_locator([
        'router' => service('router')->nullOnInvalid(),
        'request_stack' => service('request_stack')->nullOnInvalid(),
        'http_kernel' => service('http_kernel')->nullOnInvalid(),
        'serializer' => service('serializer')->nullOnInvalid(),
        'security.authorization_checker' => service('security.authorization_checker')->nullOnInvalid(),
        'twig' => service('twig')->nullOnInvalid(),
        'form.factory' => service('form.factory')->nullOnInvalid(),
        'security.token_storage' => service('security.token_storage')->nullOnInvalid(),
        'security.csrf.token_manager' => service('security.csrf.token_manager')->nullOnInvalid(),
        'parameter_bag' => service('parameter_bag')->nullOnInvalid(),
        'web_link.http_header_serializer' => service('web_link.http_header_serializer')->nullOnInvalid(),
    ]);

    $services->set(LayoutController::class)
        ->args([service(LayoutStore::class), service('localizer')->nullOnInvalid(), service('base.service.icon')->nullOnInvalid()])
        ->call('setContainer', [$controllerServiceLocator])
        ->public(true)
        ->tag('controller.service_arguments');

    $services->set(AnalyticsController::class)
        ->args([service(\Base\Service\Analytics::class), service('translator'), service(\Base\Admin\Widget\TimelineEventRegistry::class)])
        ->call('setContainer', [$controllerServiceLocator])
        ->public(true)
        ->tag('controller.service_arguments');

    $services->set(\Base\Admin\Controller\DashboardWidgetController::class)
        ->args([service(\Base\Admin\Widget\PaletteWidgetTypeRegistry::class), service('translator'), service(LayoutStore::class)])
        ->call('setContainer', [$controllerServiceLocator])
        ->public(true)
        ->tag('controller.service_arguments');

    // Built-in widget types registered here don't get auto-tagged by
    // AdminBundle's registerForAutoconfiguration() - that's an
    // autoconfigure-only mechanism and this file sets autoconfigure(false)
    // file-wide (same gotcha class as LayoutController/AnalyticsController's
    // setContainer() above). An app-defined widget type needs no such
    // thing - its own autoconfigured services pick up the tag for free.
    $services->set(\Base\Admin\Widget\AnalyticsCardWidgetType::class)
        ->args([service(\Base\Service\Analytics::class), service(\Base\Admin\Widget\TimelineEventRegistry::class), service('translator')])
        ->tag('base.admin.dashboard_widget_type')
        ->tag('base.admin.dashboard_widget_type.palette');

    $services->set(\Base\Admin\Widget\WelcomeWidgetType::class)
        ->args([service('translator')])
        ->tag('base.admin.dashboard_widget_type')
        ->tag('base.admin.dashboard_widget_type.palette');

    $services->set(\Base\Admin\Widget\LinkableEntityRegistry::class)
        ->args([service('doctrine.orm.entity_manager')]);

    $services->set(\Base\Admin\Widget\EntityViewsWidgetType::class)
        ->args([
            service(\Base\Service\Analytics::class),
            service(\Base\Admin\Widget\LinkableEntityRegistry::class),
            service('translator'),
            service(\Base\Admin\Widget\TimelineEventRegistry::class),
        ])
        ->tag('base.admin.dashboard_widget_type')
        ->tag('base.admin.dashboard_widget_type.palette');

    $services->set(\Base\Admin\Widget\CounterWidgetType::class)
        ->args([
            service(\Base\Service\Analytics::class),
            service(\Base\Admin\Widget\LinkableEntityRegistry::class),
            service('translator'),
        ])
        ->tag('base.admin.dashboard_widget_type')
        ->tag('base.admin.dashboard_widget_type.palette');

    // No .palette tag, no constructor args - deliberately excluded from the
    // "+ Add widget" palette (see the class's own docblock: composing a
    // composite is a code-level decision in v1).
    $services->set(\Base\Admin\Widget\CompositeWidgetType::class)
        ->tag('base.admin.dashboard_widget_type');

    $services->set(DashboardWidgetTypeRegistry::class)
        ->args([tagged_iterator('base.admin.dashboard_widget_type')]);

    $services->set(\Base\Admin\Widget\PaletteWidgetTypeRegistry::class)
        ->args([tagged_iterator('base.admin.dashboard_widget_type.palette')]);

    $services->set(\Base\Admin\Widget\TimelineEventRegistry::class)
        ->args([tagged_iterator('base.admin.timeline_event_provider')]);

    $services->set(\Base\Admin\Twig\DashboardWidgetTwigExtension::class)
        ->args([service(DashboardWidgetTypeRegistry::class)])
        ->tag('twig.extension');

    $services->set(\Base\Admin\Twig\DevVersionTwigExtension::class)
        ->args(['%kernel.debug%'])
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
            service('localizer')->nullOnInvalid(),
            tagged_iterator('base.admin.dashboard_controller'),
        ]);
};
