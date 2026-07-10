<?php

namespace Base\Admin\Field;

use Base\Field\Type\RouteType;
use Base\Admin\Config\Option\TextAlign;
use Symfony\Contracts\Translation\TranslatableInterface;

class RouteField extends SelectField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/select')
            ->setFormType(RouteType::class)
            ->setCustomOption(self::OPTION_SHOW, self::SHOW_ICON_ONLY)
            ->setCustomOption(self::OPTION_SHOW_FIRST, self::SHOW_ALL)
            ->setCustomOption(self::OPTION_DISPLAY_LIMIT, 2)
            ->setTextAlign(TextAlign::RIGHT);
    }
}
