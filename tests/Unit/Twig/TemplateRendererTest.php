<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Tests\Fixtures\TwigValid\TextHelpers;
use Saij\WpAppFramework\Tests\Support\Files;
use Saij\WpAppFramework\Theme;
use Saij\WpAppFramework\Twig\TemplateOverrides;
use Saij\WpAppFramework\Twig\TemplateRenderer;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

final class TemplateRendererTest extends TestCase
{
    private const string TEMPLATES = __DIR__ . '/../../Fixtures/Templates';

    private string $cache;

    protected function setUp(): void
    {
        $this->cache = sys_get_temp_dir() . '/wpaf-twig-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->cache)) {
            return;
        }
        Files::remove($this->cache);
    }

    private function renderer(string $stylesheet = 'child', bool $development = true): TemplateRenderer
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        file_put_contents($file, new ManifestGenerator()->generate(TemplateCallableCollectorTest::buildValid()));
        $manifest = Manifest::load($file);
        unlink($file);

        $templates = (string) realpath(self::TEMPLATES);

        return new TemplateRenderer(
            'theme',
            $templates . '/theme',
            Theme::templateOverrides($templates . '/theme', $templates . '/' . $stylesheet),
            $templates . '/theme/src',
            'theme',
            $templates . '/framework/src',
            $this->cache,
            $development,
            $manifest['templateCallables'],
            [],
            new Container($manifest['services']),
        );
    }

    public function testNamesWithoutNamespaceSearchChildThemeFramework(): void
    {
        self::assertSame('child|theme|framework|framework-only', $this->renderer()->render('Chain.twig'));
    }

    public function testWithoutChildTheThemeWins(): void
    {
        self::assertSame('theme|theme|framework|framework-only', $this->renderer('theme')->render('Chain.twig'));
    }

    public function testChildWithoutTemplateDirectoryIsSkipped(): void
    {
        self::assertSame('theme', $this->renderer('childWithoutTemplates')->render('Shared/Level.twig'));
    }

    public function testNamespacesAddressExactlyOneLevel(): void
    {
        $renderer = $this->renderer();

        self::assertSame('theme', $renderer->render('@theme/Shared/Level.twig'));
        self::assertSame('framework', $renderer->render('@framework/Shared/Level.twig'));
    }

    public function testMissingTemplateNamesTheSearchedDirectories(): void
    {
        $templates = (string) realpath(self::TEMPLATES);

        $this->expectException(LoaderError::class);
        $this->expectExceptionMessage(sprintf(
            'Unable to find template "Missing.twig" (looked into: %s/child/src, %s/theme/src, %s/framework/src).',
            $templates,
            $templates,
            $templates,
        ));

        $this->renderer()->render('Missing.twig');
    }

    public function testEscapingAndHtmlCallables(): void
    {
        $output = $this->renderer()->render('Escape.twig', [
            'value' => '<x>',
            'label' => 'L&L',
            'name' => 'Ann',
            'items' => [1, 2, 3],
        ]);

        self::assertSame(
            '&lt;x&gt;|<b>L&L</b>|Hello &lt;Ann&gt;|&lt;i&gt;&lt;x&gt;&lt;/i&gt;|&lt;em&gt;&lt;x&gt;&lt;/em&gt;|<i>&lt;x&gt;</i>|3',
            $output,
        );
    }

    public function testHtmlFilterReceivesEscapedValueUnlessItIsSafe(): void
    {
        self::assertSame('<i>&lt;x&gt;</i>|<i><x></i>', $this->renderer()->render('PreEscape.twig', ['value' => '<x>']));
    }

    public function testServicesAreCreatedWhenATemplateCallsThem(): void
    {
        $renderer = $this->renderer();
        $before = TextHelpers::getInstances();

        $renderer->render('Plain.twig', ['value' => 'x']);
        self::assertSame($before, TextHelpers::getInstances());

        $renderer->render('Escape.twig', ['value' => 'x', 'label' => 'l', 'name' => 'n', 'items' => []]);
        $renderer->render('Escape.twig', ['value' => 'y', 'label' => 'l', 'name' => 'n', 'items' => []]);
        self::assertSame($before + 1, TextHelpers::getInstances());
    }

    public function testUndefinedVariablesAreErrors(): void
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('Variable "missing" does not exist');

        $this->renderer()->render('Strict.twig');
    }

    public function testDumpIsAvailableInDevelopmentMode(): void
    {
        self::assertStringContainsString('dumped', $this->renderer()->render('Dump.twig', ['value' => 'dumped']));
    }

    public function testCompiledTemplatesAreWrittenToTheCacheDirectory(): void
    {
        // A template compiled earlier in this process is not compiled again, so this test uses its own.
        $this->renderer()->render('Cached.twig');

        $compiled = 0;
        foreach (Files::below($this->cache) as $file) {
            $compiled += $file->getExtension() === 'php' ? 1 : 0;
        }
        self::assertGreaterThan(0, $compiled);
    }

    public function testReleaseModeRendersIntoTheGivenCacheDirectory(): void
    {
        self::assertSame('plain &lt;x&gt;', $this->renderer('theme', false)->render('Plain.twig', ['value' => '<x>']));

        $compiled = 0;
        foreach (Files::below($this->cache) as $file) {
            $compiled += $file->getExtension() === 'php' ? 1 : 0;
        }
        self::assertGreaterThan(0, $compiled);
    }

    public function testDumpIsNotAvailableInReleaseMode(): void
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Unknown "dump" function');

        $this->renderer('theme', false)->render('Dump.twig', ['value' => 'dumped']);
    }

    public function testOverridesBetweenLevelsAreFound(): void
    {
        $templates = (string) realpath(self::TEMPLATES);

        self::assertSame(['Shared/Level.twig'], TemplateOverrides::find($templates . '/theme/src', $templates . '/framework/src'));
        self::assertSame([], TemplateOverrides::find($templates . '/childWithoutTemplates/src', $templates . '/theme/src'));
    }
}
