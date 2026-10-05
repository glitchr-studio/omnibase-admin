<?php

namespace Tests\Base\Admin\Controller;

use Base\Admin\Config\Action;
use Base\Admin\Controller\Crud\ComplaintCrudController;
use Base\Entity\User\Complaint;
use Base\Field\FieldValueResolver;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * A record whose readable identifier is computed - an accessor with no
 * column behind it, as the getSlug() a scholar's Publication makes of its
 * title - is linked by its id: /admin/.../2/edit redirected to the computed
 * identifier, an address the lookup (findEntity(), which queries columns)
 * answered 404 to. Here a complaint, identified by getStatusLabel(): an
 * accessor, not a column.
 */
class ComputedIdentifierTest extends KernelTestCase
{
    private ?Complaint $complaint = null;

    /** @var string[] */
    private array $identifierFields;

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }

        self::bootKernel();
        $request = Request::create('/admin/complaints');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);

        // The back office's own resolver, as the container wires it, told
        // to identify a record by 'statusLabel' (admin.url_identifier.fields).
        $fields = new \ReflectionProperty(FieldValueResolver::class, 'identifierFields');
        $this->identifierFields = $fields->getValue($this->resolver());
        $fields->setValue($this->resolver(), ['statusLabel']);

        $this->complaint = new Complaint(null, 'A word out of place.', 'test');
        $this->complaint->setComplainantName('A visitor');
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->persist($this->complaint);
        $em->flush();
    }

    protected function tearDown(): void
    {
        if ($this->complaint) {
            (new \ReflectionProperty(FieldValueResolver::class, 'identifierFields'))->setValue($this->resolver(), $this->identifierFields);

            $em = static::getContainer()->get('doctrine')->getManager();
            if ($managed = $em->find(Complaint::class, $this->complaint->getId())) {
                $em->remove($managed);
                $em->flush();
            }
            $this->complaint = null;
        }

        parent::tearDown();
    }

    private function resolver(): FieldValueResolver
    {
        return static::getContainer()->get(FieldValueResolver::class);
    }

    private function call(string $method, mixed ...$arguments): mixed
    {
        $controller = static::getContainer()->get(ComplaintCrudController::class);

        return (new \ReflectionMethod($controller, $method))->invoke($controller, ...$arguments);
    }

    public function testTheResolverKnowsWhichFieldsAreColumns(): void
    {
        $doctrine = new \ReflectionProperty(FieldValueResolver::class, 'doctrine');

        $this->assertNotNull($doctrine->getValue($this->resolver()), 'AdminExtension gives the resolver Doctrine');
    }

    public function testARecordWithAComputedIdentifierIsLinkedByItsId(): void
    {
        $this->assertNotSame('', $this->complaint->getStatusLabel(), 'the accessor answers');
        $this->assertFalse(static::getContainer()->get('doctrine')->getManager()->getClassMetadata(Complaint::class)->hasField('statusLabel'), 'and is not a column');

        $this->assertSame($this->complaint->getId(), $this->resolver()->entityIdentifier($this->complaint));
    }

    public function testItsEditAddressIsNotRedirectedAway(): void
    {
        $id = (string) $this->complaint->getId();

        foreach ([Action::EDIT, Action::DETAIL] as $action) {
            $this->assertNull(
                $this->call('canonicalIdentifierRedirect', Request::create('/admin/complaints/'.$id.'/'.$action), $this->complaint, $id, $action),
                'the address by id is the canonical one'
            );
        }
    }

    public function testTheAddressTheBackOfficeLinksWithResolves(): void
    {
        $linked = (string) $this->resolver()->entityIdentifier($this->complaint);

        $found = $this->call('findEntity', $linked);
        $this->assertSame($this->complaint->getId(), $found->getId());

        $url = static::getContainer()->get(\Base\Admin\Router\AdminUrlGenerator::class)
            ->setController(ComplaintCrudController::class)->setAction(Action::EDIT)->setEntityId($linked)->generateUrl();
        $this->assertStringEndsWith('/'.$this->complaint->getId().'/edit', $url);
    }

    public function testAStoredIdentifierIsStillPreferred(): void
    {
        // 'source' is a column of the complaint: a readable identifier that can be looked up again.
        (new \ReflectionProperty(FieldValueResolver::class, 'identifierFields'))->setValue($this->resolver(), ['statusLabel', 'source']);

        $this->assertSame('test', $this->resolver()->entityIdentifier($this->complaint));
        $this->assertSame($this->complaint->getId(), $this->call('findEntity', 'test')->getId());
    }
}
