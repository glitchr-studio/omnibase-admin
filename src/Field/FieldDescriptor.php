<?php

namespace Base\Admin\Field;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Single mutable descriptor carried by every field: what property it maps,
 * which Symfony FormType renders it, where it is displayed, and how.
 *
 * This is the EasyCorp-free replacement for both FieldDto and its
 * KeyValueStore bags: one class, plain arrays, no builder/DTO split.
 */
final class FieldDescriptor
{
    public const PAGE_INDEX = 'index';
    public const PAGE_DETAIL = 'detail';
    public const PAGE_EDIT = 'edit';
    public const PAGE_NEW = 'new';

    public const ALL_PAGES = [self::PAGE_INDEX, self::PAGE_DETAIL, self::PAGE_EDIT, self::PAGE_NEW];
    public const FORM_PAGES = [self::PAGE_EDIT, self::PAGE_NEW];

    protected ?string $fieldFqcn = null;
    protected ?string $propertyName = null;
    protected ?string $propertyNameSuffix = null;

    protected mixed $value = null;
    protected mixed $formattedValue = null;
    /** @var callable|null */
    protected $formatValueCallable = null;

    protected TranslatableInterface|string|bool|null $label = null;
    protected TranslatableInterface|string|null $help = null;
    protected array $translationParameters = [];

    protected ?string $formType = null;
    protected array $formTypeOptions = [];

    protected ?bool $sortable = null;
    protected ?bool $virtual = null;
    protected ?bool $disabled = null;
    protected ?bool $required = null;
    protected mixed $emptyData = null;

    protected string|Expression|null $permission = null;

    protected ?string $textAlign = null;
    protected string $cssClass = '';
    protected array $htmlAttributes = [];
    protected null|int|string $columns = null;
    protected null|int|string $defaultColumns = null;

    protected ?string $templateName = 'crud/field/text';
    protected ?string $templatePath = null;
    protected array $formThemePaths = [];

    protected array $cssAssets = [];
    protected array $jsAssets = [];
    protected array $webpackEntries = [];
    protected array $headContents = [];
    protected array $bodyContents = [];

    protected array $customOptions = [];
    protected array $doctrineMetadata = [];

    /** @var string[] pages this field is displayed on */
    protected array $displayedOn = self::ALL_PAGES;

    protected string $uniqueId;

    public function __construct()
    {
        $this->uniqueId = uniqid('field_', false);
    }

    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    public function setUniqueId(string $uniqueId): static
    {
        $this->uniqueId = $uniqueId;
        return $this;
    }

    public function getFieldFqcn(): ?string
    {
        return $this->fieldFqcn;
    }

    public function setFieldFqcn(?string $fieldFqcn): static
    {
        $this->fieldFqcn = $fieldFqcn;
        return $this;
    }

    public function getProperty(): ?string
    {
        return $this->propertyName;
    }

    public function setProperty(?string $propertyName): static
    {
        $this->propertyName = $propertyName;
        return $this;
    }

    public function getPropertySuffix(): ?string
    {
        return $this->propertyNameSuffix;
    }

    public function setPropertySuffix(?string $propertyNameSuffix): static
    {
        $this->propertyNameSuffix = $propertyNameSuffix;
        return $this;
    }

    public function getPropertyWithSuffix(): ?string
    {
        return $this->propertyName . $this->propertyNameSuffix;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): static
    {
        $this->value = $value;
        return $this;
    }

    public function getFormattedValue(): mixed
    {
        return $this->formattedValue;
    }

    public function setFormattedValue(mixed $formattedValue): static
    {
        $this->formattedValue = $formattedValue;
        return $this;
    }

    public function getFormatValueCallable(): ?callable
    {
        return $this->formatValueCallable;
    }

    public function setFormatValueCallable(?callable $callable): static
    {
        $this->formatValueCallable = $callable;
        return $this;
    }

    public function getLabel(): TranslatableInterface|string|bool|null
    {
        return $this->label;
    }

    public function setLabel(TranslatableInterface|string|bool|null $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getHelp(): TranslatableInterface|string|null
    {
        return $this->help;
    }

    public function setHelp(TranslatableInterface|string|null $help): static
    {
        $this->help = $help;
        return $this;
    }

    public function getTranslationParameters(): array
    {
        return $this->translationParameters;
    }

    public function setTranslationParameters(array $translationParameters): static
    {
        $this->translationParameters = $translationParameters;
        return $this;
    }

    public function getFormType(): ?string
    {
        return $this->formType;
    }

    public function setFormType(?string $formTypeFqcn): static
    {
        $this->formType = $formTypeFqcn;
        return $this;
    }

    public function getFormTypeOptions(): array
    {
        return $this->formTypeOptions;
    }

    public function setFormTypeOptions(array $formTypeOptions): static
    {
        $this->formTypeOptions = $formTypeOptions;
        return $this;
    }

    /**
     * Option names support "." paths, e.g. "attr.autofocus".
     */
    public function getFormTypeOption(string $optionName, mixed $default = null): mixed
    {
        $subject = $this->formTypeOptions;
        foreach (explode('.', $optionName) as $key) {
            if (!is_array($subject) || !array_key_exists($key, $subject)) {
                return $default;
            }
            $subject = $subject[$key];
        }

        return $subject;
    }

    public function setFormTypeOption(string $optionName, mixed $optionValue): static
    {
        $subject = &$this->formTypeOptions;
        $keys = explode('.', $optionName);
        $last = array_pop($keys);
        foreach ($keys as $key) {
            if (!isset($subject[$key]) || !is_array($subject[$key])) {
                $subject[$key] = [];
            }
            $subject = &$subject[$key];
        }
        $subject[$last] = $optionValue;

        return $this;
    }

    public function setFormTypeOptionIfNotSet(string $optionName, mixed $optionValue): static
    {
        if (null === $this->getFormTypeOption($optionName)) {
            $this->setFormTypeOption($optionName, $optionValue);
        }

        return $this;
    }

    public function isSortable(): ?bool
    {
        return $this->sortable;
    }

    public function setSortable(?bool $sortable): static
    {
        $this->sortable = $sortable;
        return $this;
    }

    public function isVirtual(): ?bool
    {
        return $this->virtual;
    }

    public function setVirtual(?bool $virtual): static
    {
        $this->virtual = $virtual;
        return $this;
    }

    public function isDisabled(): ?bool
    {
        return $this->disabled;
    }

    public function setDisabled(?bool $disabled): static
    {
        $this->disabled = $disabled;
        return $this;
    }

    public function isRequired(): ?bool
    {
        return $this->required;
    }

    public function setRequired(?bool $required): static
    {
        $this->required = $required;
        return $this;
    }

    public function getEmptyData(): mixed
    {
        return $this->emptyData;
    }

    public function setEmptyData(mixed $emptyData): static
    {
        $this->emptyData = $emptyData;
        return $this;
    }

    public function getPermission(): string|Expression|null
    {
        return $this->permission;
    }

    public function setPermission(string|Expression|null $permission): static
    {
        $this->permission = $permission;
        return $this;
    }

    public function getTextAlign(): ?string
    {
        return $this->textAlign;
    }

    public function setTextAlign(?string $textAlign): static
    {
        $this->textAlign = $textAlign;
        return $this;
    }

    public function getCssClass(): string
    {
        return $this->cssClass;
    }

    public function setCssClass(string $cssClass): static
    {
        $this->cssClass = trim($cssClass);
        return $this;
    }

    public function addCssClass(string $cssClass): static
    {
        $this->cssClass = trim($this->cssClass . ' ' . $cssClass);
        return $this;
    }

    public function getHtmlAttributes(): array
    {
        return $this->htmlAttributes;
    }

    public function setHtmlAttributes(array $htmlAttributes): static
    {
        $this->htmlAttributes = $htmlAttributes;
        return $this;
    }

    public function setHtmlAttribute(string $attributeName, mixed $attributeValue): static
    {
        $this->htmlAttributes[$attributeName] = $attributeValue;
        return $this;
    }

    public function getColumns(): null|int|string
    {
        return $this->columns;
    }

    public function setColumns(null|int|string $columns): static
    {
        $this->columns = $columns;
        return $this;
    }

    public function getDefaultColumns(): null|int|string
    {
        return $this->defaultColumns;
    }

    public function setDefaultColumns(null|int|string $defaultColumns): static
    {
        $this->defaultColumns = $defaultColumns;
        return $this;
    }

    public function getTemplateName(): ?string
    {
        return $this->templateName;
    }

    public function setTemplateName(?string $templateName): static
    {
        $this->templateName = $templateName;
        return $this;
    }

    public function getTemplatePath(): ?string
    {
        return $this->templatePath;
    }

    public function setTemplatePath(?string $templatePath): static
    {
        $this->templatePath = $templatePath;
        return $this;
    }

    public function getFormThemes(): array
    {
        return $this->formThemePaths;
    }

    public function addFormTheme(string ...$formThemePaths): static
    {
        foreach ($formThemePaths as $formThemePath) {
            $this->formThemePaths[] = $formThemePath;
        }

        return $this;
    }

    public function getCssAssets(): array
    {
        return $this->cssAssets;
    }

    public function addCssAsset(string $asset): static
    {
        $this->cssAssets[] = $asset;
        return $this;
    }

    public function getJsAssets(): array
    {
        return $this->jsAssets;
    }

    public function addJsAsset(string $asset): static
    {
        $this->jsAssets[] = $asset;
        return $this;
    }

    public function getWebpackEncoreEntries(): array
    {
        return $this->webpackEntries;
    }

    public function addWebpackEncoreEntry(string $entry): static
    {
        $this->webpackEntries[] = $entry;
        return $this;
    }

    public function getHeadContents(): array
    {
        return $this->headContents;
    }

    public function addHtmlContentToHead(string $content): static
    {
        $this->headContents[] = $content;
        return $this;
    }

    public function getBodyContents(): array
    {
        return $this->bodyContents;
    }

    public function addHtmlContentToBody(string $content): static
    {
        $this->bodyContents[] = $content;
        return $this;
    }

    public function getCustomOptions(): array
    {
        return $this->customOptions;
    }

    public function setCustomOptions(array $customOptions): static
    {
        $this->customOptions = $customOptions;
        return $this;
    }

    public function getCustomOption(string $optionName, mixed $default = null): mixed
    {
        return $this->customOptions[$optionName] ?? $default;
    }

    public function setCustomOption(string $optionName, mixed $optionValue): static
    {
        $this->customOptions[$optionName] = $optionValue;
        return $this;
    }

    public function getDoctrineMetadata(): array
    {
        return $this->doctrineMetadata;
    }

    public function setDoctrineMetadata(array $doctrineMetadata): static
    {
        $this->doctrineMetadata = $doctrineMetadata;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getDisplayedOn(): array
    {
        return $this->displayedOn;
    }

    /**
     * @param string[] $pages
     */
    public function setDisplayedOn(array $pages): static
    {
        $this->displayedOn = array_values(array_unique($pages));
        return $this;
    }

    public function displayOn(string ...$pages): static
    {
        return $this->setDisplayedOn(array_merge($this->displayedOn, $pages));
    }

    public function hideOn(string ...$pages): static
    {
        return $this->setDisplayedOn(array_diff($this->displayedOn, $pages));
    }

    public function isDisplayedOn(string $page): bool
    {
        return in_array($page, $this->displayedOn, true);
    }
}
