<?php

namespace Base\Admin\Field;

use Base\Field\Type\TranslationType;
use Base\Service\Localizer;
use Base\Admin\Config\Crud;
use Symfony\Contracts\Translation\TranslatableInterface;

class TranslationField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_MAX_LENGTH = 'maxLength';
    public const OPTION_RENDER_AS_HTML = 'renderAsHtml';
    public const OPTION_STRIP_TAGS = 'stripTags';

    public static function new(?string $propertyName = null, TranslatableInterface|string|bool|null $label = null): self
    {
        $field = (new self())
            ->setProperty('translations')
            ->hideOnIndex()
            ->setTemplateName('crud/field/translatable')
            ->setCustomOption('required', true)
            ->setCustomOption(self::OPTION_MAX_LENGTH, 50)
            ->setFormType(TranslationType::class);

        if ($propertyName) {
            $field->setFields([$propertyName => []])->showOnIndex($propertyName);
        } else {
            $field->hideOnDetail();
        }

        return $field;
    }

    public function setMaxLength(int $length): self
    {
        if ($length < 1) {
            throw new \InvalidArgumentException(sprintf('The argument of the "%s()" method must be 1 or higher (%d given).', __METHOD__, $length));
        }

        $this->setCustomOption(self::OPTION_MAX_LENGTH, $length);

        return $this;
    }

    /**
     * @param $autoload
     * @return $this
     */
    public function autoload($autoload = true): self
    {
        $this->setFormTypeOption('autoload', $autoload);

        return $this;
    }

    public function renderAsHtml(bool $asHtml = true): self
    {
        $this->setCustomOption(self::OPTION_RENDER_AS_HTML, $asHtml);

        return $this;
    }

    public function stripTags(bool $stripTags = true): self
    {
        $this->setCustomOption(self::OPTION_STRIP_TAGS, $stripTags);

        return $this;
    }

    public function setFields(array $fields): self
    {
        $this->setFormTypeOption('fields', $fields);

        return $this;
    }

    public function showOnIndex(?string $field = null): self
    {
        if ($field) {
            $this->setCustomOption('show_field', $field);
            $this->dto->displayOn(Crud::PAGE_INDEX);
        }

        return $this;
    }

    /**
     * @param bool $isRequired
     * @return $this
     */
    public function setRequired(bool $isRequired = true)
    {
        $this->setCustomOption('required', $isRequired);

        return $this;
    }

    /**
     * @param $excludedFields
     * @return $this
     */
    public function setExcludedFields($excludedFields): self
    {
        if (!is_array($excludedFields)) {
            $excludedFields = [$excludedFields];
        }
        $this->setFormTypeOption('excluded_fields', $excludedFields);

        return $this;
    }

    /**
     * @param $translationClass
     * @return $this
     */
    public function setTranslationClass($translationClass): self
    {
        $this->setFormTypeOption('translation_class', $translationClass);

        return $this;
    }

    public function setDefaultLocale(string $defaultLocale): self
    {
        $this->setFormTypeOption('default_locale', $defaultLocale);

        return $this;
    }

    public function renderSingleLocale(?string $singleLocale = null): self
    {
        $singleLocale = $singleLocale ?? Localizer::getDefaultLocale();
        $this->setFormTypeOption('single_locale', $singleLocale);

        return $this;
    }
}
