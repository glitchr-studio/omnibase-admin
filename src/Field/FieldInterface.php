<?php

namespace Base\Admin\Field;

use Symfony\Contracts\Translation\TranslatableInterface;

interface FieldInterface
{
    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null);

    public function getAsDto(): FieldDescriptor;

    public function __clone(): void;
}
