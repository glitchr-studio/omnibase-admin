<?php

namespace Base\Admin\Field;

use Base\Field\Type\AudioType;
use Base\Admin\Config\Option\TextAlign;
use Symfony\Contracts\Translation\TranslatableInterface;

class AudioField extends FileField implements FieldInterface
{
    public const OPTION_RENDER_FORMAT = 'renderFormat';

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/file')
            ->setFormType(AudioType::class)
            ->addCssClass('field-file')
            ->addCssClass('file-widget')
            ->setTextAlign(TextAlign::CENTER)
            ->setFormTypeOptionIfNotSet('data_class', null)
            ->setCustomOption(self::OPTION_SHOWFIRST, true)
            ->setCustomOption(self::OPTION_RENDER_FORMAT, 'audio');
    }
}
