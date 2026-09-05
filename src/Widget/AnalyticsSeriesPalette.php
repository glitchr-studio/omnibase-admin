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

    /**
     * The subset of SERIES that are traffic SOURCES - the three that
     * partition every page view between them (a view is human, bot or AI,
     * never two of those). uniqueVisitors/uniqueUsers are deliberately not
     * here: they count people rather than views, so they are their own
     * stats and are never summed into a page-view total.
     *
     * This is what makes "page views" answerable per-source at all, and it
     * lives here rather than being re-listed per widget because three
     * separate copies had already started drifting - EntityViewsWidgetType
     * kept its own, the legend handler in layout.html.twig kept a third as
     * a lookup object.
     */
    public const SOURCE_KEYS = ['pageViewsHuman', 'pageViewsBot', 'pageViewsAi'];

    /**
     * What a widget shows before anyone has touched its legend: everything
     * EXCEPT bot and AI traffic.
     *
     * "Vues" used to mean Analytics' combined `pageViews` column, which is
     * human + bot + AI, so every view count on the dashboard silently
     * counted crawlers. That is not a rounding error - on beta today bots
     * are the MAJORITY of traffic (1632 bot against 1352 human), and on
     * production 443 bot against 947 human - so the headline number was
     * reporting roughly double the real audience on one host and nearly
     * half again on the other.
     *
     * Human-only is therefore the default rather than a filter to opt into.
     * Bot and AI are not removed, just not counted until asked for: both
     * still have their own legend entry, one click re-shows the line, and
     * the total then re-adds it (see recomputeStatsFromChart in
     * layout.html.twig) - which is the property that makes this safe, since
     * the number is always exactly the sum of the lines you can see.
     */
    public const DEFAULT_VISIBLE = ['pageViewsHuman', 'uniqueVisitors', 'uniqueUsers'];
}
