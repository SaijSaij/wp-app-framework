<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Manifest;

use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Manifest\BuildException;
use WebCodingCat\WpAppFramework\Manifest\BuildResult;
use WebCodingCat\WpAppFramework\Manifest\Discovery\Source;
use WebCodingCat\WpAppFramework\Manifest\ManifestBuilder;
use WebCodingCat\WpAppFramework\Tests\Fixtures\ExternalParent;
use WebCodingCat\WpAppFramework\Tests\Fixtures\Valid;
use WebCodingCat\WpAppFramework\Tests\Support\Files;

final class ManifestBuilderTest extends TestCase
{
    public static function fixture(string $name): Source
    {
        return new Source(
            'WebCodingCat\\WpAppFramework\\Tests\\Fixtures\\' . $name . '\\',
            (string) realpath(__DIR__ . '/../../Fixtures/' . $name),
        );
    }

    public static function buildValid(): BuildResult
    {
        return new ManifestBuilder([self::fixture('Valid')])->build();
    }

    public function testStylesheetInTheAssetDirectoryIsRecorded(): void
    {
        $assets = sys_get_temp_dir() . '/wpaf-assets-' . bin2hex(random_bytes(4));

        try {
            self::assertFalse(self::buildValid()->hasStyles(), 'Without an asset directory.');
            $result = new ManifestBuilder([self::fixture('Valid')], assetDirectory: $assets)->build();
            self::assertFalse($result->hasStyles(), 'The asset directory does not exist.');
            self::assertSame($assets, $result->getAssetDirectory());

            mkdir($assets);
            touch($assets . '/styles.css');
            self::assertTrue(new ManifestBuilder([self::fixture('Valid')], assetDirectory: $assets)->build()->hasStyles());
        } finally {
            Files::remove($assets);
        }
    }

    public function testStylesheetInItsFormerPlaceIsABuildError(): void
    {
        $scripts = sys_get_temp_dir() . '/wpaf-scripts-' . bin2hex(random_bytes(4));
        mkdir($scripts);
        touch($scripts . '/styles.css');

        try {
            new ManifestBuilder([self::fixture('Valid')], scriptDirectory: $scripts)->build();
            self::fail('Expected a build error.');
        } catch (BuildException $exception) {
            self::assertSame([sprintf(
                'The stylesheet %s/styles.css is no longer built: move it to assets/styles.css in the root of the theme or plugin and make the paths in it relative to that directory (e.g. @source "../src").',
                $scripts,
            )], $exception->getErrors());
        } finally {
            Files::remove($scripts);
        }
    }

    public function testConcreteClassesAreCandidatesUnlessExcluded(): void
    {
        self::assertSame(
            [Valid\Clock::class, Valid\Dto::class, Valid\Greeter::class, Valid\Hooks::class, Valid\UnusedService::class],
            self::buildValid()->getCandidates(),
        );
    }

    public function testOnlyReachableServicesAreBuilt(): void
    {
        self::assertSame(
            [
                Valid\Clock::class => [],
                Valid\Greeter::class => ['clock' => Valid\Clock::class],
                Valid\Hooks::class => [],
            ],
            self::buildValid()->getServices(),
        );
    }

    public function testReportsAllErrorsWithClassAndParameterNames(): void
    {
        try {
            new ManifestBuilder([self::fixture('Broken')])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            $ns = 'WebCodingCat\\WpAppFramework\\Tests\\Fixtures\\Broken\\';
            $expected = [
                "Cannot autowire parameter \$logger of {$ns}InterfaceDependent::__construct(): {$ns}LoggerLike is an interface and cannot be injected (aliases are not supported).",
                "Cannot autowire parameter \$excluded of {$ns}ExcludedDependent::__construct(): {$ns}Excluded is marked #[NoService] and cannot be injected.",
                "Cannot autowire parameter \$name of {$ns}ScalarDependent::__construct(): type string is not a service and the parameter has no default value.",
                "Cannot autowire parameter \$storage of {$ns}OutsideDependent::__construct(): ArrayObject is outside the scanned PSR-4 directories of the project and the framework and cannot be injected.",
                "Cannot autowire parameter \$context of {$ns}InternalDependent::__construct(): WebCodingCat\\WpAppFramework\\Editor\\RenderContext is internal to the framework and cannot be injected into classes of a theme or plugin.",
                "Cannot autowire parameter \$posts of {$ns}InternalDependent::__construct(): WebCodingCat\\WpAppFramework\\Post\\PostStore is internal to the framework and cannot be injected into classes of a theme or plugin.",
                "Circular dependency: {$ns}CycleA -> {$ns}CycleB -> {$ns}CycleA",
                "{$ns}ExcludedWithHook::run() has #[Hook], but {$ns}ExcludedWithHook is marked #[NoService] and therefore not a service.",
                "{$ns}BrokenHooks::missingReturnType() has #[Hook] and needs a declared return type: void for an action, the filtered type for a filter.",
                "{$ns}BrokenHooks::staticHook() has #[Hook] and must be public and non-static.",
            ];

            foreach ($expected as $message) {
                self::assertContains($message, $exception->getErrors());
            }
            self::assertCount(count($expected), $exception->getErrors());
            self::assertStringStartsWith("Manifest build failed:\n - ", $exception->getMessage());
        }
    }

    public function testFileNotDeclaringItsPsr4ClassIsAnError(): void
    {
        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('Declared.php does not declare WebCodingCat\\WpAppFramework\\Tests\\Fixtures\\Mismatch\\Declared as expected by PSR-4.');

        new ManifestBuilder([self::fixture('Mismatch')])->build();
    }

    public function testClassesBuildingOnClassesOutsideTheProjectAreNotLoaded(): void
    {
        $result = new ManifestBuilder([self::fixture('ExternalParent')])->build();

        self::assertSame(
            [
                // Abstract, but excluded for the same reason: its subclasses must not be loaded either.
                ExternalParent\AbstractTable::class => 'WP_List_Table',
                ExternalParent\CachingModel::class => 'WordPress\\AiClient\\Common\\Contracts\\CachesDataInterface',
                ExternalParent\EventTable::class => 'WP_List_Table',
                ExternalParent\TagProcessor::class => 'WP_HTML_Tag_Processor',
                ExternalParent\UsesTrait::class => 'WordPress\\AiClient\\Common\\Traits\\WithDataCachingTrait',
                ExternalParent\Widget::class => 'WP_Widget',
            ],
            $result->getUnloadedClasses(),
        );
        foreach (array_keys($result->getUnloadedClasses()) as $class) {
            self::assertFalse(class_exists($class, false), $class . ' is not loaded.');
        }
        self::assertSame(
            [ExternalParent\Extension::class, ExternalParent\Storage::class, ExternalParent\Widgets::class],
            $result->getCandidates(),
        );
        self::assertSame([ExternalParent\Storage::class => [], ExternalParent\Widgets::class => []], $result->getServices());
        // Whether such a class stays outside depends on its content.
        self::assertContains((string) realpath(__DIR__ . '/../../Fixtures/ExternalParent/Widget.php'), $result->getTrackedFiles());
    }

    public function testClassesBuildingOnClassesOutsideTheProjectAreNoServicesAndCarryNoFrameworkAttributes(): void
    {
        try {
            new ManifestBuilder([self::fixture('ExternalParentBroken')])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            $ns = 'WebCodingCat\\WpAppFramework\\Tests\\Fixtures\\ExternalParentBroken\\';
            self::assertSame([
                "{$ns}Widget has #[Hook], but the build does not load it: it builds on WP_Widget, which is outside the project, its Composer dependencies and PHP. Framework attributes have no effect there; move them to a service that creates the class.",
                "Cannot autowire parameter \$widget of {$ns}NeedsWidget::__construct(): {$ns}Widget is a class building on WP_Widget, which is outside the project, its Composer dependencies and PHP, and therefore not loaded by the build and cannot be injected; create it yourself where you need it (new Widget(…) in a hook, for example).",
            ], $exception->getErrors());
            self::assertFalse(class_exists($ns . 'Widget', false));
        }
    }

    public function testLoadingAClassTheBuildDoesNotLoadIsABuildError(): void
    {
        $autoloaders = spl_autoload_functions();

        try {
            new ManifestBuilder([self::fixture('ExternalParentAccess')])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            self::assertSame([
                'The build needs WebCodingCat\\WpAppFramework\\Tests\\Fixtures\\ExternalParentAccess\\Widget, but does not load it: it builds on WP_Widget, which is outside the project, its Composer dependencies and PHP. Use it only in code that runs in WordPress, not as a type or attribute argument the build reads.',
            ], $exception->getErrors());
        }
        self::assertSame($autoloaders, spl_autoload_functions(), 'The guard is removed after the build.');
    }

    public function testFrameworkSourceIsTheFrameworkSrcDirectory(): void
    {
        $source = ManifestBuilder::frameworkSource();

        self::assertSame('WebCodingCat\\WpAppFramework\\', $source->getPrefix());
        self::assertSame(realpath(__DIR__ . '/../../../src'), $source->getDirectory());
    }

    public function testFrameworkServicesAreThePredefinedOnesAndItsTemplateFunctions(): void
    {
        $predefined = [\WebCodingCat\WpAppFramework\Theme::class, \WebCodingCat\WpAppFramework\Twig\TemplateRenderer::class, \WebCodingCat\WpAppFramework\Editor\RenderContext::class, \WebCodingCat\WpAppFramework\Cache\CacheStore::class, \WebCodingCat\WpAppFramework\Cron\CronScheduler::class];
        $result = new ManifestBuilder([ManifestBuilder::frameworkSource()], [], $predefined)->build();

        self::assertSame(
            [
                \WebCodingCat\WpAppFramework\Api\PluginTemplateFunctions::class,
                \WebCodingCat\WpAppFramework\Asset\AssetTemplateFunctions::class,
                \WebCodingCat\WpAppFramework\Asset\Assets::class,
                \WebCodingCat\WpAppFramework\Asset\MediaLibrary::class,
                \WebCodingCat\WpAppFramework\Block\BlockTemplateFunctions::class,
                \WebCodingCat\WpAppFramework\Cache\CacheStore::class,
                \WebCodingCat\WpAppFramework\Cron\CronScheduler::class,
                \WebCodingCat\WpAppFramework\Editor\EditorTemplateFunctions::class,
                \WebCodingCat\WpAppFramework\Editor\RenderContext::class,
                \WebCodingCat\WpAppFramework\I18n\TranslationTemplateFunctions::class,
                \WebCodingCat\WpAppFramework\I18n\Translator::class,
                \WebCodingCat\WpAppFramework\Post\PostStore::class,
                \WebCodingCat\WpAppFramework\Post\PostTemplateFunctions::class,
                \WebCodingCat\WpAppFramework\Theme::class,
                \WebCodingCat\WpAppFramework\Twig\TemplateRenderer::class,
                \WebCodingCat\WpAppFramework\Twig\WordPressTemplateFunctions::class,
                \WebCodingCat\WpAppFramework\Value\LinkTemplateFunctions::class,
                \WebCodingCat\WpAppFramework\View\ViewData::class,
            ],
            $result->getCandidates(),
        );
        self::assertSame(
            [
                \WebCodingCat\WpAppFramework\Api\PluginTemplateFunctions::class => [],
                \WebCodingCat\WpAppFramework\Asset\AssetTemplateFunctions::class => ['assets' => \WebCodingCat\WpAppFramework\Asset\Assets::class],
                \WebCodingCat\WpAppFramework\Block\BlockTemplateFunctions::class => [
                    'context' => \WebCodingCat\WpAppFramework\Editor\RenderContext::class,
                ],
                \WebCodingCat\WpAppFramework\Editor\EditorTemplateFunctions::class => ['context' => \WebCodingCat\WpAppFramework\Editor\RenderContext::class],
                \WebCodingCat\WpAppFramework\I18n\TranslationTemplateFunctions::class => ['translator' => \WebCodingCat\WpAppFramework\I18n\Translator::class],
                \WebCodingCat\WpAppFramework\Post\PostTemplateFunctions::class => [
                    'store' => \WebCodingCat\WpAppFramework\Post\PostStore::class,
                    'context' => \WebCodingCat\WpAppFramework\Editor\RenderContext::class,
                ],
                \WebCodingCat\WpAppFramework\Twig\WordPressTemplateFunctions::class => ['context' => \WebCodingCat\WpAppFramework\Editor\RenderContext::class],
                \WebCodingCat\WpAppFramework\Value\LinkTemplateFunctions::class => [],
            ],
            $result->getServices(),
        );
        self::assertSame([], $result->getHooks());
        self::assertSame([], $result->getRoutes());
        self::assertSame([], $result->getBlocks());
    }
}
