<?php

namespace Base\Admin\Field;

use InvalidArgumentException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Contracts\Translation\TranslatableInterface;

class TextField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_MAX_LENGTH = 'maxLength';
    public const OPTION_RENDER_AS_HTML = 'renderAsHtml';
    public const OPTION_RENDER_AS_BOOLEAN = 'renderAsBoolean';
    public const OPTION_STRIP_TAGS = 'stripTags';

    /**
     * @param TranslatableInterface|string|false|null $label
     */
    public static function new(string $propertyName, $label = null)
    {
        return (new static())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(TextType::class)
            ->addCssClass('field-text')
            ->setDefaultColumns('col-md-6 col-xxl-5')
            ->setCustomOption(self::OPTION_MAX_LENGTH, null)
            ->setCustomOption(self::OPTION_RENDER_AS_HTML, false)
            ->setCustomOption(self::OPTION_STRIP_TAGS, false);
    }

    /**
     * This option is ignored when using 'renderAsHtml()' to avoid
     * truncating contents in the middle of an HTML tag.
     */
    public function setMaxLength(int $length)
    {
        if ($length < 1) {
            throw new InvalidArgumentException(sprintf('The argument of the "%s()" method must be 1 or higher (%d given).', __METHOD__, $length));
        }

        $this->setCustomOption(self::OPTION_MAX_LENGTH, $length);

        return $this;
    }

    /**
     * @param bool $asHtml
     * @return $this
     */
    public function renderAsHtml(bool $asHtml = true)
    {
        $this->setCustomOption(self::OPTION_RENDER_AS_HTML, $asHtml);

        return $this;
    }

    /**
     * @param bool $asBool
     * @return $this
     */
    public function renderAsBoolean(bool $asBool = true)
    {
        $this->setCustomOption(self::OPTION_RENDER_AS_BOOLEAN, $asBool);

        return $this;
    }

    /**
     * @param bool $stripTags
     * @return $this
     */
    public function stripTags(bool $stripTags = true)
    {
        $this->setCustomOption(self::OPTION_STRIP_TAGS, $stripTags);

        return $this;
    }
}
