<?php

namespace Base\Admin\Widget;

use Base\Admin\Config\Menu\MenuItem;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The dashboard's greeting card - "Bienvenue dans votre administration...",
 * previously hardcoded straight into dashboard.html.twig's content_header
 * block (unhideable, unmovable, no size of its own). Same
 * DashboardWidgetTypeInterface treatment as analytics_card now: one
 * default instance registered by AbstractDashboardController::
 * configureDashboardBlockItems(), hideable/reorderable/resizable like any
 * other card, re-addable via the palette if a superadmin removes it.
 *
 * Deliberately no title - getDefaultLabel() only names this widget in the
 * "+ Add widget" palette entry, welcome.html.twig itself never renders
 * widget.label as a heading the way analytics_card does with its own -
 * the greeting text IS the whole card, nothing else in it needs a title
 * to sit under.
 */
final class WelcomeWidgetType implements PaletteDashboardWidgetTypeInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public static function getName(): string
    {
        return 'welcome';
    }

    public function getTemplate(): string
    {
        return '@Admin/widget/welcome.html.twig';
    }

    public function getDefaultLabel(): string
    {
        return 'dashboard.welcome_title';
    }

    public function getDefaultIcon(): ?string
    {
        return 'fa-solid fa-hand-wave';
    }

    public function getTemplateVars(MenuItem $widget): array
    {
        return [
            'welcomeText' => $this->translator->trans('dashboard.welcome', [], 'admin'),
        ];
    }
}
