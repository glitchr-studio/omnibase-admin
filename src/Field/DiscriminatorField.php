<?php

namespace Base\Admin\Field;

use Base\Admin\Config\Option\TextAlign;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Contracts\Translation\TranslatableInterface;

class DiscriminatorField extends SelectField implements FieldInterface
{
    public const OPTION_DISCRIMINATOR_AUTOLOAD = 'discriminatorAutoload';
    public const OPTION_SHOW_COLUMN = 'discriminatorColumn';
    public const OPTION_SHOW_INLINE = 'discriminatorInline';
    public const OPTION_SHOW_LEAF = 'discriminatorLeaf';

    public static function new(string $propertyName = "id", TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label ?? "Type")
            ->setTemplateName('crud/field/select')
            ->setFormType(HiddenType::class) // To be replaced by DiscriminatorType once ready..
            ->setCustomOption(self::OPTION_SHOW, self::SHOW_ALL)
            ->setCustomOption(self::OPTION_SHOW_FIRST, self::SHOW_ALL)
            ->setCustomOption(self::OPTION_DISCRIMINATOR_AUTOLOAD, false)
            ->setCustomOption(self::OPTION_SHOW_COLUMN, false)
            ->setCustomOption(self::OPTION_SHOW_INLINE, true)
            ->setCustomOption(self::OPTION_SHOW_LEAF, false)
            ->setCustomOption(self::OPTION_RENDER_FORMAT, "text")
            ->setIconAlign(TextAlign::RIGHT)->hideOnForm()
            ->setTextAlign(TextAlign::LEFT)->setColumns(6);
    }

    public function showColumnLabel(bool $show = true): self
    {
        $this->setCustomOption(self::OPTION_SHOW_COLUMN, $show);
        return $this;
    }

    public function showInline(bool $inline = true): self
    {
        $this->setCustomOption(self::OPTION_SHOW_INLINE, $inline);
        return $this;
    }

    public function showLeaf(bool $show = true): self
    {
        $this->setCustomOption(self::OPTION_SHOW_LEAF, $show);
        return $this;
    }
}
