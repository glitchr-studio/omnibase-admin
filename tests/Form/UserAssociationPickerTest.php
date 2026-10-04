<?php

namespace Tests\Base\Admin\Form;

use Base\Admin\Form\FieldFormBuilder;
use Base\Entity\User\PushSubscription;
use Base\Field\AssociationField;
use Base\Field\Type\AssociationType;
use Base\Field\Type\SelectType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * An AssociationField on a user account is a picker, not the account's whole
 * form embedded in the record: that form failed on `roles` ("No choices, or
 * autocomplete option, could be guessed..."), and a related account is chosen
 * from a CRUD, never edited there.
 *
 * Driven on omnibase's PushSubscription (its `user` is a ManyToOne to the
 * application's User), in a host application's kernel.
 */
class UserAssociationPickerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel') || !class_exists('App\\Entity\\User')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }

        self::bootKernel();
        $request = Request::create('/admin/somewhere');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
    }

    private function builder(): FieldFormBuilder
    {
        return new FieldFormBuilder(static::getContainer()->get('form.factory'), static::getContainer()->get('doctrine'));
    }

    public function testAUserAssociationIsAPicker(): void
    {
        $form = $this->builder()->createForm(new PushSubscription(), [AssociationField::new('user')->setColumns(6)]);

        $field = $form->get('user');
        $this->assertInstanceOf(SelectType::class, $field->getConfig()->getType()->getInnerType());
        $this->assertTrue(is_a($field->getConfig()->getOption('class'), 'App\\Entity\\User', true));

        // It renders: a select fed by the autocompletion, and none of the account's own fields.
        $view = $form->createView();
        $html = static::getContainer()->get('twig')->createTemplate('{{ form_row(field) }}')->render(['field' => $view['user']]);
        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('autocomplete', $html);
        $this->assertStringNotContainsString('[roles]', $html);
        $this->assertStringNotContainsString('[plainPassword]', $html);
        $this->assertSame('col-md-6', $view['user']->vars['row_attr']['data-columns'] ?? null, 'the generic settings are kept');
    }

    public function testTheChosenAccountIsSetOnTheRecord(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $user = new \App\Entity\User();
        $name = 'picker'.bin2hex(random_bytes(4));
        if (method_exists($user, 'setUsername')) {
            $user->setUsername($name);
        }
        $user->setEmail($name.'@example.org');
        $user->setPlainPassword('test-'.$name);
        $em->persist($user);
        $em->flush();

        try {
            $subscription = new PushSubscription();
            $form = $this->builder()->createForm($subscription, [AssociationField::new('user')], formOptions: ['csrf_protection' => false]);
            $form->submit(['user' => ['choice' => (string) $user->getId()]]);

            $this->assertTrue($form->isSynchronized(), (string) $form->getErrors(true));
            $this->assertSame($user->getId(), $subscription->getUser()?->getId());
        } finally {
            $em->remove($em->find($user::class, $user->getId()));
            $em->flush();
        }
    }

    public function testNamedFieldsKeepTheEmbeddedForm(): void
    {
        $form = $this->builder()->createFormBuilder(new PushSubscription(), [
            AssociationField::new('user')->setFields(['email' => []]),
        ])->get('user');

        $this->assertInstanceOf(AssociationType::class, $form->getType()->getInnerType());
    }

    public function testWithoutDoctrineNothingChanges(): void
    {
        $builder = new FieldFormBuilder(static::getContainer()->get('form.factory'));
        $form = $builder->createFormBuilder(new PushSubscription(), [AssociationField::new('user')])->get('user');

        $this->assertInstanceOf(AssociationType::class, $form->getType()->getInnerType());
    }
}
