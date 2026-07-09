<?php

namespace Tests\Base\Admin;

use Base\Admin\AdminBundle;
use Base\Admin\DependencyInjection\AdminExtension;
use PHPUnit\Framework\TestCase;

class AdminBundleTest extends TestCase
{
    public function testBundleExtensionIsDiscoverable(): void
    {
        $bundle = new AdminBundle();

        $extension = $bundle->getContainerExtension();
        $this->assertInstanceOf(AdminExtension::class, $extension);
        $this->assertEquals('admin', $extension->getAlias());
    }
}
