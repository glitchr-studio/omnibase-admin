<?php

namespace Base\Admin\Field;

use Base\Field\Type\SelectType;
use Doctrine\Common\Collections\Collection;
use Base\Admin\Config\Option\TextAlign;
use Symfony\Contracts\Translation\TranslatableInterface;
class SelectField implements FieldInterface
{
    use FieldTrait;


    public const OPTION_CONFIRMATION_MODAL_ON_CHECK = 'confirmationModalOnCheck';
    public const OPTION_CONFIRMATION_MODAL_ON_UNCHECK = 'confirmationModalOnUncheck';

    public const OPTION_CRUD_LINK = 'href';
    public const OPTION_AUTOCOMPLETE = 'autocomplete';
    public const OPTION_DEFAULT_CHOICE = 'default_choice';
    public const OPTION_CLASS = 'class';

    public const OPTION_CHOICES = 'choices';
    public const OPTION_ICONS = 'icons';
    public const OPTION_FILTER = 'choice_filter';

    public const OPTION_DISPLAY_LIMIT = 'displayLimit';
    public const OPTION_ICON_ALIGN = 'iconAlign';

    public const OPTION_RENDER_FORMAT = 'renderFormat';
    public const OPTION_SHOW_FIRST = 'showFirst';
    public const OPTION_USE_COLOR = 'useColor';
    public const OPTION_SHOW = 'show';

    public const NO_SHOW = 0;
    public const SHOW_NAME_ONLY = 1;
    public const SHOW_ICON_ONLY = 2;
    public const SHOW_ALL = 3;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/select')
            ->setFormType(SelectType::class)
            ->useHtml(false) // Do not use HTML by default in EasyAdmin.. (line height are constrained)
            ->setCustomOption(self::OPTION_DISPLAY_LIMIT, 2)
            ->setCustomOption(self::OPTION_SHOW, self::SHOW_ICON_ONLY)
            ->setCustomOption(self::OPTION_SHOW_FIRST, self::SHOW_ALL)
            ->setCustomOption(self::OPTION_RENDER_FORMAT, 'count')
            ->setTextAlign(TextAlign::CENTER)
            ->addCssClass('field-select');
    }

    /**
     * @param bool $useColor
     * @return $this
     */
    public function useColor(bool $useColor = true)
    {
        $this->setCustomOption(self::OPTION_USE_COLOR, $useColor);

        return $this;
    }

    /**
     * @param bool $useHtml
     * @return $this
     */
    public function useHtml(bool $useHtml = true)
    {
        $this->setFormTypeOption('use_html', $useHtml);

        return $this;
    }

    /**
     * @param string $endpoint
     * @return $this
     */
    public function autocomplete(string $endpoint)
    {
        $this->setFormTypeOption('autocomplete_endpoint', $endpoint);

        return $this;
    }

    /**
     * @param string $textAlign
     * @return $this
     */
    public function setTextAlign(string $textAlign)
    {
        $this->setIconAlign($textAlign);
        $this->dto->setTextAlign($textAlign);

        return $this;
    }

    /**
     * @param string $iconAlign
     * @return $this
     */
    public function setIconAlign(string $iconAlign)
    {
        $this->setCustomOption(self::OPTION_ICON_ALIGN, $iconAlign);

        return $this;
    }

    /**
     * @param bool $allow
     * @return $this
     */
    public function allowCrudLink(bool $allow = true)
    {
        $this->setCustomOption(self::OPTION_CRUD_LINK, $allow);

        return $this;
    }

    /**
     * @param bool $allow
     * @return $this
     */
    public function allowMultipleChoices(bool $allow = true)
    {
        $this->setFormTypeOptionIfNotSet('multiple', $allow);

        return $this;
    }

    /**
     * @param bool $allow
     * @return $this
     */
    public function allowDelete(bool $allow = true)
    {
        $this->setFormTypeOption('allow_delete', $allow);

        return $this;
    }

    /**
     * @param bool $allow
     * @return $this
     */
    public function allowSearch(bool $allow = true)
    {
        $this->setFormTypeOption('minimumResultsForSearch', $allow ? 0 : -1);

        return $this;
    }

    /**
     * @param bool $horizontal
     * @return SelectField
     */
    public function turnHorizontal(bool $horizontal)
    {
        return $this->showVertical(!$horizontal);
    }

    /**
     * @param bool $vertical
     * @return SelectField
     */
    public function showVertical(bool $vertical = true)
    {
        return $this->setFormTypeOption('vertical', $vertical);
    }

    /**
     * @param bool|array $tokenSeparators
     * @return $this
     */
    public function allowTags(bool|array $tokenSeparators = [])
    {
        $this->setFormTypeOption('tags', !empty($tokenSeparators) || false === $tokenSeparators);
        if ($tokenSeparators) {
            $this->setFormTypeOption('tokenSeparators', $tokenSeparators);
        }

        return $this;
    }

    /**
     * @param $choiceGenerator
     * @return $this
     */
    public function setChoices($choiceGenerator)
    {
        if (!\is_array($choiceGenerator) && !\is_callable($choiceGenerator)) {
            throw new \InvalidArgumentException(sprintf('The argument of the "%s" method must be an array or a closure ("%s" given).', __METHOD__, \gettype($choiceGenerator)));
        }

        $this->setFormTypeOption(self::OPTION_CHOICES, $choiceGenerator);

        return $this;
    }

    /**
     * @return $this
     */
    public function setAutoFilter()
    {
        $this->setCustomOption(self::OPTION_FILTER, null);

        return $this;
    }

    /**
     * @param ...$filter
     * @return $this
     */
    public function setFilter(...$filter)
    {
        $this->setFormTypeOptionIfNotSet(self::OPTION_FILTER, $filter);

        return $this;
    }

    /**
     * @param int $limit
     * @return $this
     */
    public function setDisplayLimit(int $limit = 2)
    {
        $this->setCustomOption(self::OPTION_DISPLAY_LIMIT, $limit);

        return $this;
    }

    /**
     * @param array $icons
     * @return $this
     */
    public function setIcons(array $icons)
    {
        $this->setCustomOption(self::OPTION_ICONS, $icons);

        return $this;
    }

    /**
     * @param string|null $class
     * @return $this
     */
    public function setClass(?string $class = null)
    {
        $this->setFormTypeOption(self::OPTION_CLASS, $class);

        return $this;
    }

    /**
     * @param int $show
     * @return $this
     */
    public function showFirst(int $show = self::SHOW_ALL)
    {
        $this->setCustomOption(self::OPTION_SHOW_FIRST, $show);

        return $this;
    }

    /**
     * @param int $show
     * @return $this
     */
    public function show(int $show = self::SHOW_ALL)
    {
        $this->setCustomOption(self::OPTION_SHOW, $show);

        return $this;
    }

    /**
     * @return $this
     */
    public function showIconOnly()
    {
        $this->setCustomOption(self::OPTION_SHOW_FIRST, self::SHOW_ICON_ONLY);
        $this->setCustomOption(self::OPTION_SHOW, self::SHOW_ICON_ONLY);

        return $this;
    }

    /**
     * @return $this
     */
    public function showNameOnly()
    {
        $this->setCustomOption(self::OPTION_SHOW_FIRST, self::SHOW_NAME_ONLY);
        $this->setCustomOption(self::OPTION_SHOW, self::SHOW_NAME_ONLY);

        return $this;
    }

    /**
     * @return $this
     */
    public function renderAsCount()
    {
        $this->setCustomOption(self::OPTION_SHOW_FIRST, self::NO_SHOW);
        $this->setCustomOption(self::OPTION_RENDER_FORMAT, 'count');

        return $this;
    }

    /**
     * @return $this
     */
    public function renderAsText()
    {
        $this->setCustomOption(self::OPTION_RENDER_FORMAT, 'text');

        return $this;
    }

    /**
     * @param $defaultChoices
     * @return $this
     */
    public function setDefault($defaultChoices)
    {
        if (!\is_array($defaultChoices) && !$defaultChoices instanceof Collection) {
            $defaultChoices = [$defaultChoices];
        }

        $this->setFormTypeOption('empty_data', $defaultChoices);
        $this->setCustomOption(self::OPTION_DEFAULT_CHOICE, $defaultChoices);

        return $this;
    }

    public function withConfirmation(bool $onCheck = true, bool $onUncheck = true): self
    {
        $this->setCustomOption(self::OPTION_CONFIRMATION_MODAL_ON_CHECK, $onCheck);
        $this->setCustomOption(self::OPTION_CONFIRMATION_MODAL_ON_UNCHECK, $onUncheck);

        return $this;
    }
}
