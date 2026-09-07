<?php

namespace Tests\Base\Admin\Form;

use Base\Field\FieldDescriptor;
use Base\Field\TextField;
use Base\Admin\Form\FieldFormBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;

class Post
{
    public ?string $title = null;
    public ?string $summary = null;
    public ?string $internal = null;
}

class FieldFormBuilderTest extends TestCase
{
    private function builder(): FieldFormBuilder
    {
        return new FieldFormBuilder(Forms::createFormFactory());
    }

    public function testBuildsFormFromFields(): void
    {
        $post = new Post();
        $form = $this->builder()->createForm($post, [
            TextField::new('title')->setRequired(true),
            TextField::new('summary')->setFormTypeOption('attr.placeholder', 'Short summary'),
        ]);

        $this->assertTrue($form->has('title'));
        $this->assertTrue($form->has('summary'));
        $this->assertInstanceOf(TextType::class, $form->get('title')->getConfig()->getType()->getInnerType());
        $this->assertTrue($form->get('title')->getConfig()->getRequired());
        $this->assertSame('Short summary', $form->get('summary')->getConfig()->getOption('attr')['placeholder']);
    }

    public function testSubmitMapsDataBack(): void
    {
        $post = new Post();
        $form = $this->builder()->createForm($post, [
            TextField::new('title'),
            TextField::new('summary'),
        ]);

        $form->submit(['title' => 'Hello', 'summary' => 'World']);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('Hello', $post->title);
        $this->assertSame('World', $post->summary);
    }

    public function testPageVisibilityFiltersFields(): void
    {
        $form = $this->builder()->createForm(new Post(), [
            TextField::new('title'),
            TextField::new('internal')->onlyOnIndex(),
            TextField::new('summary')->hideWhenCreating(),
        ], FieldDescriptor::PAGE_NEW);

        $this->assertTrue($form->has('title'));
        $this->assertFalse($form->has('internal'));
        $this->assertFalse($form->has('summary'));

        $form = $this->builder()->createForm(new Post(), [
            TextField::new('summary')->hideWhenCreating(),
        ], FieldDescriptor::PAGE_EDIT);

        $this->assertTrue($form->has('summary'));
    }

    public function testVirtualFieldIsUnmapped(): void
    {
        $form = $this->builder()->createForm(new Post(), [
            TextField::new('title')->setVirtual(true),
        ]);

        $this->assertFalse($form->get('title')->getConfig()->getMapped());
    }

    public function testExplicitFormTypeOptionWins(): void
    {
        $form = $this->builder()->createForm(new Post(), [
            TextField::new('title')->setRequired(true)->setFormTypeOption('required', false),
        ]);

        $this->assertFalse($form->get('title')->getConfig()->getRequired());
    }
}
