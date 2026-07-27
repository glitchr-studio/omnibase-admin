<?php

namespace Base\Admin\Layout;

use Base\Service\Localizer;
use Base\Service\SettingBagInterface;
use Throwable;

/**
 * Persists LayoutConfig via the existing SettingBag path/value store - no
 * new entity. Two real gotchas, both handled here so callers don't have to
 * think about them:
 *
 * 1. SettingBag::set()/get() resolve to the CURRENT REQUEST locale when
 *    none is passed explicitly - saving from /fr/admin and reading from
 *    /en/admin would silently diverge. Always pin the default locale.
 * 2. SettingBag::set() invalidates the WHOLE compiled settings snapshot on
 *    every call, so the customize UI must batch one save on "Done", never
 *    one write per drag - that's a client-side contract, not enforced here.
 */
class LayoutStore
{
    private const PATH_PREFIX = 'admin.layout.';

    public function __construct(
        protected readonly SettingBagInterface $settingBag,
    ) {
    }

    public function get(string $scope): LayoutConfig
    {
        try {
            $raw = $this->settingBag->getScalar(self::PATH_PREFIX . $scope, Localizer::getDefaultLocale());
        } catch (Throwable) {
            return LayoutConfig::new();
        }

        return LayoutConfig::fromArray($raw);
    }

    public function save(string $scope, LayoutConfig $config): void
    {
        $this->settingBag->set(self::PATH_PREFIX . $scope, $config->toArray(), Localizer::getDefaultLocale());
    }
}
