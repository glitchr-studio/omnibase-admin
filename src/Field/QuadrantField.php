<?php

namespace Base\Admin\Field;

use Base\Field\Type\QuadrantType;
use Base\Admin\Config\Option\TextAlign;
use Symfony\Contracts\Translation\TranslatableInterface;

final class QuadrantField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_RENDER_FORMAT = 'renderFormat';
    public const OPTION_ICON_ALIGN = 'iconAlign';

    public const OPTION_SHOW_FIRST = 'showFirst';
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
            ->setFormType(QuadrantType::class)
            ->addCssClass('field-quadrant')
            ->addCssClass('quadrant-widget')
            ->setTextAlign(TextAlign::CENTER)
            ->showIconOnly()
            ->setColumns(2)
            ->setFormTypeOptionIfNotSet('data_class', null);
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
}
