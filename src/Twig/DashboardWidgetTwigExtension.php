<?php

namespace Base\Admin\Twig;

use Base\Admin\Widget\DashboardWidgetTypeInterface;
use Base\Admin\Widget\DashboardWidgetTypeRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DashboardWidgetTwigExtension extends AbstractExtension
{
    public function __construct(
        protected readonly DashboardWidgetTypeRegistry $registry,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('dashboard_widget_type', $this->getWidgetType(...)),
        ];
    }

    public function getWidgetType(string $name): ?DashboardWidgetTypeInterface
    {
        return $this->registry->get($name);
    }
}
