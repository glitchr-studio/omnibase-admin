<?php

namespace Tests\Base\Admin\Field;

use Base\Admin\Field\FieldDescriptor;
use Base\Admin\Field\TextField;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class FieldTraitTest extends TestCase
{
    public function testNewWiresDescriptor(): void
    {
        $field = TextField::new('title', 'My title');
        $dto = $field->getAsDto();

        $this->assertSame(TextField::class, $dto->getFieldFqcn());
        $this->assertSame('title', $dto->getProperty());
        $this->assertSame('My title', $dto->getLabel());
        $this->assertSame(TextType::class, $dto->getFormType());
        $this->assertSame('field-text', $dto->getCssClass());
        $this->assertFalse($dto->getCustomOption(TextField::OPTION_RENDER_AS_HTML));
    }

    public function testVisibilityToggles(): void
    {
        $dto = TextField::new('title')->hideOnIndex()->getAsDto();
        $this->assertFalse($dto->isDisplayedOn(FieldDescriptor::PAGE_INDEX));
        $this->assertTrue($dto->isDisplayedOn(FieldDescriptor::PAGE_DETAIL));
        $this->assertTrue($dto->isDisplayedOn(FieldDescriptor::PAGE_EDIT));

        $dto = TextField::new('title')->onlyOnForms()->getAsDto();
        $this->assertFalse($dto->isDisplayedOn(FieldDescriptor::PAGE_INDEX));
        $this->assertFalse($dto->isDisplayedOn(FieldDescriptor::PAGE_DETAIL));
        $this->assertTrue($dto->isDisplayedOn(FieldDescriptor::PAGE_EDIT));
        $this->assertTrue($dto->isDisplayedOn(FieldDescriptor::PAGE_NEW));

        $dto = TextField::new('title')->hideOnForm()->showOnIndex()->getAsDto();
        $this->assertTrue($dto->isDisplayedOn(FieldDescriptor::PAGE_INDEX));
        $this->assertFalse($dto->isDisplayedOn(FieldDescriptor::PAGE_NEW));
    }

    public function testColumnsIntBecomesResponsiveClass(): void
    {
        $dto = TextField::new('title')->setColumns(6)->getAsDto();
        $this->assertSame('col-md-6', $dto->getColumns());

        $dto = TextField::new('title')->setColumns('col-md-6 col-xxl-3')->getAsDto();
        $this->assertSame('col-md-6 col-xxl-3', $dto->getColumns());
    }

    public function testFormTypeOptionPathSyntax(): void
    {
        $dto = TextField::new('title')
            ->setFormTypeOption('attr.autofocus', true)
            ->setFormTypeOptionIfNotSet('attr.autofocus', false)
            ->setFormTypeOptionIfNotSet('attr.placeholder', 'x')
            ->getAsDto();

        $this->assertSame(['attr' => ['autofocus' => true, 'placeholder' => 'x']], $dto->getFormTypeOptions());
        $this->assertTrue($dto->getFormTypeOption('attr.autofocus'));
    }

    public function testCloneDetachesDescriptor(): void
    {
        $field = TextField::new('title');
        $copy = clone $field;
        $copy->setLabel('changed');

        $this->assertNull($field->getAsDto()->getLabel());
        $this->assertSame('changed', $copy->getAsDto()->getLabel());
    }
}
