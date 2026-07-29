<?php

namespace Base\Admin\Controller;

use Base\Admin\Config\Menu\MenuItem;
use Base\Admin\Widget\PaletteWidgetTypeRegistry;
use Base\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Backs the dashboard's "+ Add widget" palette (types/newInstance) and the
 * drag-one-card-onto-another merge gesture (merge). All three actions are
 * GET, no CSRF - read-only, none of them persists anything (persistence
 * still only ever happens through the existing batched admin_layout_save
 * POST on "Done"). Each renders server-side Twig so the client never has
 * to reinvent widget markup itself - it only ever inserts/replaces
 * whatever HTML comes back.
 */
class DashboardWidgetController extends AbstractController
{
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
     * Drag-to-merge: the client sends each side's own full definition
     * (captured client-side from data-merge-def, see _block.html.twig and
     * dashboard.html.twig's group branch), this renders the two-pane
     * composite fragment the same way newInstance() renders a single
     * fresh one - GET, no CSRF, nothing persisted here either (the
     * dropped/target nodes only stop existing in storage once "Done"
     * batches the whole grid's current DOM state, same as every other
     * customize-mode edit).
     */
    public function merge(Request $request): Response
    {
        $this->assertSuperadmin();

        $target = $this->decodePaneDefinition($request->query->get('target'));
        $dragged = $this->decodePaneDefinition($request->query->get('dragged'));
        if (null === $target || null === $dragged) {
            throw $this->createNotFoundException('Invalid widget definition to merge.');
        }

        $widget = (new MenuItem(MenuItem::TYPE_BLOCK, null, null))
            ->setBlockName('composite')
            ->setParams(['panes' => [$target, $dragged]])
            ->setKey('adhoc.' . \bin2hex(\random_bytes(6)))
            ->setSize(2)
            ->setAdHoc(true);

        return $this->render('@Admin/widget/_block.html.twig', [
            'widget' => $widget,
            'customize_enabled' => true,
        ]);
    }

    /**
     * Validation boundary for a merge pane definition coming from the
     * client (data-merge-def, itself just a straight JSON dump of what
     * the server rendered - trusted in spirit, but still untrusted
     * request input) - same defensive shape as LayoutConfig::fromArray():
     * never throws on malformed input, drops anything that doesn't fit
     * either the block-pane or the group-pane shape.
     *
     * @return array{type: string, label: ?string, icon: ?string, params: array}|array{label: ?string, icon: ?string, subItems: array}|null
     */
    private function decodePaneDefinition(?string $raw): ?array
    {
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }

        $data = \json_decode($raw, true);
        if (!\is_array($data)) {
            return null;
        }

        $label = \is_string($data['label'] ?? null) ? $data['label'] : '';
        $icon = \is_string($data['icon'] ?? null) ? $data['icon'] : null;

        if (\is_string($data['blockName'] ?? null) && '' !== $data['blockName']) {
            return [
                'type' => $data['blockName'],
                'label' => $label,
                'icon' => $icon,
                'params' => \is_array($data['params'] ?? null) ? $data['params'] : [],
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

            return ['label' => $label, 'icon' => $icon, 'subItems' => $subItems];
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
