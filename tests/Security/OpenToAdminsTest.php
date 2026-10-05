<?php

namespace Tests\Base\Admin\Security;

use Base\Admin\Attribute\OpenToAdmins;
use Base\Admin\Config\Action;
use Base\Admin\Config\Actions;
use Base\Admin\Config\Crud;
use Base\Admin\Controller\Crud\RedirectionCrudController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * #[OpenToAdmins] on a CRUD controller: the site's administrator (ROLE_ADMIN)
 * creates, edits and deletes there - the back office keeps those to the
 * super-admin by default, and an administrator met a 403 on "new" for the
 * site's own contents. The default is asserted too: it does not move.
 */
class OpenToAdminsTest extends KernelTestCase
{
    private const WRITES = [Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE, Action::SAVE_AND_RETURN, Action::SAVE_AND_CONTINUE, Action::SAVE_AND_ADD_ANOTHER];

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }

        self::bootKernel();
        $request = Request::create('/admin/redirections');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
    }

    private function signInWith(string ...$roles): void
    {
        static::getContainer()->get('security.token_storage')->setToken(
            new UsernamePasswordToken(new InMemoryUser('someone', null, $roles), 'main', $roles)
        );
    }

    /**
     * A CRUD of the given class, built as the container builds the
     * redirections' one (the test classes below extend it and are no
     * services of their own).
     *
     * @param class-string<RedirectionCrudController> $class
     */
    private function crud(string $class): RedirectionCrudController
    {
        $service = static::getContainer()->get(RedirectionCrudController::class);
        $crud = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        for ($reflection = new \ReflectionClass($service); $reflection; $reflection = $reflection->getParentClass() ?: null) {
            foreach ($reflection->getProperties() as $property) {
                if (!$property->isStatic() && $property->isInitialized($service)) {
                    $property->setValue($crud, $property->getValue($service));
                }
            }
        }
        // Nothing the service already worked out for itself.
        (new \ReflectionProperty($crud, 'actionsConfig'))->setValue($crud, null);
        (new \ReflectionProperty($crud, 'actionPermissions'))->setValue($crud, []);

        return $crud;
    }

    private function actions(RedirectionCrudController $crud): Actions
    {
        return (new \ReflectionMethod($crud, 'getActionsConfig'))->invoke($crud);
    }

    private function mayRun(RedirectionCrudController $crud, string $action): bool
    {
        $page = Action::NEW === $action ? Crud::PAGE_NEW : (Action::EDIT === $action ? Crud::PAGE_EDIT : Crud::PAGE_INDEX);
        $config = (new \ReflectionMethod($crud, 'getCrudConfig'))->invoke($crud, $page, $action);

        try {
            (new \ReflectionMethod($crud, 'denyAccessUnlessGrantedToRun'))->invoke($crud, $config);
        } catch (AccessDeniedException) {
            return false;
        }

        return true;
    }

    public function testByDefaultWritingIsTheSuperAdmins(): void
    {
        $this->signInWith('ROLE_ADMIN');
        $crud = $this->crud(RedirectionCrudController::class);

        $this->assertNull(OpenToAdmins::of(RedirectionCrudController::class));
        $this->assertArrayNotHasKey(Action::NEW, $this->actions($crud)->getAll(Actions::PAGE_INDEX), 'no "new" button for an administrator');
        foreach ([Action::NEW, Action::EDIT, Action::DELETE] as $action) {
            $this->assertFalse($this->mayRun($crud, $action), $action);
            $this->assertSame('ROLE_SUPERADMIN', $this->actions($crud)->getEffectivePermission($action), $action);
        }
        $this->assertTrue($this->mayRun($crud, Action::INDEX), 'the list is read');
    }

    public function testAnOpenedCrudIsWrittenByTheAdministrator(): void
    {
        $this->signInWith('ROLE_ADMIN');
        $crud = $this->crud(OpenedCrudController::class);

        foreach (self::WRITES as $action) {
            $this->assertSame('ROLE_ADMIN', $this->actions($crud)->getEffectivePermission($action), $action);
        }
        $this->assertArrayHasKey(Action::NEW, $this->actions($crud)->getAll(Actions::PAGE_INDEX), 'the "new" button is drawn');
        $this->assertArrayHasKey(Action::EDIT, $this->actions($crud)->getAll(Actions::PAGE_INDEX));
        $this->assertArrayHasKey(Action::DELETE, $this->actions($crud)->getAll(Actions::PAGE_INDEX));
        foreach ([Action::NEW, Action::EDIT, Action::DELETE] as $action) {
            $this->assertTrue($this->mayRun($crud, $action), $action);
        }
    }

    public function testAnOpenedCrudIsNotOpenToEveryone(): void
    {
        $this->signInWith('ROLE_USER');
        $crud = $this->crud(OpenedCrudController::class);

        $this->assertArrayNotHasKey(Action::NEW, $this->actions($crud)->getAll(Actions::PAGE_INDEX));
        $this->assertFalse($this->mayRun($crud, Action::NEW));
        $this->assertFalse($this->mayRun($crud, Action::DELETE));
    }

    public function testACrudThatExtendsAnOpenedOneIsOpened(): void
    {
        $this->signInWith('ROLE_ADMIN');

        $this->assertNotNull(OpenToAdmins::of(ChildOfOpenedCrudController::class));
        $this->assertTrue($this->mayRun($this->crud(ChildOfOpenedCrudController::class), Action::NEW));
    }

    public function testAnotherRoleAndTheCrudsOwnActions(): void
    {
        $crud = $this->crud(OpenedToStaffCrudController::class);

        $this->signInWith('ROLE_ADMIN');
        $this->assertFalse($this->mayRun($crud, Action::NEW), 'ROLE_STAFF was asked, not ROLE_ADMIN');

        $this->signInWith('ROLE_STAFF');
        $crud = $this->crud(OpenedToStaffCrudController::class);
        $this->assertTrue($this->mayRun($crud, Action::NEW));
        $this->assertSame('ROLE_STAFF', $this->actions($crud)->getEffectivePermission('publish'), 'its own action is the staff\'s too');
        $this->assertNull($this->actions($crud)->getEffectivePermission('somethingElse'), 'an action it did not name is as before');
    }

    public function testAPermissionTheCrudSetsItselfIsKept(): void
    {
        $this->signInWith('ROLE_ADMIN');
        $crud = $this->crud(OpenedButDeleteCrudController::class);

        $this->assertTrue($this->mayRun($crud, Action::EDIT));
        $this->assertFalse($this->mayRun($crud, Action::DELETE), 'configureActions() kept delete to the super-admin');
        $this->assertSame('ROLE_SUPERADMIN', $this->actions($crud)->getEffectivePermission(Action::DELETE));
        $this->assertNull($this->actions($crud)->getEffectivePermission(Action::BATCH_DELETE), 'allowAnyoneTo() is kept as well');
    }

    public function testOpenToOnItsOwn(): void
    {
        $actions = Actions::new()->addDefaults()->setPermission(Action::NEW, 'ROLE_EDITOR')->openTo('ROLE_ADMIN', 'archive');

        $this->assertSame('ROLE_EDITOR', $actions->getEffectivePermission(Action::NEW));
        $this->assertSame('ROLE_ADMIN', $actions->getEffectivePermission(Action::EDIT));
        $this->assertSame('ROLE_ADMIN', $actions->getEffectivePermission('archive'));
        $this->assertSame('ROLE_SUPERADMIN', Actions::new()->addDefaults()->getEffectivePermission(Action::EDIT), 'another CRUD is not touched');
    }
}

#[OpenToAdmins]
class OpenedCrudController extends RedirectionCrudController
{
}

class ChildOfOpenedCrudController extends OpenedCrudController
{
}

#[OpenToAdmins(role: 'ROLE_STAFF', actions: ['publish'])]
class OpenedToStaffCrudController extends RedirectionCrudController
{
}

#[OpenToAdmins]
class OpenedButDeleteCrudController extends RedirectionCrudController
{
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->setPermission(Action::DELETE, 'ROLE_SUPERADMIN')
            ->allowAnyoneTo(Action::BATCH_DELETE);
    }
}
