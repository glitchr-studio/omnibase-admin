<?php

namespace Base\Admin;

use Base\Admin\Controller\CrudControllerInterface;
use Base\Admin\DependencyInjection\Compiler\AdminRoutePass;
use Base\Bundle\AbstractBaseBundle;
use Base\Traits\SingletonTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AdminBundle extends AbstractBaseBundle
{
    // Gives this bundle its own singleton storage instead of sharing
    // AbstractBaseBundle's - see that class's constructor for why this is
    // required on every concrete bundle extending it, not just this one.
    use SingletonTrait;

    // The trait's own protected no-op __construct() (there to force
    // singleton access through getInstance()) takes priority over the
    // INHERITED AbstractBaseBundle::__construct() the moment the trait is
    // used directly here, which both hides its real registration logic and
    // makes Symfony's `new AdminBundle()` in bundles.php fatal (protected
    // constructor). Re-declaring it explicitly, public, delegating to
    // parent, restores both.
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Modern bundle layout: the class lives in src/, the bundle root is the
     * package root — so TwigBundle picks up ./templates as @Admin.
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->registerForAutoconfiguration(CrudControllerInterface::class)
            ->addTag('base.admin.crud_controller')
            ->addTag('controller.service_arguments');

        $container->registerForAutoconfiguration(\Base\Admin\Controller\AbstractDashboardController::class)
            ->addTag('base.admin.dashboard_controller');

        $container->addCompilerPass(new AdminRoutePass());
    }
}
