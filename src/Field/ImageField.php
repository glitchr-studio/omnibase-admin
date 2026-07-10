<?php

namespace Base\Admin\Field;

use Base\Admin\Config\Option\TextAlign;

use Base\Field\Type\ImageType;
use Symfony\Contracts\Translation\TranslatableInterface;

class ImageField extends FileField implements FieldInterface
{
    public const OPTION_RENDER_FORMAT = "renderFormat";

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/file')
            ->setFormType(ImageType::class)
            ->addCssClass('field-file')
            ->addCssClass('file-widget')
            ->setTextAlign(TextAlign::CENTER)
            ->setFormTypeOptionIfNotSet("data_class", null)
            ->setCustomOption(self::OPTION_SHOWFIRST, true)
            ->setCustomOption(self::OPTION_RENDER_FORMAT, "image");
    }

    /**
     * @param array|bool|null $cropper
     * @return $this
     */
    public function setCropper(null|array|bool $cropper = true)
    {
        if (is_bool($cropper)) {
            $cropper = $cropper ? [] : null;
        }
        $this->setFormTypeOption("cropper", $cropper);
        return $this;
    }
}
