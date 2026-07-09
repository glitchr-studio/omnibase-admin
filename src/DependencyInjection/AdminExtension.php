<?php

namespace Base\Admin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Config\Definition\Processor;

use Symfony\Component\DependencyInjection\ContainerBuilder;

use Base\Bundle\AbstractBaseExtension;

class AdminExtension extends AbstractBaseExtension
{
    public function getConfiguration(array $config, ContainerBuilder $container): AdminConfiguration
    {
        return new AdminConfiguration();
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        //
        // Load service declaration (includes services, controllers,..)
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config'));
        $loader->load('services.php');

        // Configuration file: ./config/package/admin.yaml
        $processor = new Processor();
        $configuration = new AdminConfiguration();
        $config = $processor->processConfiguration($configuration, $configs);
        $this->setConfiguration($container, $config, $configuration->getTreeBuilder()->buildTree()->getName());

        $this->setConfigurationAliases($container);
    }
}
