<?php

namespace Base\Admin\Field;


use Base\Field\Type\CollectionType;

class CollectionField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_ALLOW_ADD = 'allow_add';
    public const OPTION_ALLOW_OBJECT = 'allow_object';
    public const OPTION_ALLOW_DELETE = 'allow_delete';
    public const OPTION_ENTRY_IS_COMPLEX = 'entryIsComplex';
    public const OPTION_ENTRY_TYPE = 'entry_type';
    public const OPTION_ENTRY_OPTIONS = 'entry_options';
    public const OPTION_ENTRY_LABEL = 'entry_label';
    public const OPTION_RENDER_EXPANDED = 'renderExpanded';

    public const OPTION_LENGTH = 'length';

    /**
     * @param string|false|null $label
     */
    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/collection')
            ->setFormType(CollectionType::class)
            ->setCustomOption(self::OPTION_ALLOW_ADD, true)
            ->setCustomOption(self::OPTION_ALLOW_DELETE, true)
            ->setCustomOption(self::OPTION_ENTRY_IS_COMPLEX, null)
            ->setCustomOption(self::OPTION_ENTRY_TYPE, null)
            ->setCustomOption(self::OPTION_RENDER_EXPANDED, false);
    }

    public function allowAdd(bool $allow = true): self
    {
        $this->setFormTypeOption(self::OPTION_ALLOW_ADD, $allow);

        return $this;
    }

    public function allowObject(bool $allow = true): self
    {
        $this->setFormTypeOption(self::OPTION_ALLOW_OBJECT, $allow);

        return $this;
    }

    public function allowDelete(bool $allow = true): self
    {
        $this->setFormTypeOption(self::OPTION_ALLOW_DELETE, $allow);

        return $this;
    }

    public function setEntryType(string $entryType): self
    {
        $this->setFormTypeOption(self::OPTION_ENTRY_TYPE, $entryType);

        return $this;
    }

    public function setEntryOptions(array $entryOptions): self
    {
        $this->setFormTypeOption(self::OPTION_ENTRY_OPTIONS, $entryOptions);

        return $this;
    }

    /**
     * @param $entryLabel
     * @return $this
     */
    public function setEntryLabel($entryLabel): self
    {
        $this->setFormTypeOption(self::OPTION_ENTRY_LABEL, $entryLabel);

        return $this;
    }

    public function renderExpanded(bool $renderExpanded = true): self
    {
        $this->setFormTypeOption(self::OPTION_RENDER_EXPANDED, $renderExpanded);

        return $this;
    }

    public function setLength(int $length): self
    {
        $this->setFormTypeOption(self::OPTION_LENGTH, max(0, $length));
        return $this;
    }

    /**
     * @param $embed
     * @return $this
     */
    public function showEmbedded($embed = true): self
    {
        $this->setFormTypeOption("group", $embed);
        return $this;
    }

    /**
     * @param $embed
     * @return $this
     */
    public function showEmbeddedRow($embed = true): self
    {
        $this->setFormTypeOption("row_group", $embed);
        return $this;
    }

    /**
     * @param $collapsed
     * @return $this
     */
    public function showCollapsed($collapsed = true): self
    {
        $this->setFormTypeOption("entry_collapsed", $collapsed);
        return $this;
    }
}
