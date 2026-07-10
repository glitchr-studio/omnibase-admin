<?php

namespace Base\Admin\Field;

use Base\Enum\ThreadState;
use Base\Field\Type\StateType;
use Base\Admin\Config\Option\TextAlign;
use Symfony\Contracts\Translation\TranslatableInterface;

class StateField extends SelectField
{
    public const OPTION_CLASS = 'class';

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/select')
            ->setFormType(StateType::class)
            ->setCustomOption(self::OPTION_CLASS, ThreadState::class)
            ->setCustomOption(self::OPTION_SHOW, self::SHOW_ALL)
            ->setTextAlign(TextAlign::LEFT)
            ->setFormTypeOption('capitalize', false);
    }
}
