<?php

namespace Base\Admin\Layout;

/**
 * The superadmin-authored customization stored for ONE admin page: its
 * own entry (customized title/description) plus its top-right action
 * row's order and per-action icons.
 *
 * All of it lives in the CRUD scope, keyed by a stable page key - a
 * CRUD's URL slug for an entity page, "system/<page>" for a bespoke one
 * (settings, API keys). Both kinds need the same thing, an identity that
 * is NOT a sidebar menu item (see LayoutScope::CRUD's own doc comment),
 * so they share one resolver instead of each walking the stored config
 * themselves.
 */
class PageCustomization
{
    public function __construct(protected readonly LayoutStore $layoutStore)
    {
    }

    /**
     * @return array{page_key: string, page: ?array, action_order: array<string, int>, action_icons: array<string, string>, action_hidden: array<string, true>}
     */
    public function resolve(string $pageKey): array
    {
        $item = null;

        // ->toArray() is the whole config (['items' => [...]]), not the item
        // list - quickSave writes into $array['items'] and this has to read
        // the same level back.
        $stored = $this->layoutStore->get(LayoutScope::CRUD)->toArray();

        foreach (\is_array($stored['items'] ?? null) ? $stored['items'] : [] as $candidate) {
            if (($candidate['key'] ?? null) === $pageKey) {
                $item = $candidate;
                break;
            }
        }

        // action name => position, for the top-right button row. Stored as
        // the entry's children (see LayoutController::quickSave) so the
        // order is just the child order. A map rather than a list because
        // the templates sort with it and an O(1) lookup keeps that simple;
        // an action with no stored position sorts after the known ones,
        // so a newly added action appears at the end instead of vanishing.
        $order = [];
        // ...and action name => custom icon class, from the same children.
        // An action the superadmin never re-iconed simply has no entry and
        // the template falls back to the icon declared in code.
        $icons = [];
        // ...and the set of actions hidden ON THIS PAGE. A SET, not a
        // visible-flag map: 'visible' defaults to true everywhere (see
        // LayoutConfig::sanitizeItems), so the only thing worth carrying
        // to a template is the exception - "this one is hidden here" -
        // which keeps the templates' own check a plain key lookup rather
        // than a three-state (unstored/true/false) dance.
        $hidden = [];
        foreach (\is_array($item['children'] ?? null) ? $item['children'] : [] as $position => $child) {
            if (\is_string($child['key'] ?? null) && '' !== $child['key']) {
                $order[$child['key']] = $position;

                if (\is_string($child['icon'] ?? null) && '' !== $child['icon']) {
                    $icons[$child['key']] = $child['icon'];
                }

                if (\array_key_exists('visible', $child) && !$child['visible']) {
                    $hidden[$child['key']] = true;
                }
            }
        }

        return [
            'page_key' => $pageKey,
            'page' => $item,
            'action_order' => $order,
            'action_icons' => $icons,
            'action_hidden' => $hidden,
        ];
    }
}
