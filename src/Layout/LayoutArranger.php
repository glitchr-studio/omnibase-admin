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
        return $this->applyLevel($items, $config->getItems());
    }

    /**
     * @param MenuItem[] $items
     * @param array<int, array{key: string, visible: bool, children: array}> $stored
     * @return MenuItem[]
     */
    private function applyLevel(array $items, array $stored): array
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

        // stored keys, in stored order, each carrying its matching code item
        foreach ($stored as $entry) {
            $key = $entry['key'];
            $item = $byKey[$key] ?? null;
            if (null === $item) {
                // a removed CRUD/menu item - drop the stale stored entry silently
                continue;
            }

            $item->setHidden(!$entry['visible']);
            if ([] !== $item->getSubItems()) {
                $item->setSubItems($this->applyLevel($item->getSubItems(), $entry['children'] ?? []));
            }

            $result[] = $item;
            $seen[$key] = true;
        }

        // anything the stored config never mentioned, appended in code
        // order at the end of the level (simpler and less surprising than
        // splicing relative to a sibling; a superadmin drags it once)
        foreach ($items as $item) {
            $key = $item->getKey();
            if (isset($seen[$key])) {
                continue;
            }

            $item->setHidden(false);
            $seen[$key] = true;
            $result[] = $item;
        }

        return $result;
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
                'children' => $this->captureLevel($item->getSubItems()),
            ];
        }

        return $captured;
    }
}
