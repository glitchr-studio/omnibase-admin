<?php

namespace Tests\Base\Admin\Menu;

use Base\Admin\Config\Menu\MenuItem;
use Base\Admin\Context\AdminContext;
use Base\Admin\Menu\MenuBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The dashboard's whole menu on every admin screen.
 *
 * The applications declare their screens as the dashboard's groups
 * (configureWidgetItems()) and keep configureMenuItems() for the system
 * pages; the sidebar showed configureMenuItems() alone - "Système" and
 * nothing to go anywhere else -, and a screen extending
 * `@Admin/layout.html.twig` from a plain controller (routed outside the CRUD
 * loader, as omnibase/marketplace's quote pipeline or an application's tools)
 * showed an empty one when it did not seed the context itself.
 *
 * Runs in a host application (the omnibase harness: its App DashboardController
 * has four groups, the last one linking the trash).
 */
class SidebarEverywhereTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Controller\\Admin\\DashboardController')) {
            self::markTestSkipped('Requires a host application with a dashboard (the omnibase harness).');
        }

        self::bootKernel();
        $container = static::getContainer();

        $request = Request::create('/admin/somewhere-of-its-own');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $container->get('request_stack')->push($request);
        $user = new InMemoryUser('admin', null, ['ROLE_SUPERADMIN', 'ROLE_ADMIN', 'ROLE_USER']);
        $container->get('security.token_storage')->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    /** @param MenuItem[] $items */
    private static function labels(array $items): array
    {
        return array_map(fn (MenuItem $item) => (string) $item->getLabel(), $items);
    }

    /** @param MenuItem[] $items */
    private static function links(array $items): array
    {
        $links = [];
        foreach ($items as $item) {
            if (null !== $item->getLinkUrl() && !$item->isSection()) {
                $links[] = $item->getLinkUrl();
            }
            $links = [...$links, ...self::links($item->getSubItems())];
        }

        return $links;
    }

    public function testTheSidebarIsTheDashboardsGroups(): void
    {
        $menu = static::getContainer()->get(MenuBuilder::class)->buildDefault();
        $sections = array_values(array_filter($menu, fn (MenuItem $item) => $item->isSection()));

        $this->assertSame(['The shop', 'The forge', 'The forum', 'Members and the site'], self::labels($sections));
        $this->assertContains('Stores', self::labels($sections[0]->getSubItems()));
        $this->assertContains('Trash', self::labels($sections[3]->getSubItems()));

        // Every link once, each with its URL.
        $links = self::links($menu);
        $this->assertNotEmpty($links);
        $this->assertSame($links, array_values(array_unique($links)));
        $this->assertContains(static::getContainer()->get('router')->generate('admin_trash'), $links);
    }

    public function testAScreenExtendingTheLayoutWithoutAContextHasTheSidebar(): void
    {
        $html = static::getContainer()->get('twig')
            ->createTemplate("{% extends '@Admin/layout.html.twig' %}{% block content %}<p id=\"own-screen\">An application's own screen</p>{% endblock %}")
            ->render([]);

        $this->assertStringContainsString('id="own-screen"', $html);
        $this->assertMatchesRegularExpression('/<ul class="admin-menu"/', $html);
        foreach (['The shop', 'The forge', 'The forum', 'Members and the site', 'Stores', 'Trash'] as $label) {
            $this->assertStringContainsString($label, $html, $label.' in the sidebar');
        }
        $this->assertNotEmpty(static::getContainer()->get(AdminContext::class)->getUserMenu(), 'the account menu too');
    }

    public function testAContextSeededByItsControllerIsKept(): void
    {
        $context = static::getContainer()->get(AdminContext::class);
        $context->setMainMenu([\Base\Admin\Config\MenuItem::linkToUrl('Only this', null, '/admin/only-this')]);

        $html = static::getContainer()->get('twig')
            ->createTemplate("{% extends '@Admin/layout.html.twig' %}{% block content %}x{% endblock %}")
            ->render(['admin_context' => $context]);

        $this->assertStringContainsString('Only this', $html);
        $this->assertStringNotContainsString('The forge', $html);
    }
}
