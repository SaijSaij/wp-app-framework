<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Setting;

use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Asset\OrphanScripts;
use WebCodingCat\WpAppFramework\Manifest\BuildException;
use WebCodingCat\WpAppFramework\Manifest\BuildResult;
use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Manifest\ManifestBuilder;
use WebCodingCat\WpAppFramework\Manifest\ManifestGenerator;
use WebCodingCat\WpAppFramework\Project\ProjectKind;
use WebCodingCat\WpAppFramework\Setting\SettingsPage;
use WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsScript\Design;
use WebCodingCat\WpAppFramework\Tests\Support\Files;
use WebCodingCat\WpAppFramework\Tests\Support\Recorded;
use WebCodingCat\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;

/**
 * Settings scripts (Design.settings.js next to a #[SettingsGroup] class): found by the build, loaded on the settings
 * page after the page script, and errors for a settings script without a group.
 */
final class SettingsScriptTest extends TestCase
{
    private string $build;

    protected function setUp(): void
    {
        $this->build = sys_get_temp_dir() . '/wp-app-framework-build-' . bin2hex(random_bytes(4));
        mkdir($this->build . '/vendor/wp-app-framework', 0777, true);
        mkdir($this->build . '/Setting', 0777, true);
    }

    protected function tearDown(): void
    {
        Files::remove($this->build);
        unset($GLOBALS['wp_app_framework_test_scripts'], $GLOBALS['wp_app_framework_test_styles'], $GLOBALS['wp_app_framework_test_pages']);
    }

    private static function build(?string $scriptDirectory): BuildResult
    {
        $fixture = ManifestBuilderTest::fixture('SettingsScript');

        return new ManifestBuilder(
            [$fixture, ManifestBuilder::frameworkSource()],
            scriptDirectory: $scriptDirectory,
            slug: 'my-theme',
            name: 'Fixture Theme',
            namespace: 'my-theme',
        )->build();
    }

    public function testTheBuildFindsTheScriptNextToTheGroup(): void
    {
        $directory = ManifestBuilderTest::fixture('SettingsScript')->getDirectory();
        $groups = array_column(self::build($directory)->getSettings(), 'script', 'name');

        self::assertSame(['color_schemes' => 'Design.settings', 'plain' => null], $groups);
        self::assertSame([], OrphanScripts::errors(self::build($directory)->getClasses(), $directory));
    }

    public function testAScriptOutsideTheScriptDirectoryIsAnError(): void
    {
        try {
            self::build(null);
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            self::assertSame([sprintf(
                'The settings script %s/Design.settings.js of %s is outside the script directory (none), which the build of the project compiles.',
                ManifestBuilderTest::fixture('SettingsScript')->getDirectory(),
                Design::class,
            )], $exception->getErrors());
        }
    }

    public function testASettingsScriptWithoutGroupIsAnError(): void
    {
        $directory = sys_get_temp_dir() . '/wpaf-orphans-' . bin2hex(random_bytes(4));
        mkdir($directory, 0777, true);
        $directory = (string) realpath($directory);
        touch($directory . '/Colors.settings.js');
        touch($directory . '/Colors.php');

        try {
            self::assertSame(
                [sprintf('The settings script %s/Colors.settings.js belongs to no settings group: a settings script is loaded only next to the class of a #[SettingsGroup] (Design.php → Design.settings.js).', $directory)],
                OrphanScripts::errors([], $directory),
            );
        } finally {
            Files::remove($directory);
        }
    }

    public function testTheSettingsPageLoadsTheScriptsOfTheGroupsAfterItsOwn(): void
    {
        $directory = ManifestBuilderTest::fixture('SettingsScript')->getDirectory();
        $file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        file_put_contents($file, new ManifestGenerator()->generate(self::build($directory)));
        $manifest = Manifest::load($file);
        unlink($file);

        file_put_contents($this->build . '/vendor/wp-app-framework/settings.asset.php', "<?php return ['dependencies' => ['wp-components'], 'version' => 'v1'];");
        file_put_contents($this->build . '/vendor/wp-app-framework/settings.css', '');
        file_put_contents($this->build . '/Design.settings.asset.php', "<?php return ['dependencies' => ['my-theme-wp-app-framework-settings'], 'version' => 'v2'];");

        $page = new SettingsPage('my-theme', ProjectKind::Theme, 'Fixture Theme', $manifest['settings']['groups'], $this->build, 'https://example.test/build');
        $page->addPage();
        $page->enqueue('appearance_page_my-theme-settings');

        $scripts = Recorded::scripts();
        self::assertSame(['my-theme-wp-app-framework-settings', 'my-theme-settings-color-schemes'], array_keys($scripts));
        $script = $scripts['my-theme-settings-color-schemes'];
        self::assertSame('https://example.test/build/Design.settings.js', $script['src']);
        self::assertSame(['my-theme-wp-app-framework-settings'], $script['deps']);
        self::assertTrue($script['enqueued']);
    }
}
