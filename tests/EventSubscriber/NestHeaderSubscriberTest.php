<?php

namespace Tests\Base\Admin\EventSubscriber;

use Base\Admin\EventSubscriber\NestHeaderSubscriber;
use Base\Admin\Router\AdminRouteRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * X-Transparent-Nest: every page under the back office's prefix answers it,
 * whoever routed it - a screen of an application at /admin/outils/canva had
 * to ask with `defaults: ['_nest' => true]`.
 */
class NestHeaderSubscriberTest extends TestCase
{
    /** @param array<string, mixed> $attributes */
    private function header(string $path, array $attributes = [], string $prefix = '/admin', int $type = HttpKernelInterface::MAIN_REQUEST): ?string
    {
        $request = Request::create($path);
        $request->attributes->add($attributes);
        $response = new Response();

        (new NestHeaderSubscriber(new AdminRouteRegistry([], [], $prefix)))
            ->onKernelResponse(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, $type, $response));

        return $response->headers->get(NestHeaderSubscriber::HEADER);
    }

    public function testEveryPathUnderThePrefixIsNested(): void
    {
        $this->assertSame('overlay', $this->header('/admin', ['_route' => 'admin']));
        $this->assertSame('overlay', $this->header('/admin/articles/12/edit', ['_route' => 'admin_crud_articles_edit']));
        $this->assertSame('overlay', $this->header('/admin/outils/canva', ['_route' => 'app_tools_canva']), 'an application\'s own screen, routed outside the CRUD loader');
        $this->assertSame('overlay', $this->header('/admin/nowhere'), 'the back office\'s error page too');
    }

    public function testTheSitesPagesAreNot(): void
    {
        $this->assertNull($this->header('/', ['_route' => 'app_home']));
        $this->assertNull($this->header('/administration', ['_route' => 'app_administration']), 'only starts like the prefix');
        $this->assertNull($this->header('/admin/outils/canva', ['_route' => 'app_tools_canva'], '/admin', HttpKernelInterface::SUB_REQUEST));
    }

    public function testAnotherPrefixIsFollowed(): void
    {
        $this->assertSame('overlay', $this->header('/backoffice/outils', ['_route' => 'app_tools'], '/backoffice'));
        $this->assertNull($this->header('/admin/outils', ['_route' => 'app_tools'], '/backoffice'));
    }

    public function testARouteStillOptsInByNameOrByDefault(): void
    {
        $this->assertSame('overlay', $this->header('/cuisine', ['_route' => 'app_kitchen', '_nest' => true]));
        $this->assertSame('overlay', $this->header('/gestion/reglages', ['_route' => 'admin_settings']));
        $this->assertNull($this->header('/cuisine', ['_route' => 'app_kitchen']));
    }

    public function testWithoutARegistryThePrefixIsAdmin(): void
    {
        $request = Request::create('/admin/outils');
        $response = new Response();
        (new NestHeaderSubscriber())->onKernelResponse(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $this->assertSame('overlay', $response->headers->get(NestHeaderSubscriber::HEADER));
    }
}
