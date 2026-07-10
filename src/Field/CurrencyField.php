<?php

namespace Base\Admin\Field;

use Base\Field\Type\CurrencyType;
use Symfony\Contracts\Translation\TranslatableInterface;

class CurrencyField extends SelectField implements FieldInterface
{
    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/currency')
            ->setFormType(CurrencyType::class);
    }
}
