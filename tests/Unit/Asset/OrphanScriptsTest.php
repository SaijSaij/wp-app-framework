<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Asset\OrphanScripts;
use WebCodingCat\WpAppFramework\Tests\Support\Files;
use WebCodingCat\WpAppFramework\Tests\Unit\View\ViewBuildTest;

/**
 * Editor and view scripts in src/ without a block or block support next to them.
 */
final class OrphanScriptsTest extends TestCase
{
    public function testScriptsNextToBlocksAndSupportsBelongToThem(): void
    {
        $result = ViewBuildTest::buildValid();

        self::assertSame([], OrphanScripts::errors($result->getClasses(), $result->getScriptDirectory()));
    }

    public function testScriptsWithoutBlockOrSupportAreErrors(): void
    {
        $directory = sys_get_temp_dir() . '/wpaf-orphans-' . bin2hex(random_bytes(4));
        mkdir($directory . '/Menu', 0777, true);
        $directory = (string) realpath($directory);
        touch($directory . '/Menu/Menu.view.js');
        touch($directory . '/Menu/Menu.php');
        touch($directory . '/Format.editor.js');
        touch($directory . '/helper.js');

        try {
            self::assertSame([
                sprintf('The editor script %s/Format.editor.js belongs to no block or block support: a script in %s is loaded only next to the class of a block or block support (Hero.php → Hero.editor.js). Move a script without a block to assets/ in the root of the theme or plugin.', $directory, $directory),
                sprintf('The view script %s/Menu/Menu.view.js belongs to no block or block support: a script in %s is loaded only next to the class of a block or block support (Hero.php → Hero.view.js). Move a script without a block to assets/ in the root of the theme or plugin.', $directory, $directory),
            ], OrphanScripts::errors(ViewBuildTest::buildValid()->getClasses(), $directory));
        } finally {
            Files::remove($directory);
        }
    }

    public function testNoScriptDirectoryHasNoErrors(): void
    {
        self::assertSame([], OrphanScripts::errors([], null));
        self::assertSame([], OrphanScripts::errors([], '/does/not/exist'));
    }
}
