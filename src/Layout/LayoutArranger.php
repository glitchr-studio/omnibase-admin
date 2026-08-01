<?php

namespace Base\Admin\Layout;

use Base\Admin\Config\Menu\MenuItem;

/**
 * Pure merge of a code-defined MenuItem tree with a persisted LayoutConfig:
 * reorders, hides, and drops stale entries - never invents or mutates the
 * underlying items beyond calling setHidden(). No dependencies beyond
 * MenuItem, so this is trivially unit-testable.
 */
class LayoutArranger
{
    /**
     * @param MenuItem[] $items
     * @return MenuItem[]
     */
    public function apply(array $items, LayoutConfig $config): array
    {
        return $this->applyLevel($items, $config->getItems(), $config->getColumns());
    }

    /**
     * @param MenuItem[] $items
     * @param array<int, array{key: string, visible: bool, deleted?: bool, children: array}> $stored
     * @return MenuItem[]
     */
    private function applyLevel(array $items, array $stored, int $columns): array
    {
        /** @var array<string, MenuItem> $byKey first-wins on a key collision */
        $byKey = [];
        foreach ($items as $item) {
            $key = $item->getKey();
            if (!isset($byKey[$key])) {
                $byKey[$key] = $item;
            }
        }

        $result = [];
        $seen = [];
        $consumedKeys = $this->collectConsumedSourceKeys($stored);

        // stored keys, in stored order, each carrying its matching code item
        foreach ($stored as $entry) {
            $key = $entry['key'];

            if ($entry['deleted'] ?? false) {
                // Marking $seen (not just skipping) is what actually makes
                // this stick for a code-defined item: without it, the
                // "anything the stored config never mentioned" pass below
                // would treat this key as never having been stored at all
                // and re-append it fresh, same as any other never-
                // customized item. An ad-hoc entry never reaches here with
                // deleted:true in practice (the client just omits the key
                // instead, see synthesizeAdHocWidget()'s own comment) but
                // honoring the flag for one too is harmless - the result
                // is identical either way.
                $seen[$key] = true;
                continue;
            }

            $item = $byKey[$key] ?? null;
            if (null === $item) {
                if (empty($entry['blockName'])) {
                    // a removed CRUD/menu item - drop the stale stored entry silently
                    continue;
                }
                // an ad-hoc/palette-added widget: no code-defined counterpart
                // exists (and never will), so the stored entry IS its whole
                // definition - synthesize a real MenuItem straight from it.
                // True removal falls out for free from this design: if a
                // future save simply omits this key, it's never synthesized
                // again - no tombstone/delete path needed anywhere here.
                $item = $this->synthesizeAdHocWidget($entry);
            } elseif (!empty($entry['blockName'])) {
                // A code-defined widget the admin has customized in place
                // (see the analytics card's settings panel) - blockName
                // present on a stored entry that DID match a code-defined
                // item means "apply this override", not "synthesize a new
                // widget": blockName/icon are deliberately left untouched
                // (swapping what a key renders isn't customizing it, it's
                // a different widget), only label/params ever get applied
                // this way. An empty label falls back to the code-defined
                // default rather than shipping a blank title - the same
                // "clear the field to reset" a superadmin would expect,
                // not a state this app has to specifically distinguish
                // from "never customized".
                if (\is_string($entry['label'] ?? null) && '' !== $entry['label']) {
                    $item->setLabel($entry['label']);
                }
                if (\is_array($entry['params'] ?? null)) {
                    $item->setParams($entry['params']);
                }
            }

            $item->setHidden(!$entry['visible']);
            // min(), not a raw assignment: LayoutConfig::sanitizeItems()
            // already clamped a STORED size to whatever column count was
            // in effect when it was saved, but the column count can be
            // lowered afterwards without that entry ever being re-saved -
            // re-clamping here against the CURRENT columns is what keeps
            // an old, now-too-wide stored size from overflowing the grid.
            $item->setSize(min($columns, $entry['size'] ?? $item->getSize()));
            $item->setHeight(\array_key_exists('height', $entry) ? $entry['height'] : $item->getHeight());
            if ([] !== $item->getSubItems()) {
                $item->setSubItems($this->applyLevel($item->getSubItems(), $entry['children'] ?? [], $columns));
            }

            $result[] = $item;
            $seen[$key] = true;
        }

        // anything the stored config never mentioned, appended in code
        // order at the end of the level (simpler and less surprising than
        // splicing relative to a sibling; a superadmin drags it once) -
        // UNLESS this exact key was merged into a composite elsewhere in
        // this same stored config (consumedKeys): the app's own
        // configureWidgetItems() still yields it every request regardless
        // (nothing removes a code-defined item from PHP just because a
        // superadmin merged it), so without this check it would silently
        // reappear as its own standalone card right alongside the
        // composite it's now part of - reported live as "the dashboard is
        // adding replicates on top of the merged one".
        foreach ($items as $item) {
            $key = $item->getKey();
            if (isset($seen[$key]) || isset($consumedKeys[$key])) {
                continue;
            }

            $item->setHidden(false);
            // A code-defined default size (e.g. the built-in analytics_card
            // widget's ->setSize(3)) never went through LayoutConfig's own
            // sanitizer at all - clamp it here too, so it can't overflow a
            // dashboard configured with fewer columns than the code default.
            $item->setSize(min($columns, $item->getSize()));
            $seen[$key] = true;
            $result[] = $item;
        }

        return $result;
    }

    /**
     * Every source key recorded by a composite's own stored panes at this
     * level (see DashboardWidgetController::merge()'s 'sourceKey' pane
     * field, set when the pane's original widget was code-defined) - a
     * code-defined item whose key shows up here has been absorbed into
     * that composite and must not also be re-appended as its own
     * standalone entry by the "unmentioned code items" pass above.
     *
     * @param array<int, array{blockName?: string, params?: array}> $stored
     * @return array<string, true>
     */
    private function collectConsumedSourceKeys(array $stored): array
    {
        $consumed = [];
        foreach ($stored as $entry) {
            if ('composite' !== ($entry['blockName'] ?? null)) {
                continue;
            }

            foreach ($entry['params']['panes'] ?? [] as $pane) {
                if (\is_array($pane) && \is_string($pane['sourceKey'] ?? null) && '' !== $pane['sourceKey']) {
                    $consumed[$pane['sourceKey']] = true;
                }
            }
        }

        return $consumed;
    }

    /**
     * Builds a real MenuItem straight from a stored ad-hoc entry - no
     * DashboardWidgetTypeRegistry dependency needed here (that stays this
     * class's own promise: "no dependencies beyond MenuItem"). Whether
     * blockName actually resolves to something renderable is decided
     * later, exactly where it already is today for every widget:
     * dashboard.html.twig's own {% if widgetType %} guard.
     */
    private function synthesizeAdHocWidget(array $entry): MenuItem
    {
        return (new MenuItem(MenuItem::TYPE_BLOCK, $entry['label'] ?? '', $entry['icon'] ?? null))
            ->setBlockName($entry['blockName'])
            ->setParams($entry['params'] ?? [])
            ->setKey($entry['key'])
            ->setAdHoc(true);
    }

    /**
     * Snapshots the current code-defined order as a starting config, so the
     * customize UI's JS never has to invent keys - it always starts from
     * what capture() already produced server-side.
     *
     * @param MenuItem[] $items
     */
    public function capture(array $items): LayoutConfig
    {
        return LayoutConfig::new()->setItems($this->captureLevel($items));
    }

    /**
     * @param MenuItem[] $items
     */
    private function captureLevel(array $items): array
    {
        $captured = [];
        foreach ($items as $item) {
            $captured[] = [
                'key' => $item->getKey(),
                'visible' => !$item->isHidden(),
                'size' => $item->getSize(),
                'height' => $item->getHeight(),
                'children' => $this->captureLevel($item->getSubItems()),
            ];
        }

        return $captured;
    }
}
