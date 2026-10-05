<?php

namespace Base\Admin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Config\Definition\Processor;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

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

        // Injected straight into the service rather than left to
        // setConfiguration() below: that helper recurses into EVERY array
        // it meets, so a LIST like ['slug','uuid'] would be flattened into
        // admin.url_identifier.fields.0 / .1 parameters instead of one
        // array parameter - and the entities map, whose keys are FQCNs,
        // would fare worse still. Pulled out of $config so it never
        // reaches that recursion.
        $urlIdentifier = $config['url_identifier'] ?? [];
        unset($config['url_identifier']);

        $resolver = $container->getDefinition(\Base\Field\FieldValueResolver::class)
            ->setArgument('$identifierFields', $urlIdentifier['fields'] ?? \Base\Field\FieldValueResolver::DEFAULT_IDENTIFIER_FIELDS)
            ->setArgument('$identifierFieldsByEntity', $urlIdentifier['entities'] ?? [])
            ->setArgument('$lowercaseIdentifiers', $urlIdentifier['lowercase'] ?? false);

        // Doctrine tells the resolver which of those fields are columns: a
        // getSlug() computed from the title is not put in an address
        // findEntity() could not resolve - the record is linked by its id.
        // The argument is asked of the installed class: a glitchr/omnibase
        // that does not have it yet is wired as before.
        foreach ((new \ReflectionMethod(\Base\Field\FieldValueResolver::class, '__construct'))->getParameters() as $parameter) {
            if ('doctrine' === $parameter->getName()) {
                $resolver->setArgument('$doctrine', new Reference('doctrine', ContainerInterface::NULL_ON_INVALID_REFERENCE));
            }
        }

        $this->setConfiguration($container, $config, $configuration->getTreeBuilder()->buildTree()->getName());

        // NB: no setConfigurationAliases() here - Base\Admin IS the canonical
        // namespace of this package; the swapped-segment service aliases the
        // other extension bundles use would only duplicate every definition.
    }
}
