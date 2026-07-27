<?php

namespace Base\Admin\Layout;

/**
 * One persisted layout: an ordered, hide/show-annotated tree of item keys.
 * Shape: {version, items: [{key, visible, children: [...]}]}. fromArray()
 * is the validation boundary for data coming back out of SettingBag (itself
 * reachable and hand-editable via the generic settings CRUD) - it must
 * never throw or produce something LayoutArranger can't safely consume,
 * no matter how malformed the input.
 */
class LayoutConfig
{
    private const MAX_DEPTH = 2;
    private const MAX_ITEMS = 500;

    protected int $version = 1;

    /** @var array<int, array{key: string, visible: bool, children: array}> */
    protected array $items = [];

    public static function new(): static
    {
        return new static();
    }

    public static function fromArray(mixed $data): static
    {
        $config = new static();

        if (!\is_array($data)) {
            return $config;
        }

        $config->version = \is_int($data['version'] ?? null) ? $data['version'] : 1;
        $config->items = self::sanitizeItems($data['items'] ?? [], self::MAX_DEPTH);

        return $config;
    }

    private static function sanitizeItems(mixed $items, int $depthRemaining): array
    {
        if (!\is_array($items) || $depthRemaining < 0) {
            return [];
        }

        $sanitized = [];
        foreach ($items as $item) {
            if (\count($sanitized) >= self::MAX_ITEMS) {
                break;
            }

            if (!\is_array($item) || !\is_string($item['key'] ?? null) || '' === $item['key']) {
                continue;
            }

            $sanitized[] = [
                'key' => $item['key'],
                'visible' => !\array_key_exists('visible', $item) || (bool) $item['visible'],
                'children' => self::sanitizeItems($item['children'] ?? [], $depthRemaining - 1),
            ];
        }

        return $sanitized;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @return array<int, array{key: string, visible: bool, children: array}>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param array<int, array{key: string, visible: bool, children: array}> $items
     */
    public function setItems(array $items): static
    {
        $this->items = $items;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'items' => $this->items,
        ];
    }
}
