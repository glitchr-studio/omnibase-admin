<?php

namespace Base\Admin\Field;

use Base\Field\Type\IconType;
use Base\Service\Model\IconProvider\IconAdapterInterface;
use Base\Admin\Config\Option\TextAlign;
use Symfony\Contracts\Translation\TranslatableInterface;

class IconField extends SelectField implements FieldInterface
{
    public const OPTION_TARGET_FIELD_NAME = 'targetFieldName';

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/icon')
            ->setFormType(IconType::class)
            ->setTextAlign(TextAlign::CENTER);
    }

    /**
     * @param string $fieldName
     * @return $this
     */
    public function setTargetColor(string $fieldName)
    {
        $this->setCustomOption(self::OPTION_TARGET_FIELD_NAME, $fieldName);
        return $this;
    }

    /**
     * @param IconAdapterInterface|string $objectOrClass
     * @return $this
     */
    public function setAdapter(IconAdapterInterface|string $objectOrClass)
    {
        $this->setFormTypeOption("adapter", is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass);
        return $this;
    }
}
