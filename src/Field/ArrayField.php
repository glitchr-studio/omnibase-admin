<?php

namespace Base\Admin\Field;

use Base\Field\Type\ArrayType;

class ArrayField extends CollectionField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_ASSOCIATIVE = 'associative';
    public const OPTION_LENGTH = 'length';
    public const OPTION_PATTERN_FIELD_NAME = 'pattern';
    public const OPTION_PLACEHOLDER = 'placeholder';

    /**
     * @param string|false|null $label
     */
    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/collection')
            ->setFormType(ArrayType::class)
            ->setCustomOption(self::OPTION_ALLOW_ADD, true)
            ->setCustomOption(self::OPTION_ALLOW_DELETE, true)
            ->setCustomOption(self::OPTION_ENTRY_IS_COMPLEX, null)
            ->setCustomOption(self::OPTION_ENTRY_TYPE, null)
            ->setCustomOption(self::OPTION_RENDER_EXPANDED, false)
            ->setFormTypeOption(self::OPTION_PLACEHOLDER, null);
    }

    public function useAssociativeKeys(bool $use_associative = true): self
    {
        $this->setFormTypeOption(self::OPTION_ASSOCIATIVE, $use_associative);

        return $this;
    }

    public function setPlaceholder(array $placeholder): self
    {
        $this->setFormTypeOption(self::OPTION_PLACEHOLDER, $placeholder);

        return $this;
    }

    public function setPatternFieldName(string $fieldName): self
    {
        $this->setFormTypeOption(self::OPTION_PATTERN_FIELD_NAME, $fieldName);

        return $this;
    }

    public function allowAdd(bool $allow = true): self
    {
        $this->setCustomOption(self::OPTION_ALLOW_ADD, $allow);

        return $this;
    }

    public function allowDelete(bool $allow = true): self
    {
        $this->setCustomOption(self::OPTION_ALLOW_DELETE, $allow);

        return $this;
    }

    /**
     * Set this option to TRUE if the collection items are complex form types
     * composed of several form fields (EasyAdmin applies a special rendering to make them look better).
     */
    public function setEntryIsComplex(bool $isComplex): self
    {
        $this->setCustomOption(self::OPTION_ENTRY_IS_COMPLEX, $isComplex);

        return $this;
    }

    public function setEntryType(string $entryType): self
    {
        $this->setCustomOption(self::OPTION_ENTRY_TYPE, $entryType);

        return $this;
    }

    public function setEntryOptions(array $entryOptions): self
    {
        $this->setCustomOption(self::OPTION_ENTRY_OPTIONS, $entryOptions);

        return $this;
    }

    public function renderExpanded(bool $renderExpanded = true): self
    {
        $this->setCustomOption(self::OPTION_RENDER_EXPANDED, $renderExpanded);

        return $this;
    }

    public function setLength(string|int $length): self
    {
        $this->setFormTypeOption(self::OPTION_LENGTH, is_string($length) ? $length : max(0, $length));

        return $this;
    }
}
