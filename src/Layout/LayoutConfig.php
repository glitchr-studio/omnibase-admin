<?php

namespace Base\Admin\Layout;

/**
 * One persisted layout: an ordered, hide/show-annotated tree of item keys.
 * Shape: {version, items: [{key, visible, size, children: [...]}]}, plus
 * four OPTIONAL keys (blockName, label, icon, params) present only on
 * ad-hoc/palette-added widgets - a self-contained widget definition with
 * no code-defined counterpart to re-skin. An item without blockName is
 * byte-identical to the pre-widening 4-key shape (every pre-existing
 * stored config, and every sidebar-scope item, which never carries
 * blockName at all).
 * fromArray() is the validation boundary for data coming back out of
 * SettingBag (itself reachable and hand-editable via the generic settings
 * CRUD) - it must never throw or produce something LayoutArranger can't
 * safely consume, no matter how malformed the input.
 */
class LayoutConfig
{
    private const MAX_DEPTH = 2;
    private const MAX_ITEMS = 500;
    private const MIN_SIZE = 1;
    private const MAX_SIZE = 3;
    private const MAX_LABEL_LENGTH = 200;
    private const MAX_ICON_LENGTH = 100;
    private const MAX_BLOCK_NAME_LENGTH = 100;
    private const MAX_PARAM_KEYS = 50;
    // 4, not 3: a merged composite widget's group-type pane nests
    // params.panes[n].subItems[n].{label,icon,url} - 3 array levels below
    // the top-level params array itself - and needs to survive
    // sanitization intact for the merge feature to round-trip through a
    // save/reload. See LayoutConfigTest::testDeeplyNestedParamsAreCappedNotThrown.
    private const MAX_PARAMS_DEPTH = 4;

    protected int $version = 1;

    /** @var array<int, array{key: string, visible: bool, size: int, children: array, blockName?: string, label?: string, icon?: ?string, params?: array}> */
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

            $size = \is_int($item['size'] ?? null) ? $item['size'] : self::MIN_SIZE;

            $entry = [
                'key' => $item['key'],
                'visible' => !\array_key_exists('visible', $item) || (bool) $item['visible'],
                'size' => max(self::MIN_SIZE, min(self::MAX_SIZE, $size)),
                'children' => self::sanitizeItems($item['children'] ?? [], $depthRemaining - 1),
            ];

            $blockName = $item['blockName'] ?? null;
            if (\is_string($blockName) && '' !== $blockName) {
                $entry['blockName'] = mb_substr($blockName, 0, self::MAX_BLOCK_NAME_LENGTH);
                // '' not null: every widget template does {{ widget.label|trans(...) }}
                $entry['label'] = \is_string($item['label'] ?? null) ? mb_substr($item['label'], 0, self::MAX_LABEL_LENGTH) : '';
                $icon = $item['icon'] ?? null;
                $entry['icon'] = \is_string($icon) && '' !== $icon ? mb_substr($icon, 0, self::MAX_ICON_LENGTH) : null;
                $entry['params'] = self::sanitizeParams($item['params'] ?? [], self::MAX_PARAMS_DEPTH);
            }

            $sanitized[] = $entry;
        }

        return $sanitized;
    }

    /**
     * Same defensive style as sanitizeItems(): array-only, string|int keys,
     * scalar/null/array values, depth+count capped, silently drops
     * anything else rather than throwing.
     */
    private static function sanitizeParams(mixed $params, int $depthRemaining): array
    {
        if (!\is_array($params) || $depthRemaining < 0) {
            return [];
        }

        $sanitized = [];
        foreach ($params as $key => $value) {
            if (!\is_string($key) && !\is_int($key)) {
                continue;
            }
            if (\count($sanitized) >= self::MAX_PARAM_KEYS) {
                break;
            }

            if (\is_array($value)) {
                $sanitized[$key] = self::sanitizeParams($value, $depthRemaining - 1);
            } elseif (\is_scalar($value) || null === $value) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @return array<int, array{key: string, visible: bool, size: int, children: array, blockName?: string, label?: string, icon?: ?string, params?: array}>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param array<int, array{key: string, visible: bool, size: int, children: array, blockName?: string, label?: string, icon?: ?string, params?: array}> $items
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
