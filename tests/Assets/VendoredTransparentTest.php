<?php

namespace Tests\Base\Admin\Assets;

use PHPUnit\Framework\TestCase;

/**
 * The back office's transparentjs is the library's own file, copied as it is
 * (public/js/README.md): its first line names the version and the commit, the
 * stylesheet and the README name the same, the library's code says that version
 * - and it is at least 3.0.31, which keeps the page's canvases going past one
 * without an id (omnibase/music's player wave stopped the others being kept).
 */
final class VendoredTransparentTest extends TestCase
{
    public function testTheCopyIsOneVersionOfTheLibraryNamedEverywhere(): void
    {
        $root = \dirname(__DIR__, 2);
        $js = file_get_contents($root.'/public/js/transparent.js');
        $css = file_get_contents($root.'/public/css/transparent.css');
        $readme = file_get_contents($root.'/public/js/README.md');

        self::assertSame(1, preg_match('~^/\*! @glitchr/transparentjs (\d+\.\d+\.\d+) - github\.com/glitchr-studio/transparentjs ([0-9a-f]{7,}), src/js/transparent\.js copied as it is~', $js, $m), 'the first line names the version and the commit');
        [, $version, $commit] = $m;

        self::assertStringStartsWith("/*! @glitchr/transparentjs $version - github.com/glitchr-studio/transparentjs $commit, src/css/index.scss", $css);
        self::assertStringContainsString("Currently vendored at transparentjs $version (glitchr-studio/transparentjs $commit)", $readme);
        self::assertStringContainsString("Transparent.version = '$version';", $js, 'the library\'s code, unedited, says the same version');
        self::assertTrue(version_compare($version, '3.0.31', '>='), 'a canvas without an id must not stop the others being kept');
        self::assertStringNotContainsString('Unexpected canvas without ID found', $js);
    }
}
