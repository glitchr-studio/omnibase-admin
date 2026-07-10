<?php

namespace Base\Admin\Field;

use Base\Field\Type\CountryType;
use Symfony\Contracts\Translation\TranslatableInterface;

class CountryField extends SelectField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/country')
            ->setFormType(CountryType::class)
            ->setChoices(CountryType::getChoices())
            ->addCssClass('field-country');
    }
}
