<?php

namespace Tests\Base\Admin\Demo;

use Base\Demo\DemoMode;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * The demonstration's banner in the back office: a screen of the layout
 * (/admin/hours) shows glitchr/omnibase's "Démonstration — données remises à
 * zéro chaque nuit" when the kernel runs the demonstration, and nothing of it
 * otherwise. Whole requests through the host application's kernel (the
 * omnibase harness).
 */
class DemoBannerTest extends KernelTestCase
{
    /** @var object[] */
    private array $users = [];

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel') || !class_exists('App\\Entity\\User')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }
        if (!class_exists(DemoMode::class)) {
            self::markTestSkipped('Requires a glitchr/omnibase with the demo environment.');
        }

        $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] ??= 'phpunit';
    }

    protected function tearDown(): void
    {
        if (static::$booted && $this->users) {
            $em = static::getContainer()->get('doctrine')->getManager();
            $em->clear();
            foreach ($this->users as $user) {
                if ($managed = $em->find($user::class, $user->getId())) {
                    $em->remove($managed);
                }
            }
            $em->flush();
        }
        $this->users = [];
        parent::tearDown();
    }

    /** A screen of the back office as an administrator, the kernel told which environment it demonstrates. */
    private function backOffice(string $environment): Response
    {
        self::bootKernel();
        $container = static::getContainer();
        // What `demo` is, asked of one service: the page is rendered as the demonstration renders it.
        $container->set(DemoMode::class, new DemoMode($environment));

        $em = $container->get('doctrine')->getManager();
        $user = new \App\Entity\User();
        $name = 'banner'.bin2hex(random_bytes(4));
        if (method_exists($user, 'setUsername')) {
            $user->setUsername($name);
        }
        $user->setEmail($name.'@example.org');
        $user->setPlainPassword('test-'.$name);
        $user->setRoles(['ROLE_ADMIN']);
        $em->persist($user);
        $em->flush();
        $this->users[] = $user;

        $session = $container->get('session.factory')->createSession();
        $session->set('_security_main', serialize(new UsernamePasswordToken($user, 'main', $user->getRoles())));
        $session->save();

        $request = Request::create('/admin/hours');
        $request->cookies->set($session->getName(), $session->getId());

        return static::$kernel->handle($request);
    }

    public function testTheBackOfficeShowsTheBannerInTheDemonstration(): void
    {
        $response = $this->backOffice('demo');
        $html = (string) $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('data-demo-banner', $html);
        $this->assertMatchesRegularExpression('/remises à zéro chaque nuit|reset every night/', $html);
        $this->assertLessThan(strpos($html, 'id="page"'), strpos($html, 'data-demo-banner'), 'above the page a navigation swaps');
    }

    public function testAndNothingOfItAnywhereElse(): void
    {
        foreach (['test', 'prod', 'dev'] as $environment) {
            $response = $this->backOffice($environment);

            $this->assertSame(200, $response->getStatusCode(), $environment);
            $this->assertStringNotContainsString('data-demo-banner', (string) $response->getContent(), $environment);
        }
    }
}
