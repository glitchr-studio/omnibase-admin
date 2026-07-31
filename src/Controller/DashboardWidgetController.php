<?php

namespace Base\Admin\Controller;

use Base\Admin\Config\Menu\MenuItem;
use Base\Admin\Config\MenuItem as MenuItemFacade;
use Base\Admin\Widget\PaletteWidgetTypeRegistry;
use Base\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Backs the dashboard's "+ Add widget" palette (types/newInstance), the
 * drag-one-card-onto-another merge gesture (merge, also used to add a
 * THIRD/FOURTH card into an already-merged composite), and pulling a
 * composite back apart into its standalone cards again (split). All
 * actions are GET, no CSRF - read-only, none of them persists anything
 * (persistence still only ever happens through the existing batched
 * admin_layout_save POST on "Done"). Each renders server-side Twig so the
 * client never has to reinvent widget markup itself - it only ever
 * inserts/replaces whatever HTML comes back.
 */
class DashboardWidgetController extends AbstractController
{
    // A 2x2 arrangement is the practical ceiling for one composite card -
    // beyond that it stops reading as "one fused card" and the merge
    // gesture (drop onto a small existing card) gets fiddly to target.
    private const MAX_PANES = 4;

    // A pane's relative width within a 2-pane composite (see
    // sanitizePaneDefinition()'s 'size' handling and composite.html.twig's
    // grid-template-columns) - bounds are generous but not unbounded
    // (arbitrary client input), same defensive posture as
    // LayoutConfig::MIN_SIZE/MAX_HEIGHT etc.
    private const MIN_PANE_SIZE = 1;
    private const MAX_PANE_SIZE = 6;

    // Merge/split have no direct line to the dashboard's own configured
    // column count (LayoutConfig lives one layer up, only consulted on
    // the batched admin_layout_save) - this is the fallback when the
    // client's own ?columns= is missing/malformed, matching
    // LayoutConfig::DEFAULT_COLUMNS.
    private const DEFAULT_COLUMNS = 5;

    public function __construct(
        protected readonly PaletteWidgetTypeRegistry $paletteRegistry,
        protected readonly TranslatorInterface $translator,
    ) {
    }

    public function types(): JsonResponse
    {
        $this->assertSuperadmin();

        $types = \array_map(fn ($type) => [
            'name' => $type::getName(),
            'label' => $this->translator->trans($type->getDefaultLabel(), [], 'admin'),
            'icon' => $type->getDefaultIcon(),
        ], $this->paletteRegistry->all());

        return $this->json(['types' => $types]);
    }

    /**
     * Drag-to-merge: the client sends the full list of panes the resulting
     * composite should have - either [target, dragged] for a brand new
     * 2-pane fusion, or [...existing composite's own panes, dragged] when
     * dropping a THIRD/FOURTH card onto an already-merged one (the client
     * decides which case it is; this endpoint doesn't care, it just
     * renders whatever pane list it's given, capped at MAX_PANES).
     */
    public function merge(Request $request): Response
    {
        $this->assertSuperadmin();

        $rawPanes = \json_decode((string) $request->query->get('panes', ''), true);
        if (!\is_array($rawPanes)) {
            throw $this->createNotFoundException('Invalid widget definitions to merge.');
        }

        $panes = [];
        foreach ($rawPanes as $rawPane) {
            $pane = $this->sanitizePaneDefinition($rawPane);
            if (null !== $pane) {
                $panes[] = $pane;
            }
            if (\count($panes) >= self::MAX_PANES) {
                break;
            }
        }

        if (\count($panes) < 2) {
            throw $this->createNotFoundException('At least two valid widget definitions are required to merge.');
        }

        $columns = \filter_var($request->query->get('columns'), \FILTER_VALIDATE_INT);
        if (false === $columns || null === $columns || $columns < 1) {
            $columns = self::DEFAULT_COLUMNS;
        }

        // Sum of what each pane already was, not a fixed count-based
        // heuristic - a 1-wide and a 2-wide widget merge into a 3-wide
        // composite instead of both being forced to whatever the pane
        // COUNT happened to imply. Clamped to the dashboard's own column
        // count so the live preview (before "Done" ever saves/re-clamps
        // it, see LayoutConfig::sanitizeItems()) can't overflow the grid.
        $size = \min($columns, \array_sum(\array_column($panes, 'size')));

        $widget = (new MenuItem(MenuItem::TYPE_BLOCK, null, null))
            ->setBlockName('composite')
            ->setParams(['panes' => $panes])
            ->setKey('adhoc.' . \bin2hex(\random_bytes(6)))
            ->setSize($size)
            ->setAdHoc(true);

        return $this->render('@Admin/widget/_block.html.twig', [
            'widget' => $widget,
            'customize_enabled' => true,
        ]);
    }

    /**
     * The inverse of merge(): the client sends a composite's own
     * data-widget-params (its 'panes' array, unchanged), this renders each
     * pane back out as its own standalone widget fragment - block-type
     * panes through the same _block.html.twig wrapper newInstance() uses,
     * group-type (link-list) panes through _group.html.twig - concatenated
     * into one response. The client removes the composite node and
     * inserts this in its place; nothing is persisted here either, same
     * as every other action in this controller.
     */
    public function split(Request $request): Response
    {
        $this->assertSuperadmin();

        $rawPanes = \json_decode((string) $request->query->get('panes', ''), true);
        if (!\is_array($rawPanes)) {
            throw $this->createNotFoundException('Invalid composite widget definition to split.');
        }

        $html = '';
        foreach ($rawPanes as $rawPane) {
            $pane = $this->sanitizePaneDefinition($rawPane);
            if (null === $pane) {
                continue;
            }

            $key = 'adhoc.' . \bin2hex(\random_bytes(6));

            if (isset($pane['type'])) {
                $widget = (new MenuItem(MenuItem::TYPE_BLOCK, $pane['label'], $pane['icon']))
                    ->setBlockName($pane['type'])
                    ->setParams($pane['params'])
                    ->setKey($key)
                    // Restores whatever this pane's own relative width was
                    // inside the composite (see sanitizePaneDefinition()'s
                    // 'size') rather than resetting every split-out widget
                    // to size 1 - a 1:2 merge splits back into a 1-wide and
                    // a 2-wide card, not two 1-wide ones.
                    ->setSize($pane['size'])
                    ->setAdHoc(true);

                $html .= $this->renderView('@Admin/widget/_block.html.twig', [
                    'widget' => $widget,
                    'customize_enabled' => true,
                ]);
                continue;
            }

            $subItems = [];
            foreach ($pane['subItems'] as $subItem) {
                $subItems[] = MenuItemFacade::linkToUrl($subItem['label'], $subItem['icon'], $subItem['url'])->setLinkUrl($subItem['url']);
            }

            $widget = (new MenuItem(MenuItem::TYPE_SECTION, $pane['label'], $pane['icon']))
                ->setSubItems($subItems)
                ->setKey($key)
                ->setSize($pane['size'])
                ->setAdHoc(true);

            $html .= $this->renderView('@Admin/widget/_group.html.twig', [
                'widget' => $widget,
                'customize_enabled' => true,
            ]);
        }

        if ('' === $html) {
            throw $this->createNotFoundException('No valid panes to split out of this composite.');
        }

        return new Response($html);
    }

    /**
     * Validation boundary for a pane definition coming from the client
     * (data-merge-def or a composite's own data-widget-params - both
     * trusted in spirit, still untrusted request input): never throws on
     * malformed input, drops anything that doesn't fit either the
     * block-pane ({type, label, icon, params}) or the group-pane
     * ({label, icon, subItems}) shape. Same defensive style as
     * LayoutConfig::fromArray().
     *
     * @return array{type: string, label: ?string, icon: ?string, params: array, sourceKey: ?string, size: int}|array{label: ?string, icon: ?string, subItems: array, sourceKey: ?string, size: int}|null
     */
    private function sanitizePaneDefinition(mixed $data): ?array
    {
        if (!\is_array($data)) {
            return null;
        }

        $label = \is_string($data['label'] ?? null) ? $data['label'] : '';
        $icon = \is_string($data['icon'] ?? null) ? $data['icon'] : null;
        // This pane's relative width within a 2-pane composite (see
        // composite.html.twig's grid-template-columns) - defaults to 1
        // both for a genuinely new equal-share merge AND for every
        // composite persisted before this field existed (an absent 'size'
        // in already-stored params.panes[] is indistinguishable from "was
        // never sized", and 1 is the correct fallback for both: it's what
        // every pane already behaved as under the old fixed repeat(2,
        // 1fr) CSS).
        $size = \filter_var($data['size'] ?? null, \FILTER_VALIDATE_INT);
        $size = (false === $size || null === $size) ? 1 : \max(self::MIN_PANE_SIZE, \min(self::MAX_PANE_SIZE, $size));
        // The pane's ORIGINAL widget key, if it had one (data-merge-def
        // carries the sortable key of whatever card it was captured from -
        // see layout.html.twig's captureMergeDefinition()). Round-tripped
        // as-is into the stored composite's own pane data so
        // LayoutArranger can recognize "this code-defined key has been
        // absorbed into a composite" and skip re-appending it as its own
        // standalone card - without this, a merged code-defined widget
        // (its underlying configureWidgetItems() entry never goes away)
        // reappeared as a duplicate right next to the composite it's now
        // part of on the very next save/reload.
        $sourceKey = \is_string($data['sourceKey'] ?? null) && '' !== $data['sourceKey'] ? $data['sourceKey'] : null;

        // A pane coming from data-merge-def names its type 'blockName'
        // (that's the top-level widget's own attribute name); a pane
        // coming from an EXISTING composite's own data-widget-params
        // (the split/re-merge case) names it 'type' (CompositeWidgetType's
        // own pane shape, params.panes[n].type) - accept either so both
        // call sites can hand this method their data as-is.
        $type = $data['blockName'] ?? $data['type'] ?? null;
        if (\is_string($type) && '' !== $type && 'composite' !== $type) {
            return [
                'type' => $type,
                'label' => $label,
                'icon' => $icon,
                'params' => \is_array($data['params'] ?? null) ? $data['params'] : [],
                'sourceKey' => $sourceKey,
                'size' => $size,
            ];
        }

        if (\is_array($data['subItems'] ?? null)) {
            $subItems = [];
            foreach ($data['subItems'] as $subItem) {
                if (!\is_array($subItem) || !\is_string($subItem['label'] ?? null) || !\is_string($subItem['url'] ?? null) || '' === $subItem['url']) {
                    continue;
                }
                $subItems[] = [
                    'label' => $subItem['label'],
                    'icon' => \is_string($subItem['icon'] ?? null) ? $subItem['icon'] : null,
                    'url' => $subItem['url'],
                ];
            }
            if ([] === $subItems) {
                return null;
            }

            return ['label' => $label, 'icon' => $icon, 'subItems' => $subItems, 'sourceKey' => $sourceKey, 'size' => $size];
        }

        return null;
    }

    public function newInstance(string $blockName, Request $request): Response
    {
        $this->assertSuperadmin();

        $type = $this->paletteRegistry->get($blockName);
        if (null === $type) {
            throw $this->createNotFoundException(\sprintf('Unknown palette widget type "%s".', $blockName));
        }

        $label = $request->query->get('label');
        if (!\is_string($label) || '' === \trim($label)) {
            $label = $this->translator->trans($type->getDefaultLabel(), [], 'admin');
        }

        $widget = (new MenuItem(MenuItem::TYPE_BLOCK, $label, $type->getDefaultIcon()))
            ->setBlockName($blockName)
            ->setKey('adhoc.' . \bin2hex(\random_bytes(6)))
            ->setSize(1)
            ->setAdHoc(true);

        return $this->render('@Admin/widget/_block.html.twig', [
            'widget' => $widget,
            'customize_enabled' => true,
        ]);
    }

    private function assertSuperadmin(): void
    {
        if (!$this->isGranted(UserRole::SUPERADMIN)) {
            throw $this->createAccessDeniedException('Adding a dashboard widget requires ' . UserRole::SUPERADMIN . '.');
        }
    }
}
