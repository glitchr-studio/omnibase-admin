<?php

namespace Base\Admin\Field;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Fluent configuration surface shared by every field.
 *
 * Method names intentionally match the ones CRUD controllers already call,
 * so porting a controller does not require rewriting configureFields().
 * All state lives in a single FieldDescriptor (see getAsDto()).
 */
trait FieldTrait
{
    protected FieldDescriptor $dto;

    protected function __construct()
    {
        $this->dto = new FieldDescriptor();
        $this->dto->setFieldFqcn(static::class);
    }

    public function __clone(): void
    {
        $this->dto = clone $this->dto;
    }

    public function setFieldFqcn(string $fieldFqcn): static
    {
        $this->dto->setFieldFqcn($fieldFqcn);
        return $this;
    }

    public function setProperty(string $propertyName): static
    {
        $this->dto->setProperty($propertyName);
        return $this;
    }

    public function setPropertySuffix(string $propertyNameSuffix): static
    {
        $this->dto->setPropertySuffix($propertyNameSuffix);
        return $this;
    }

    public function setLabel(TranslatableInterface|string|bool|null $label): static
    {
        $this->dto->setLabel($label);
        return $this;
    }

    public function setValue(mixed $value): static
    {
        $this->dto->setValue($value);
        return $this;
    }

    public function setFormattedValue(mixed $value): static
    {
        $this->dto->setFormattedValue($value);
        return $this;
    }

    public function formatValue(?callable $callable): static
    {
        $this->dto->setFormatValueCallable($callable);
        return $this;
    }

    public function setVirtual(bool $isVirtual): static
    {
        $this->dto->setVirtual($isVirtual);
        return $this;
    }

    public function setDisabled(bool $disabled = true): static
    {
        $this->dto->setDisabled($disabled);
        return $this;
    }

    public function setRequired(bool $isRequired): static
    {
        $this->dto->setRequired($isRequired);
        return $this;
    }

    public function setEmptyData(mixed $emptyData = null): static
    {
        $this->dto->setEmptyData($emptyData);
        return $this;
    }

    public function setFormType(string $formTypeFqcn): static
    {
        $this->dto->setFormType($formTypeFqcn);
        return $this;
    }

    public function setFormTypeOptions(array $options): static
    {
        foreach ($options as $optionName => $optionValue) {
            $this->dto->setFormTypeOption($optionName, $optionValue);
        }

        return $this;
    }

    public function setFormTypeOption(string $optionName, mixed $optionValue): static
    {
        $this->dto->setFormTypeOption($optionName, $optionValue);
        return $this;
    }

    public function setFormTypeOptionIfNotSet(string $optionName, mixed $optionValue): static
    {
        $this->dto->setFormTypeOptionIfNotSet($optionName, $optionValue);
        return $this;
    }

    public function setHtmlAttribute(string $attributeName, mixed $attributeValue): static
    {
        $this->dto->setHtmlAttribute($attributeName, $attributeValue);
        return $this;
    }

    public function setHtmlAttributes(array $attributes): static
    {
        foreach ($attributes as $attributeName => $attributeValue) {
            $this->dto->setHtmlAttribute($attributeName, $attributeValue);
        }

        return $this;
    }

    public function setSortable(bool $isSortable): static
    {
        $this->dto->setSortable($isSortable);
        return $this;
    }

    public function setPermission(string|Expression $permission): static
    {
        $this->dto->setPermission($permission);
        return $this;
    }

    /**
     * @param string $textAlign one of "left", "center", "right"
     */
    public function setTextAlign(string $textAlign): static
    {
        $this->dto->setTextAlign($textAlign);
        return $this;
    }

    public function setHelp(TranslatableInterface|string $help): static
    {
        $this->dto->setHelp($help);
        return $this;
    }

    public function addCssClass(string $cssClass): static
    {
        $this->dto->addCssClass($cssClass);
        return $this;
    }

    public function setCssClass(string $cssClass): static
    {
        $this->dto->setCssClass($cssClass);
        return $this;
    }

    public function setTranslationParameters(array $parameters): static
    {
        $this->dto->setTranslationParameters($parameters);
        return $this;
    }

    public function setTemplateName(string $name): static
    {
        $this->dto->setTemplateName($name);
        $this->dto->setTemplatePath(null);
        return $this;
    }

    public function setTemplatePath(string $path): static
    {
        $this->dto->setTemplatePath($path);
        return $this;
    }

    public function addFormTheme(string ...$formThemePaths): static
    {
        $this->dto->addFormTheme(...$formThemePaths);
        return $this;
    }

    public function addWebpackEncoreEntries(string ...$entryNames): static
    {
        foreach ($entryNames as $entryName) {
            $this->dto->addWebpackEncoreEntry($entryName);
        }

        return $this;
    }

    public function addCssFiles(string ...$paths): static
    {
        foreach ($paths as $path) {
            $this->dto->addCssAsset($path);
        }

        return $this;
    }

    public function addJsFiles(string ...$paths): static
    {
        foreach ($paths as $path) {
            $this->dto->addJsAsset($path);
        }

        return $this;
    }

    public function addHtmlContentsToHead(string ...$contents): static
    {
        foreach ($contents as $content) {
            $this->dto->addHtmlContentToHead($content);
        }

        return $this;
    }

    public function addHtmlContentsToBody(string ...$contents): static
    {
        foreach ($contents as $content) {
            $this->dto->addHtmlContentToBody($content);
        }

        return $this;
    }

    public function setCustomOption(string $optionName, mixed $optionValue): static
    {
        $this->dto->setCustomOption($optionName, $optionValue);
        return $this;
    }

    public function setCustomOptions(array $options): static
    {
        $this->dto->setCustomOptions($options);
        return $this;
    }

    public function hideOnDetail(): static
    {
        $this->dto->hideOn(FieldDescriptor::PAGE_DETAIL);
        return $this;
    }

    public function hideOnForm(): static
    {
        $this->dto->hideOn(...FieldDescriptor::FORM_PAGES);
        return $this;
    }

    public function hideWhenCreating(): static
    {
        $this->dto->hideOn(FieldDescriptor::PAGE_NEW);
        return $this;
    }

    public function hideWhenUpdating(): static
    {
        $this->dto->hideOn(FieldDescriptor::PAGE_EDIT);
        return $this;
    }

    public function hideOnIndex(): static
    {
        $this->dto->hideOn(FieldDescriptor::PAGE_INDEX);
        return $this;
    }

    public function showOnIndex(bool $show = true): static
    {
        $show ? $this->dto->displayOn(FieldDescriptor::PAGE_INDEX) : $this->dto->hideOn(FieldDescriptor::PAGE_INDEX);
        return $this;
    }

    public function showOnDetail(bool $show = true): static
    {
        $show ? $this->dto->displayOn(FieldDescriptor::PAGE_DETAIL) : $this->dto->hideOn(FieldDescriptor::PAGE_DETAIL);
        return $this;
    }

    public function onlyOnDetail(): static
    {
        $this->dto->setDisplayedOn([FieldDescriptor::PAGE_DETAIL]);
        return $this;
    }

    public function onlyOnForms(): static
    {
        $this->dto->setDisplayedOn(FieldDescriptor::FORM_PAGES);
        return $this;
    }

    public function onlyOnIndex(): static
    {
        $this->dto->setDisplayedOn([FieldDescriptor::PAGE_INDEX]);
        return $this;
    }

    public function onlyWhenCreating(): static
    {
        $this->dto->setDisplayedOn([FieldDescriptor::PAGE_NEW]);
        return $this;
    }

    public function onlyWhenUpdating(): static
    {
        $this->dto->setDisplayedOn([FieldDescriptor::PAGE_EDIT]);
        return $this;
    }

    /**
     * @param int|string $cols an integer 1..12 or a responsive spec like "col-md-6 col-xxl-3"
     */
    public function setColumns(int|string $cols): static
    {
        $this->dto->setColumns(is_int($cols) ? 'col-md-' . $cols : $cols);
        return $this;
    }

    public function setDefaultColumns(int|string $cols): static
    {
        $this->dto->setDefaultColumns(is_int($cols) ? 'col-md-' . $cols : $cols);
        return $this;
    }

    public function getAsDto(): FieldDescriptor
    {
        return $this->dto;
    }
}
