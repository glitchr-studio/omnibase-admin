<?php

namespace Base\Admin;

use Base\Admin\Controller\CrudControllerInterface;
use Base\Admin\DependencyInjection\Compiler\AdminRoutePass;
use Base\Admin\Widget\DashboardWidgetTypeInterface;
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

        // Same App\-wins override convention BaseBundle uses for
        // Entity/Repository/Enum/Notifier/Form: aliases every concrete
        // Base\Admin\Controller\* class onto App\Admin\Controller\* UNLESS
        // the app already defines a real class there (setAlias() only
        // creates the alias when the App\ side doesn't exist yet) - runs
        // once at container-compile time, not per-request, so it needs
        // none of BaseBundle::warmUp()'s own cache layer.
        $this->setMapping($this->getPath() . '/src/Controller', 'Base\Admin\Controller', 'App\Admin\Controller');

        $container->registerForAutoconfiguration(CrudControllerInterface::class)
            ->addTag('base.admin.crud_controller')
            ->addTag('controller.service_arguments');

        $container->registerForAutoconfiguration(\Base\Admin\Controller\AbstractDashboardController::class)
            ->addTag('base.admin.dashboard_controller');

        // No dedup compiler pass needed here unlike CrudControllerInterface's
        // AdminRoutePass - each widget type is already explicitly named via
        // getName(), so a plain tagged_iterator() in config/services.php is
        // enough (same as MenuBuilder's own dashboard-controller argument).
        $container->registerForAutoconfiguration(DashboardWidgetTypeInterface::class)
            ->addTag('base.admin.dashboard_widget_type');

        $container->addCompilerPass(new AdminRoutePass());
    }
}
