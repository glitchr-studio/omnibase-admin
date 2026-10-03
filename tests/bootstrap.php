<?php

// Standalone (composer install in this checkout) or inside a host application
// (vendor/omnibase/admin, the omnibase harness): whichever autoloader exists is
// used, and the test namespace is registered by hand - a host's autoloader
// never reads a dependency's autoload-dev.
foreach ([__DIR__.'/../vendor/autoload.php', __DIR__.'/../../../autoload.php'] as $candidate) {
    if (is_file($candidate)) {
        $loader = require $candidate;
        $loader->addPsr4('Tests\\Base\\Admin\\', __DIR__);

        return;
    }
}

throw new RuntimeException('No autoloader found: run composer install in this checkout or install the bundle in an application.');
