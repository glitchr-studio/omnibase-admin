<?php

namespace Base\Admin\Widget;

use Base\Admin\Config\Menu\MenuItem;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The "Generic" widget in the "+ Add widget" palette - a free-form text
 * block, editable in place (see welcome.html.twig's own data-widget-text,
 * and layout.html.twig's input handler for it). getName() stays 'welcome'
 * (the class/file keep their original name too) even though this is no
 * longer just the dashboard's fixed greeting card - every already-stored
 * widget (code-defined default instance AND any ad-hoc one a superadmin
 * added) references that exact string as its blockName; changing it would
 * silently orphan every existing one (see _block.html.twig's own "unknown
 * blockName renders an empty shell" comment for what that'd look like).
 * Only the user-facing label changed (getDefaultLabel()), not the
 * internal identifier.
 *
 * params.text holds the custom text once edited; falls back to the
 * original "Bienvenue dans votre administration..." greeting (translated)
 * for the one default instance that's never been touched, so the existing
 * live dashboard doesn't visibly change until a superadmin actually edits
 * it or adds a new instance.
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
        return 'dashboard.generic_title';
    }

    public function getDefaultIcon(): ?string
    {
        return 'fa-solid fa-align-left';
    }

    public function getTemplateVars(MenuItem $widget): array
    {
        $text = $widget->getParams()['text'] ?? null;

        return [
            'welcomeText' => \is_string($text) && '' !== $text
                ? $text
                : $this->translator->trans('dashboard.welcome', [], 'admin'),
        ];
    }
}
