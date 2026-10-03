<?php

namespace Tests\Base\Admin\Settings;

use Base\Admin\EventSubscriber\NestHeaderSubscriber;
use Base\Admin\Settings\SettingsSectionInterface;
use Base\Admin\Settings\SettingsSections;
use Base\Admin\Settings\SpamKeySection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SettingsSectionsTest extends TestCase
{
    private function section(string $page, array $fields): SettingsSectionInterface
    {
        return new class($page, $fields) implements SettingsSectionInterface {
            public function __construct(private string $page, private array $fields) {}
            public function getPage(): string { return $this->page; }
            public function getFields(): array { return $this->fields; }
        };
    }

    public function testEachPageGathersItsSectionsInOrderAndALaterOneRelabels(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $sections = new SettingsSections([
            new SpamKeySection($translator),
            $this->section(SettingsSectionInterface::API_KEYS, ['api.payment_method.stripe.api_key' => ['label' => 'Stripe']]),
            $this->section(SettingsSectionInterface::SETTINGS, ['app.site.city' => ['label' => 'City']]),
            $this->section(SettingsSectionInterface::API_KEYS, ['api.padlet.key' => ['label' => 'Padlet'], 'api.spam.akismet' => ['label' => 'Akismet (site)']]),
        ]);

        $keys = $sections->fields(SettingsSectionInterface::API_KEYS);
        $this->assertSame(['api.spam.akismet', 'api.payment_method.stripe.api_key', 'api.padlet.key'], array_keys($keys));
        $this->assertSame('Akismet (site)', $keys['api.spam.akismet']['label']);
        $this->assertSame(['app.site.city'], array_keys($sections->fields(SettingsSectionInterface::SETTINGS)));
    }

    public function testAdminRoutesAndRoutesThatAskForItAreNested(): void
    {
        $subscriber = new NestHeaderSubscriber();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $nested = function (array $attributes) use ($subscriber, $kernel): bool {
            $request = new Request([], [], $attributes);
            $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response = new Response());
            $subscriber->onKernelResponse($event);

            return $response->headers->has(NestHeaderSubscriber::HEADER);
        };

        $this->assertTrue($nested(['_route' => 'admin']));
        $this->assertTrue($nested(['_route' => 'admin_crud_comments_index']));
        $this->assertTrue($nested(['_route' => 'admin_settings']));
        $this->assertTrue($nested(['_route' => 'app_board', '_nest' => true]));
        $this->assertFalse($nested(['_route' => 'app_home']));
    }
}
