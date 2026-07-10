<?php

namespace Base\Admin\Field;

use Base\Field\Type\ImageType;
use Base\Admin\Config\Option\TextAlign;
use Symfony\Contracts\Translation\TranslatableInterface;

class CropperField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_SHOWFIRST = 'showFirst';
    public const OPTION_RENDER_FORMAT = 'renderFormat';

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/cropper')
            ->setFormType(ImageType::class)
            ->addCssClass('field-cropper')
            ->addCssClass('cropper-widget')
            ->setTextAlign(TextAlign::CENTER)
            ->setFormTypeOptionIfNotSet('data_class', null)
            ->setCustomOption(self::OPTION_SHOWFIRST, true)
            ->setFormTypeOption('cropper', [])
            ->setCustomOption(self::OPTION_RENDER_FORMAT, 'count');
    }
}
