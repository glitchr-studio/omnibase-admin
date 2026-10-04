<?php

namespace Tests\Base\Admin\Form;

use Base\Admin\Form\FieldFormBuilder;
use Base\Entity\Thread\Comment;
use Base\Field\AssociationField;
use Base\Field\Type\AssociationType;
use Base\Field\Type\SelectType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * An AssociationField to a record that exists on its own (a many-to-one, a
 * many-to-many) is a picker; the related record's own form is embedded for
 * what the record owns (one-to-many, one-to-one), or when it is asked for:
 * ->embed(), ->setFields([...]).
 *
 * Driven on omnibase's Comment: `parent` is a many-to-one to another comment,
 * `replies` its one-to-many side, `state` a PHP enum - the embedded form of a
 * comment put that enum in a text input, and the screen answered 500.
 */
class AssociationPickerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
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

    private function typeOf(AssociationField $field): string
    {
        $name = $field->getAsDto()->getProperty();

        return $this->builder()->createFormBuilder(new Comment(), [$field])->get($name)->getType()->getInnerType()::class;
    }

    public function testAManyToOneIsAPicker(): void
    {
        $form = $this->builder()->createForm(new Comment(), [AssociationField::new('parent')->setColumns(6)->setRequired(false)]);

        $field = $form->get('parent');
        $this->assertInstanceOf(SelectType::class, $field->getConfig()->getType()->getInnerType());
        $this->assertSame(Comment::class, $field->getConfig()->getOption('class'));
        $this->assertFalse($field->getConfig()->getOption('required'), 'the generic settings are kept');

        $view = $form->createView();
        $html = static::getContainer()->get('twig')->createTemplate('{{ form_row(field) }}')->render(['field' => $view['parent']]);
        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('autocomplete', $html);
        $this->assertStringNotContainsString('[content]', $html, 'none of the related record\'s own fields');
        $this->assertStringNotContainsString('[state]', $html);
    }

    public function testTheChosenRecordIsSet(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $parent = (new Comment())->setName('Ada')->setContent('A first comment');
        $em->persist($parent);
        $em->flush();

        try {
            $reply = new Comment();
            $form = $this->builder()->createForm($reply, [AssociationField::new('parent')], formOptions: ['csrf_protection' => false]);
            $form->submit(['parent' => ['choice' => (string) $parent->getId()]]);

            $this->assertTrue($form->get('parent')->isSynchronized(), (string) $form->getErrors(true));
            $this->assertSame($parent->getId(), $reply->getParent()?->getId());
        } finally {
            $em->remove($em->find(Comment::class, $parent->getId()));
            $em->flush();
        }
    }

    public function testWhatTheRecordOwnsKeepsTheEmbeddedForm(): void
    {
        $this->assertSame(AssociationType::class, $this->typeOf(AssociationField::new('replies')));
    }

    public function testTheEmbeddedFormOnRequest(): void
    {
        $this->assertSame(AssociationType::class, $this->typeOf(AssociationField::new('parent')->embed()));
        $this->assertSame(AssociationType::class, $this->typeOf(AssociationField::new('parent')->setFields(['content' => []])));
    }

    public function testThePickerOnRequest(): void
    {
        $this->assertSame(SelectType::class, $this->typeOf(AssociationField::new('replies')->embed(false)));
    }

    public function testTheEmbeddedFormOfARecordThatHoldsAnEnumRenders(): void
    {
        $form = $this->builder()->createForm((new Comment())->setParent(new Comment()), [AssociationField::new('parent')->embed()]);

        $this->assertInstanceOf(SelectType::class, $form->get('parent')->get('state')->getConfig()->getType()->getInnerType());

        $html = static::getContainer()->get('twig')->createTemplate('{{ form_row(field) }}')->render(['field' => $form->createView()['parent']]);
        $this->assertStringContainsString('crud_form[parent][state][choice]', $html);
    }
}
