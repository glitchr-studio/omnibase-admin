<?php

namespace Base\Admin\Field;

use App\Enum\UserRole;
use Base\Field\Type\RoleType;
use Base\Admin\Config\Option\TextAlign;
use Symfony\Contracts\Translation\TranslatableInterface;

class RoleField extends SelectField
{
    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/select')
            ->setFormType(RoleType::class)
            ->setCustomOption(SelectField::OPTION_CLASS, UserRole::class)
            ->setCustomOption(self::OPTION_SHOW, self::SHOW_ICON_ONLY)
            ->setCustomOption(self::OPTION_SHOW_FIRST, self::SHOW_ALL)
            ->setCustomOption(self::OPTION_DISPLAY_LIMIT, 2)
            ->setTextAlign(TextAlign::RIGHT);
    }
}
