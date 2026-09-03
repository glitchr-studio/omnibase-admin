<?php

namespace Tests\Base\Admin\Layout;

use Base\Admin\Layout\LayoutConfig;
use Base\Admin\Layout\LayoutScope;
use Base\Admin\Layout\LayoutStore;
use Base\Admin\Layout\PageCustomization;
use PHPUnit\Framework\TestCase;

class PageCustomizationTest extends TestCase
{
    private function store(array $items): LayoutStore
    {
        $store = $this->createMock(LayoutStore::class);
        $store->method('get')
            ->with(LayoutScope::CRUD)
            ->willReturn(LayoutConfig::fromArray(['version' => 1, 'items' => $items]));

        return $store;
    }

    public function testAnUncustomizedPageResolvesToItsKeyAndNothingElse(): void
    {
        $customization = (new PageCustomization($this->store([])))->resolve('system/settings');

        $this->assertSame('system/settings', $customization['page_key']);
        $this->assertNull($customization['page']);
        $this->assertSame([], $customization['action_order']);
        $this->assertSame([], $customization['action_icons']);
        $this->assertSame([], $customization['action_hidden']);
    }

    public function testStoredChildrenBecomeTheActionOrderAndIconMap(): void
    {
        $customization = (new PageCustomization($this->store([
            ['key' => 'articles', 'children' => [['key' => 'saveAndReturn', 'icon' => 'fa-solid fa-bolt']]],
            ['key' => 'system/settings', 'children' => [
                ['key' => 'home'],
                ['key' => 'saveAndReturn', 'icon' => 'fa-solid fa-floppy-disk'],
                ['key' => 'saveAndContinue', 'visible' => false],
            ]],
        ])))->resolve('system/settings');

        // Position IS the child order, and only a child that actually
        // carries an icon appears in the icon map - everything else falls
        // back to the icon declared in code.
        $this->assertSame(['home' => 0, 'saveAndReturn' => 1, 'saveAndContinue' => 2], $customization['action_order']);
        $this->assertSame(['saveAndReturn' => 'fa-solid fa-floppy-disk'], $customization['action_icons']);
        // A SET of the exceptions only: everything not listed is shown, so
        // the templates' check stays a plain key lookup.
        $this->assertSame(['saveAndContinue' => true], $customization['action_hidden']);
    }

    public function testAnotherPagesEntryIsNeverReadForThisOne(): void
    {
        // Action names repeat across pages ("saveAndReturn" exists on every
        // entity form AND on every system page), so resolving by key has to
        // stop at the page entry, not merge whatever it finds elsewhere.
        $customization = (new PageCustomization($this->store([
            ['key' => 'articles', 'children' => [['key' => 'saveAndReturn', 'icon' => 'fa-solid fa-bolt']]],
        ])))->resolve('system/settings');

        $this->assertSame([], $customization['action_order']);
        $this->assertSame([], $customization['action_icons']);
        $this->assertSame([], $customization['action_hidden']);
    }
}
