<?php

namespace Base\Admin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

use Base\Admin\Field\FieldValueResolver;
use Base\Bundle\AbstractBaseConfiguration;

class AdminConfiguration extends AbstractBaseConfiguration
{
    /**
     * getTreeBuilder() memoises the builder but this method ADDS children
     * to it, so a second call would redeclare them and blow up with "the
     * node already exists". Symfony calls it once per Processor run, but
     * config:dump-reference and a re-processed extension both call it
     * again on the same instance.
     */
    private bool $childrenDeclared = false;

    /**
     * @inheritdoc
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = $this->getTreeBuilder();
        if ($this->childrenDeclared) {
            return $treeBuilder;
        }
        $this->childrenDeclared = true;

        $treeBuilder->getRootNode()
            ->children()
                // Which field goes in an admin URL in place of the id.
                //
                //     admin:
                //         url_identifier:
                //             fields: ['slug', 'uuid']   # [] = always ids
                //             entities:
                //                 App\Entity\User: ['username']
                //
                // Order is priority: the first field the entity actually
                // has, and whose value is neither blank nor all-digits,
                // wins. Both URL GENERATION and URL RESOLUTION read this,
                // so narrowing it can never produce a link the admin
                // cannot follow - only shorter URLs.
                ->arrayNode('url_identifier')
                    ->info('Readable identifiers used in admin URLs instead of the numeric id.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('fields')
                            ->info('Identifier fields in priority order. Empty list = always use the numeric id.')
                            ->scalarPrototype()->end()
                            ->defaultValue(FieldValueResolver::DEFAULT_IDENTIFIER_FIELDS)
                        ->end()
                        ->booleanNode('lowercase')
                            ->info('Lowercase generated identifiers. Only safe where the database compares strings case-insensitively (MySQL _ci collation).')
                            ->defaultFalse()
                        ->end()
                        ->arrayNode('entities')
                            ->info('Per-entity override, keyed by FQCN. Applies to subclasses too.')
                            ->useAttributeAsKey('class')
                            ->arrayPrototype()
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
