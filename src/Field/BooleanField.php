<?php

namespace Base\Admin\Field;

use Base\Field\Type\BooleanType;
use Base\Admin\Config\Option\TextAlign;

final class BooleanField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_RENDER_AS_SWITCH = 'switch';
    public const OPTION_CONFIRMATION_MODAL_ON_CHECK = 'confirmation[onCheck]';
    public const OPTION_CONFIRMATION_MODAL_ON_UNCHECK = 'confirmation[onUncheck]';

    /** @internal */
    public const CSRF_TOKEN_NAME = 'ea-toggle';
    public const OPTION_TOGGLE_URL = 'toggleUrl';

    /**
     * @param string|false|null $label
     */
    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/boolean')
            ->setFormType(BooleanType::class)
            ->addCssClass('field-boolean')
            ->setTextAlign(TextAlign::CENTER)
            ->setCustomOption(self::OPTION_RENDER_AS_SWITCH, true)
            ->setCustomOption(self::OPTION_CONFIRMATION_MODAL_ON_CHECK, false)
            ->setCustomOption(self::OPTION_CONFIRMATION_MODAL_ON_UNCHECK, false);
    }

    public function renderAsSwitch(bool $isASwitch = true): self
    {
        $this->setCustomOption(self::OPTION_RENDER_AS_SWITCH, $isASwitch);

        return $this;
    }

    public function showInline(bool $inline = true): self
    {
        $this->setFormTypeOption('inline', $inline);

        return $this;
    }

    public function withConfirmation(bool $onCheck = true, bool $onUncheck = true): self
    {
        $this->setCustomOption(self::OPTION_CONFIRMATION_MODAL_ON_CHECK, $onCheck);
        $this->setCustomOption(self::OPTION_CONFIRMATION_MODAL_ON_UNCHECK, $onUncheck);

        return $this;
    }
}
