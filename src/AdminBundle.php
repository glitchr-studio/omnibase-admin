<?php

namespace Base\Admin;

use Base\Admin\Controller\CrudControllerInterface;
use Base\Admin\DependencyInjection\Compiler\AdminRoutePass;
use Base\Bundle\AbstractBaseBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AdminBundle extends AbstractBaseBundle
{
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

        $container->addCompilerPass(new AdminRoutePass());
    }
}
