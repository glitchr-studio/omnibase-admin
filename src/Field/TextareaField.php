<?php

namespace Base\Admin\Field;

use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Contracts\Translation\TranslatableInterface;

class TextareaField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_MAX_LENGTH = 'maxLength';
    public const OPTION_NUM_OF_ROWS = 'numOfRows';

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(TextareaType::class)
            ->addCssClass('field-textarea')
            ->setDefaultColumns('col-md-8 col-xxl-6')
            ->setCustomOption(self::OPTION_MAX_LENGTH, null)
            ->setCustomOption(self::OPTION_NUM_OF_ROWS, null);
    }

    public function setMaxLength(int $length): self
    {
        $this->setCustomOption(self::OPTION_MAX_LENGTH, $length);
        return $this;
    }

    public function setNumOfRows(int $rows): self
    {
        $this->setCustomOption(self::OPTION_NUM_OF_ROWS, $rows);
        $this->setFormTypeOption('attr.rows', $rows);
        return $this;
    }
}
