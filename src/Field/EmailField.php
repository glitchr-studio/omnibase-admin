<?php

namespace Base\Admin\Field;

use Base\Admin\Config\Option\TextAlign;
use Symfony\Component\Form\Extension\Core\Type\EmailType;

class EmailField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_ICON_CUSTOM = 'icon';
    public const OPTION_SHOW = 'show';
    public const SHOW_ICON = 'icon';
    public const SHOW_EMAIL = 'email';
    public const SHOW_ALL = 'all';

    public function setTargetFieldName(string $show): self
    {
        $this->setCustomOption(self::OPTION_SHOW, $show);

        return $this;
    }

    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/email')
            ->setFormType(EmailType::class)
            ->setTextAlign(TextAlign::CENTER)
            ->setCustomOption(self::OPTION_SHOW, self::SHOW_ICON)
            ->setCustomOption(self::OPTION_ICON_CUSTOM, 'fa-solid fa-at')
            ->setDefaultColumns('col-md-6 col-xxl-5');
    }

    /**
     * @param string $faIcon
     * @return $this
     */
    public function setCustomIcon(string $faIcon)
    {
        $this->setCustomOption(self::OPTION_ICON_CUSTOM, $faIcon);

        return $this;
    }

    /**
     * @return $this
     */
    public function renderIcon()
    {
        $this->setCustomOption(self::OPTION_SHOW, self::SHOW_ICON);

        return $this;
    }

    /**
     * @return $this
     */
    public function renderAll()
    {
        $this->setCustomOption(self::OPTION_SHOW, self::SHOW_ALL);

        return $this;
    }

    /**
     * @return $this
     */
    public function renderText()
    {
        $this->setCustomOption(self::OPTION_SHOW, self::SHOW_EMAIL);

        return $this;
    }
}
