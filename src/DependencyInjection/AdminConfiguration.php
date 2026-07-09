<?php

namespace Base\Admin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

use Base\Bundle\AbstractBaseConfiguration;

class AdminConfiguration extends AbstractBaseConfiguration
{
    /**
     * @inheritdoc
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        return $this->getTreeBuilder();
    }
}
