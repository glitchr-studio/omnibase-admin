<?php

namespace Base\Admin\Field;

use Base\Field\Type\ColorPickerType;
use Base\Admin\Config\Option\TextAlign;
use Symfony\Contracts\Translation\TranslatableInterface;

final class ColorPickerField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setTextAlign(TextAlign::CENTER)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/color')
            ->setFormType(ColorPickerType::class);
    }
}
