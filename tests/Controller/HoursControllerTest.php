<?php

namespace Tests\Base\Admin\Controller;

use Base\Entity\Hours\ScopedWeek;
use Base\Entity\Hours\SpecialDay;
use Base\Entity\Hours\WeekDayHours;
use Base\Service\OpeningHours;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * The opening-hours screen (/admin/hours) as an administrator uses it: the
 * page, the usual week saved, a special day added and deleted, one place's
 * own week - and what OpeningHours then answers. Whole requests through the
 * host application's kernel (the omnibase harness), signed in by a session.
 */
class HoursControllerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private SessionInterface $session;
    /** @var object[] */
    private array $users = [];

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel') || !class_exists('App\\Entity\\User')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }

        self::bootKernel();
        $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] ??= 'phpunit';
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->clean();
    }

    protected function tearDown(): void
    {
        if (isset($this->em)) {
            $this->em->clear();
            $this->clean();
            foreach ($this->users as $user) {
                if ($managed = $this->em->find($user::class, $user->getId())) {
                    $this->em->remove($managed);
                }
            }
            $this->em->flush();
        }
        parent::tearDown();
    }

    private function clean(): void
    {
        foreach ([ScopedWeek::class, SpecialDay::class, WeekDayHours::class] as $class) {
            $this->em->createQuery('DELETE FROM '.$class.' e')->execute();
        }
    }

    /** @param string[] $roles */
    private function signIn(array $roles): void
    {
        $user = new \App\Entity\User();
        $name = 'hours'.bin2hex(random_bytes(4));
        if (method_exists($user, 'setUsername')) {
            $user->setUsername($name);
        }
        $user->setEmail($name.'@example.org');
        $user->setPlainPassword('test-'.$name);
        $user->setRoles($roles);
        $this->em->persist($user);
        $this->em->flush();
        $this->users[] = $user;

        $this->session = static::getContainer()->get('session.factory')->createSession();
        $this->session->set('_security_main', serialize(new UsernamePasswordToken($user, 'main', $user->getRoles())));
        $this->session->save();
    }

    /** @param array<string, mixed> $parameters */
    private function request(string $method, string $path, array $parameters = []): Response
    {
        $request = Request::create($path, $method, $parameters);
        $request->cookies->set($this->session->getName(), $this->session->getId());

        return static::$kernel->handle($request);
    }

    /** The CSRF token of the form posting to $action, read from the page as a browser has it. */
    private function token(string $html, string $action): string
    {
        $this->assertMatchesRegularExpression('#action="'.preg_quote($action, '#').'"[^>]*>\s*<input type="hidden" name="_token" value="([^"]+)"#', $html);
        preg_match('#action="'.preg_quote($action, '#').'"[^>]*>\s*<input type="hidden" name="_token" value="([^"]+)"#', $html, $match);

        return html_entity_decode($match[1]);
    }

    private function hours(): OpeningHours
    {
        $hours = static::getContainer()->get(OpeningHours::class);
        $hours->reset();
        $this->em->clear();

        return $hours;
    }

    public function testTheUsualWeekIsShownAndSaved(): void
    {
        $this->signIn(['ROLE_ADMIN']);

        $page = $this->request('GET', '/admin/hours');
        $this->assertSame(200, $page->getStatusCode());
        $this->assertSame('overlay', $page->headers->get('X-Transparent-Nest'));
        $html = (string) $page->getContent();
        $this->assertStringContainsString('name="week[1][0][open]"', $html);
        $this->assertStringContainsString('name="week[7][2][close]"', $html);

        $response = $this->request('POST', '/admin/hours/week', [
            '_token' => $this->token($html, '/admin/hours/week'),
            'week' => [
                3 => [['open' => '16:30', 'close' => '19:00'], ['open' => '09:00', 'close' => '13:00'], ['open' => '', 'close' => '']],
                6 => [['open' => '09:00', 'close' => '18:00']],
            ],
        ]);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/admin/hours', (string) $response->headers->get('Location'));

        $week = $this->hours()->week();
        $this->assertSame([['09:00', '13:00'], ['16:30', '19:00']], $week[3], 'the slots of a day in order');
        $this->assertSame([['09:00', '18:00']], $week[6]);
        $this->assertSame([], $week[1]);

        // Shown back in the page.
        $html = (string) $this->request('GET', '/admin/hours')->getContent();
        $this->assertStringContainsString('name="week[3][1][open]" value="16:30"', $html);
    }

    public function testHoursThatMakeNoSenseAreRefused(): void
    {
        $this->signIn(['ROLE_ADMIN']);
        $html = (string) $this->request('GET', '/admin/hours')->getContent();

        $response = $this->request('POST', '/admin/hours/week', [
            '_token' => $this->token($html, '/admin/hours/week'),
            'week' => [3 => [['open' => '13:00', 'close' => '09:00']]],
        ]);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame([], $this->em->getRepository(WeekDayHours::class)->findAll(), 'nothing saved');
    }

    public function testASpecialDayIsAddedShownAndDeleted(): void
    {
        $this->signIn(['ROLE_ADMIN']);
        $html = (string) $this->request('GET', '/admin/hours')->getContent();
        $from = (new \DateTimeImmutable('+10 days'))->format('Y-m-d');
        $until = (new \DateTimeImmutable('+12 days'))->format('Y-m-d');

        $response = $this->request('POST', '/admin/hours/special', [
            '_token' => $this->token($html, '/admin/hours/special'),
            'from' => $from, 'until' => $until, 'reason' => 'Congés', 'mode' => 'closed',
        ]);
        $this->assertSame(302, $response->getStatusCode());

        $response = $this->request('POST', '/admin/hours/special', [
            '_token' => $this->token($html, '/admin/hours/special'),
            'from' => (new \DateTimeImmutable('+20 days'))->format('Y-m-d'), 'reason' => 'Salon', 'mode' => 'hours',
            'hours' => [['open' => '10:00', 'close' => '14:00'], ['open' => '', 'close' => '']],
        ]);
        $this->assertSame(302, $response->getStatusCode());

        $hours = $this->hours();
        $this->assertCount(2, $hours->specialDays());
        $this->assertSame([], $hours->hoursOn(new \DateTimeImmutable('+11 days')));
        $this->assertSame([['10:00', '14:00']], $hours->hoursOn(new \DateTimeImmutable('+20 days')));

        $html = (string) $this->request('GET', '/admin/hours')->getContent();
        $this->assertStringContainsString('Congés', $html);
        $this->assertStringContainsString('10:00 – 14:00', $html);

        $closed = $this->em->getRepository(SpecialDay::class)->findOneBy(['reason' => 'Congés']);
        $action = '/admin/hours/special/'.$closed->getId().'/delete';
        $this->assertSame(302, $this->request('POST', $action, ['_token' => $this->token($html, $action)])->getStatusCode());
        $this->assertCount(1, $this->hours()->specialDays());
    }

    public function testOnePlacesOwnWeek(): void
    {
        $this->signIn(['ROLE_ADMIN']);
        $html = (string) $this->request('GET', '/admin/hours', ['scope' => 'store:12'])->getContent();
        $this->assertStringContainsString('name="scope" value="store:12"', $html);

        $response = $this->request('POST', '/admin/hours/week', [
            '_token' => $this->token($html, '/admin/hours/week'), 'scope' => 'store:12',
            'week' => [1 => [['open' => '08:00', 'close' => '12:00']]],
        ]);
        $this->assertMatchesRegularExpression('#/admin/hours/?\\?scope=store(:|%3A)12$#', (string) $response->headers->get('Location'), 'back to the place\'s screen');

        $hours = $this->hours();
        $this->assertSame([['08:00', '12:00']], $hours->for('store:12')->week()[1]);
        $this->assertSame([], $hours->week()[1], 'the site\'s week is untouched');
    }

    public function testOnlyAdministratorsAndOnlyWithTheFormsToken(): void
    {
        $this->signIn(['ROLE_ADMIN']);
        $this->assertSame(403, $this->request('POST', '/admin/hours/week', ['_token' => 'nope', 'week' => []])->getStatusCode());

        $this->signIn(['ROLE_USER']);
        $this->assertSame(403, $this->request('GET', '/admin/hours')->getStatusCode());
    }
}
