<?php

namespace Base\Admin\Field;

use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Contracts\Translation\TranslatableInterface;

class DateField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(DateType::class)
            ->setFormTypeOption('widget', 'single_text')
            ->addCssClass('field-date')
            ->setDefaultColumns('col-md-4 col-xxl-3');
    }
}
