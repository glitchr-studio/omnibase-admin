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
 * Backs the dashboard's "+ Add widget" palette: list what's available, and
 * render a fresh instance's markup so the client can insert it without
 * reinventing server-rendered Twig client-side. Both actions are GET, no
 * CSRF - read-only, neither persists anything (persistence still only ever
 * happens through the existing batched admin_layout_save POST on "Done").
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
