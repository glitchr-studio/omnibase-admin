<?php

namespace Base\Admin\Settings;

/**
 * The sections of the system pages, gathered from every service tagged
 * base.admin.settings_section (SettingsSectionInterface), in priority order.
 */
final class SettingsSections
{
    /** @param iterable<SettingsSectionInterface> $sections */
    public function __construct(private readonly iterable $sections = [])
    {
    }

    /**
     * Every field of a page, in the sections' order; a path declared again
     * further down (the application's section, at a lower priority) keeps
     * its place and takes the later options - a label changed, a field made
     * required.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fields(string $page): array
    {
        $fields = [];
        foreach ($this->sections as $section) {
            if ($section->getPage() !== $page) {
                continue;
            }
            $fields = array_replace($fields, $section->getFields());
        }

        return $fields;
    }
}
