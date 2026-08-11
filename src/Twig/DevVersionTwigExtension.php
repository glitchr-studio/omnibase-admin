<?php

namespace Base\Admin\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Dev-only suffix for transparentJS's cache_version (see layout.html.twig's
 * Transparent.ready call): deploy_version() folds in the app's and each
 * vendor/glitchr repo's git HEAD, which is exactly right for deploys - but
 * during live development the vendor edits are UNCOMMITTED, HEAD never
 * moves, and every already-visited admin page kept replaying its SPA-cached
 * pre-fix HTML/CSS indefinitely (reported live, repeatedly, as fixes
 * "still broken" mid-iteration). In debug mode this appends the max mtime
 * of this bundle's own hot sources, so ANY saved edit purges the client
 * cache on the next navigation; in prod it contributes nothing at all.
 */
final class DevVersionTwigExtension extends AbstractExtension
{
    private ?string $version = null;

    public function __construct(
        private readonly bool $debug,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_dev_version', $this->getVersion(...)),
        ];
    }

    public function getVersion(): string
    {
        if (!$this->debug) {
            return '';
        }
        if (null !== $this->version) {
            return $this->version;
        }

        $root = \dirname(__DIR__, 2);
        $max = 0;
        foreach (['templates', 'public/css', 'public/js', 'translations', 'src'] as $dir) {
            $path = $root . '/' . $dir;
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $max = max($max, $file->getMTime());
            }
        }

        return $this->version = '.' . $max;
    }
}
