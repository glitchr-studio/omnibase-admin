<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

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
};
