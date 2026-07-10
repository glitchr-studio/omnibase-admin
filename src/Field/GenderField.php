<?php

namespace Base\Admin\Field;

use Base\Enum\Gender;
use Base\Field\Type\GenderType;
use Symfony\Contracts\Translation\TranslatableInterface;

class GenderField extends SelectField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/select')
            ->setFormType(GenderType::class)
            ->setCustomOption(SelectField::OPTION_CLASS, Gender::class);
    }
}
