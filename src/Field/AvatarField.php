<?php

namespace Base\Admin\Field;

use Base\Field\Type\AvatarType;
use Base\Admin\Config\Option\TextAlign;
use Symfony\Contracts\Translation\TranslatableInterface;

class AvatarField extends ImageField implements FieldInterface
{
    public const OPTION_RENDER_FORMAT = 'renderFormat';

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/file')
            ->setFormType(AvatarType::class)
            ->addCssClass('field-file')
            ->addCssClass('file-widget')
            ->setTextAlign(TextAlign::CENTER)
            ->setColumns(2)
            ->setFormTypeOptionIfNotSet('data_class', null)
            ->setCustomOption(self::OPTION_RENDER_FORMAT, 'avatar');
    }
}
