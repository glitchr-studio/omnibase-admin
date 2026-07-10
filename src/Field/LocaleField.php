<?php

namespace Base\Admin\Field;

use Symfony\Component\Form\Extension\Core\Type\LocaleType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class LocaleField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_SHOW_CODE = 'showCode';
    public const OPTION_SHOW_NAME = 'showName';
    public const OPTION_LOCALE_CODES_TO_KEEP = 'localeCodesToKeep';
    public const OPTION_LOCALE_CODES_TO_REMOVE = 'localeCodesToRemove';

    /**
     * @param TranslatableInterface|string|false|null $label
     */
    public static function new(string $propertyName, $label = null)
    {
        return (new static())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/country')
            ->setFormType(LocaleType::class)
            ->addCssClass('field-locale')
            ->setDefaultColumns('col-md-5 col-xxl-4')
            ->setCustomOption(self::OPTION_SHOW_CODE, false)
            ->setCustomOption(self::OPTION_SHOW_NAME, true)
            ->setCustomOption(self::OPTION_LOCALE_CODES_TO_KEEP, null)
            ->setCustomOption(self::OPTION_LOCALE_CODES_TO_REMOVE, null);
    }

    /**
     * @param bool $isShown
     * @return $this
     */
    public function showCode(bool $isShown = true)
    {
        $this->setCustomOption(self::OPTION_SHOW_CODE, $isShown);

        return $this;
    }

    /**
     * @param bool $isShown
     * @return $this
     */
    public function showName(bool $isShown = true)
    {
        $this->setCustomOption(self::OPTION_SHOW_NAME, $isShown);

        return $this;
    }

    /**
     * Restricts the list of locales shown by the field to the given list of locale codes.
     * e.g. ->includeOnly(['de', 'en', 'fr']).
     */
    public function includeOnly(array $countryCodesToKeep)
    {
        $this->setCustomOption(self::OPTION_LOCALE_CODES_TO_KEEP, $countryCodesToKeep);

        return $this;
    }

    /**
     * Removes the given list of locale codes from the locales displayed by the field.
     * e.g. ->remove(['de', 'fr']).
     */
    public function remove(array $countryCodesToRemove)
    {
        $this->setCustomOption(self::OPTION_LOCALE_CODES_TO_REMOVE, $countryCodesToRemove);

        return $this;
    }
}
