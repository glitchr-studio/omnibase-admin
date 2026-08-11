<?php

namespace Base\Admin\Layout;

/**
 * One persisted layout: an ordered, hide/show-annotated tree of item keys.
 * Shape: {version, items: [{key, visible, size, height, deleted, children:
 * [...]}]}, plus five OPTIONAL keys (blockName, label, icon, params, and
 * label ALONE without the other three - see below).
 *
 * blockName+label+icon+params together mean an ad-hoc/palette-added widget
 * - a self-contained widget definition with no code-defined counterpart to
 * re-skin. An item without any of the five is otherwise byte-identical to
 * the pre-widening 4-key shape (every pre-existing stored config, and
 * every sidebar-scope item, which never carries any of them at all).
 *
 * label CAN also appear alone, with no blockName/icon/params: an in-place
 * title edit on a matched CODE-DEFINED item, block-type or section-type
 * alike (see LayoutArranger::applyLevel()'s own matching half - label
 * applies to any matched code-defined item, not just a blockName-carrying
 * one). icon/params only ever mean something for a blockName-dispatched
 * (block-type) widget's own rendering, so they stay gated to entries that
 * actually carry a blockName.
 *
 * deleted only ever matters for a code-defined item (see LayoutArranger's
 * own comment on why an ad-hoc one needs no such flag), but is always
 * present/false rather than one more OPTIONAL key, since unlike the
 * others it isn't tied to whether this entry has its own self-contained
 * widget definition.
 *
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
    // Explicit height override, in pixels - null means "auto" (natural
    // content height / stretched to match a taller sibling row, see
    // layout.html.twig's flex-fill rules). Same range as
    // MenuItem::setHeight()'s own clamp.
    private const MIN_HEIGHT = 80;
    private const MAX_HEIGHT = 2000;
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
    // How many columns the dashboard grid is divided into - configurable
    // per the owner's own request ("why not... allow to change it to 2 3
    // 5 10?"), not a fixed 3. DEFAULT_COLUMNS=5 matches what was already
    // being used/liked before this was made configurable at all. A
    // widget's own 'size' is always clamped to [MIN_SIZE, columns] - not
    // a separate fixed ceiling - so "size 3" always means "3 of however
    // many columns are configured", never silently overflows the grid.
    private const MIN_COLUMNS = 2;
    private const MAX_COLUMNS = 10;
    private const DEFAULT_COLUMNS = 5;

    protected int $version = 1;
    protected int $columns = self::DEFAULT_COLUMNS;

    /** @var array<int, array{key: string, visible: bool, size: int, height: ?int, deleted: bool, children: array, blockName?: string, label?: string, icon?: ?string, params?: array}> */
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
        $columns = \is_int($data['columns'] ?? null) ? $data['columns'] : self::DEFAULT_COLUMNS;
        $config->columns = max(self::MIN_COLUMNS, min(self::MAX_COLUMNS, $columns));
        $config->items = self::sanitizeItems($data['items'] ?? [], self::MAX_DEPTH, $config->columns);

        return $config;
    }

    private static function sanitizeItems(mixed $items, int $depthRemaining, int $maxSize): array
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
            $height = \is_int($item['height'] ?? null) ? max(self::MIN_HEIGHT, min(self::MAX_HEIGHT, $item['height'])) : null;

            $entry = [
                'key' => $item['key'],
                'visible' => !\array_key_exists('visible', $item) || (bool) $item['visible'],
                'size' => max(self::MIN_SIZE, min($maxSize, $size)),
                'height' => $height,
                // Same "always present, missing means true/default" shape
                // as visible above - any widget can toggle its own card
                // chrome off now (see MenuItem::$background's own
                // docblock), not just Bienvenue/"Generic" (the one this
                // was first built for).
                'background' => !\array_key_exists('background', $item) || (bool) $item['background'],
                // A code-defined item (no blockName carried on ITS OWN
                // entry - an ad-hoc one is already truly gone the moment a
                // save simply omits its key, see LayoutArranger's own
                // comment) has no other way to be permanently removed: the
                // app's own configureWidgetItems()/etc. still yields it
                // every request regardless of what's stored, so without
                // this flag omitting its key would just make it reappear
                // fresh next load instead of staying gone.
                'deleted' => (bool) ($item['deleted'] ?? false),
                'children' => self::sanitizeItems($item['children'] ?? [], $depthRemaining - 1, $maxSize),
            ];

            // An in-place title edit on a matched code-defined item - block
            // OR section widget alike (see LayoutArranger::applyLevel()'s
            // own matching half of this: label applies to any matched
            // code-defined item now, not just a blockName-carrying one -
            // found live, only Trafic/Vues could ever have their title
            // changed before this, every plain link-list card like Blog/
            // Photos/Destinations couldn't, because this whole key used to
            // live inside the blockName-only branch below and got silently
            // dropped by this exact validator for anything else). '' not
            // null: every widget template does {{ widget.label|trans(...) }}.
            $label = \is_string($item['label'] ?? null) && '' !== $item['label']
                ? mb_substr($item['label'], 0, self::MAX_LABEL_LENGTH)
                : null;

            $blockName = $item['blockName'] ?? null;
            if (\is_string($blockName) && '' !== $blockName) {
                // Key order kept exactly as before (blockName, label,
                // icon, params) for every existing ad-hoc entry - only
                // the label-alone case below is new.
                $entry['blockName'] = mb_substr($blockName, 0, self::MAX_BLOCK_NAME_LENGTH);
                $entry['label'] = $label ?? '';
                $icon = $item['icon'] ?? null;
                $entry['icon'] = \is_string($icon) && '' !== $icon ? mb_substr($icon, 0, self::MAX_ICON_LENGTH) : null;
                $entry['params'] = self::sanitizeParams($item['params'] ?? [], self::MAX_PARAMS_DEPTH);
            } else {
                if (null !== $label) {
                    $entry['label'] = $label;
                }
                // icon-ALONE on a code-defined item: an in-place page/menu
                // icon customization (see layout.html.twig's data-page-icon
                // handler) - same OPTIONAL shape and clear-to-reset
                // semantics as label-alone. Locale-agnostic on purpose: an
                // icon isn't language-specific, so it never joins the
                // 'intl' map below.
                $icon = $item['icon'] ?? null;
                if (\is_string($icon) && '' !== $icon) {
                    $entry['icon'] = mb_substr($icon, 0, self::MAX_ICON_LENGTH);
                }
            }

            // A page's own customized description (see MenuItem::$description
            // / layout.html.twig's data-page-desc handler) - same OPTIONAL
            // "present only when customized" shape as label-alone, same
            // clear-to-reset semantics ('' drops the key entirely).
            $description = \is_string($item['description'] ?? null) && '' !== $item['description']
                ? mb_substr($item['description'], 0, self::MAX_LABEL_LENGTH)
                : null;
            if (null !== $description) {
                $entry['description'] = $description;
            }

            // Per-locale page title/description overrides for NON-default
            // admin locales: {locale: {label?, description?}}. The plain
            // label/description keys above stay the DEFAULT locale's values
            // (and the only shape dashboard widgets ever use) - this map is
            // additive, only the page-heading feature reads it. Same
            // clear-to-reset semantics per locale; a locale left with
            // neither field is dropped, an empty map drops the key.
            $intl = [];
            foreach (\is_array($item['intl'] ?? null) ? $item['intl'] : [] as $locale => $values) {
                if (!\is_string($locale) || !preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $locale) || !\is_array($values)) {
                    continue;
                }
                $localized = [];
                foreach (['label', 'description'] as $field) {
                    if (\is_string($values[$field] ?? null) && '' !== $values[$field]) {
                        $localized[$field] = mb_substr($values[$field], 0, self::MAX_LABEL_LENGTH);
                    }
                }
                if ([] !== $localized) {
                    $intl[$locale] = $localized;
                }
            }
            if ([] !== $intl) {
                $entry['intl'] = $intl;
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

    public function getColumns(): int
    {
        return $this->columns;
    }

    public function setColumns(int $columns): static
    {
        $this->columns = max(self::MIN_COLUMNS, min(self::MAX_COLUMNS, $columns));
        return $this;
    }

    /**
     * @return array<int, array{key: string, visible: bool, size: int, height: ?int, deleted: bool, children: array, blockName?: string, label?: string, icon?: ?string, params?: array}>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param array<int, array{key: string, visible: bool, size: int, height: ?int, deleted: bool, children: array, blockName?: string, label?: string, icon?: ?string, params?: array}> $items
     */
    public function setItems(array $items): static
    {
        $this->items = $items;
        return $this;
    }

    /**
     * Clears the deleted flag on the top-level entry with this key, if
     * present - the restore-widget action's whole job (see
     * DashboardWidgetController::restore()). A no-op if the key isn't
     * stored at all (nothing to restore) or wasn't deleted in the first
     * place (idempotent, matches a superadmin double-clicking restore).
     */
    public function restoreItem(string $key): static
    {
        foreach ($this->items as &$item) {
            if ($item['key'] === $key) {
                $item['deleted'] = false;
            }
        }
        unset($item);

        return $this;
    }

    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'columns' => $this->columns,
            'items' => $this->items,
        ];
    }
}
