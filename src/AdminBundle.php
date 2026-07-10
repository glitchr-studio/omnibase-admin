<?php

namespace Base\Admin;

use Base\Admin\Controller\CrudControllerInterface;
use Base\Admin\DependencyInjection\Compiler\AdminRoutePass;
use Base\Bundle\AbstractBaseBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AdminBundle extends AbstractBaseBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->registerForAutoconfiguration(CrudControllerInterface::class)
            ->addTag('base.admin.crud_controller')
            ->addTag('controller.service_arguments');

        $container->addCompilerPass(new AdminRoutePass());
    }
}
