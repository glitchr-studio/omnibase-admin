<?php

namespace Base\Admin\Widget;

/**
 * The single source of truth for the analytics widget's per-series identity
 * (translation key + color) - shared by AnalyticsController::breakdown()
 * (the range picker's JSON endpoint) and AnalyticsCardWidgetType (the
 * initial server-rendered paint), so the two can never drift apart the way
 * two hand-copied color maps eventually do.
 *
 * Order and colors are both load-bearing, not decoration: this is slots
 * 1-5 of the design system's validated 8-hue categorical ramp, kept in
 * THIS sequence - `node scripts/validate_palette.js` (dataviz skill)
 * confirms it passes the adjacent-pair CVD/contrast checks in both light
 * and dark for this exact order, not for any color individually.
 * Reordering the keys, swapping a color, or inserting a new series
 * between two existing ones invalidates that guarantee and needs
 * re-validating, not just re-eyeballing.
 */
final class AnalyticsSeriesPalette
{
    public const SERIES = [
        'pageViewsHuman' => ['label' => 'analytics.label.page_views_human', 'color' => '#2a78d6', 'colorDark' => '#3987e5'],
        'pageViewsBot' => ['label' => 'analytics.label.page_views_bot', 'color' => '#eb6834', 'colorDark' => '#d95926'],
        'pageViewsAi' => ['label' => 'analytics.label.page_views_ai', 'color' => '#1baf7a', 'colorDark' => '#199e70'],
        'uniqueVisitors' => ['label' => 'analytics.label.unique_visitors', 'color' => '#eda100', 'colorDark' => '#c98500'],
        'uniqueUsers' => ['label' => 'analytics.label.unique_users', 'color' => '#e87ba4', 'colorDark' => '#d55181'],
    ];
}
