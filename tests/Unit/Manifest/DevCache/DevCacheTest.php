<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Manifest\DevCache;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Tests\Support\Files;

final class DevCacheTest extends TestCase
{
    private string $theme;
    private string $cache;

    protected function setUp(): void
    {
        $root = sys_get_temp_dir() . '/wpaf-dev-cache-' . bin2hex(random_bytes(4));
        mkdir($root . '/theme/src', 0777, true);
        $this->theme = (string) realpath($root . '/theme');
        $this->cache = $root . '/cache';

        file_put_contents($this->theme . '/composer.json', json_encode(
            ['type' => 'wordpress-theme', 'autoload' => ['psr-4' => ['DevCacheProbe\\' => 'src/']]],
            JSON_THROW_ON_ERROR,
        ));
        file_put_contents($this->theme . '/style.css', "/*\nTheme Name: Probe\n*/\n");
        $this->writeHooks("return 'first';", '');
    }

    protected function tearDown(): void
    {
        Files::remove(dirname($this->theme));
    }

    private function writeHooks(string $body, string $extraMethod): void
    {
        $this->writeSource('Hooks.php', <<<PHP
            <?php

            declare(strict_types=1);

            namespace DevCacheProbe;

            use Saij\\WpAppFramework\\Hook\\Hook;

            final class Hooks
            {
                #[Hook('probe_filter')]
                public function filter(string \$value): string
                {
                    {$body}
                }
            {$extraMethod}
            }
            PHP);
    }

    private function writeSource(string $name, string $code): void
    {
        $file = $this->theme . '/src/' . $name;
        file_put_contents($file, $code);
        // Newer than the cached manifest, so the change is always inspected.
        touch($file, time() + 10);
    }

    /**
     * @phpstan-impure Runs the dev cache in a new process; the result depends on the files.
     */
    private function probe(string $mode): string
    {
        $command = [PHP_BINARY, __DIR__ . '/../../../bin/dev-cache-probe.php', $mode, $this->cache, $this->theme];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        proc_close($process);

        return $output;
    }

    public function testBuildsOnceAndReusesTheCache(): void
    {
        self::assertSame('stale', $this->probe('check'));
        self::assertSame('probe_filter', $this->probe('load'));
        self::assertFileExists($this->cache . '/manifest.php');
        self::assertFileExists($this->cache . '/manifest.meta.php');
        self::assertSame('fresh', $this->probe('check'));
    }

    public function testMetaFileContainsNoAbsolutePaths(): void
    {
        $this->probe('load');
        $meta = (string) file_get_contents($this->cache . '/manifest.meta.php');

        self::assertStringNotContainsString($this->theme, $meta);
        self::assertStringNotContainsString((string) realpath(__DIR__ . '/../../../..'), $meta);
        self::assertStringContainsString("'project',", $meta);
        self::assertStringContainsString("'src/Hooks.php',", $meta);
        self::assertStringContainsString("'framework',", $meta);
    }

    public function testMethodBodyChangeKeepsTheCache(): void
    {
        $this->probe('load');
        $this->writeHooks("return 'second';", '');

        self::assertSame('fresh', $this->probe('check'));
    }

    public function testSignatureChangeRebuilds(): void
    {
        $this->probe('load');
        $this->writeHooks("return 'first';", <<<'PHP'

                #[Hook('probe_action')]
                public function action(): void
                {
                }
            PHP);

        self::assertSame('stale', $this->probe('check'));
        self::assertSame('probe_filter,probe_action', $this->probe('load'));
        self::assertSame('fresh', $this->probe('check'));
    }

    public function testChangedPermissionCallbackRebuilds(): void
    {
        $controller = static fn(string $callback): string => <<<PHP
            <?php

            declare(strict_types=1);

            namespace DevCacheProbe;

            use Saij\\WpAppFramework\\Rest\\HttpMethod;
            use Saij\\WpAppFramework\\Rest\\Permission\\Callback;
            use Saij\\WpAppFramework\\Rest\\RestController;
            use Saij\\WpAppFramework\\Rest\\RestRoute;

            #[RestController('probe/v1')]
            final class Controller
            {
                #[RestRoute('/check', HttpMethod::Get, new Callback(self::{$callback}(...)))]
                public function check(): bool
                {
                    return true;
                }

                public static function first(): bool
                {
                    return true;
                }

                public static function second(): bool
                {
                    return false;
                }
            }
            PHP;

        $this->writeSource('Controller.php', $controller('first'));
        $this->probe('load');
        self::assertSame('fresh', $this->probe('check'));

        $this->writeSource('Controller.php', $controller('second'));
        self::assertSame('stale', $this->probe('check'));
    }

    public function testNewEnumCaseAndChangedDefaultRebuild(): void
    {
        $enum = static fn(string $cases): string => "<?php\n\nnamespace DevCacheProbe;\n\nenum Level: string\n{\n{$cases}}\n";
        $attributes = static fn(string $default): string => "<?php\n\nnamespace DevCacheProbe;\n\nfinal readonly class Attributes\n{\n    public function __construct(public Level \$level = Level::{$default})\n    {\n    }\n}\n";

        $this->writeSource('Level.php', $enum("    case Low = 'low';\n"));
        $this->writeSource('Attributes.php', $attributes('Low'));
        $this->probe('load');
        self::assertSame('fresh', $this->probe('check'));

        // Enum cases are constants, not methods; they still define the schema.
        $this->writeSource('Level.php', $enum("    case Low = 'low';\n    case High = 'high';\n"));
        self::assertSame('stale', $this->probe('check'));

        $this->probe('load');
        $this->writeSource('Attributes.php', $attributes('High'));
        self::assertSame('stale', $this->probe('check'));
    }

    public function testEditorChangesRebuild(): void
    {
        $enum = static fn(string $label): string => "<?php\n\nnamespace DevCacheProbe;\n\nuse Saij\\WpAppFramework\\Block\\Labeled;\n\nenum Tone: string implements Labeled\n{\n    case Light = 'light';\n\n    public function label(): string\n    {\n        return '{$label}';\n    }\n}\n";
        $attributes = static fn(string $label): string => "<?php\n\nnamespace DevCacheProbe;\n\nuse Saij\\WpAppFramework\\Block\\Label;\n\nfinal readonly class Attributes\n{\n    public function __construct(#[Label('{$label}')] public Tone \$tone = Tone::Light)\n    {\n    }\n}\n";

        $this->writeSource('Tone.php', $enum('Bright'));
        $this->writeSource('Attributes.php', $attributes('Tone'));
        $this->probe('load');
        self::assertSame('fresh', $this->probe('check'));

        // Enum labels come from a method body.
        $this->writeSource('Tone.php', $enum('Light'));
        self::assertSame('stale', $this->probe('check'));

        $this->probe('load');
        $this->writeSource('Attributes.php', $attributes('Mood'));
        self::assertSame('stale', $this->probe('check'));

        // Whether a block has an editor script is part of the manifest.
        $this->probe('load');
        file_put_contents($this->theme . '/src/Hooks.editor.js', '');
        self::assertSame('stale', $this->probe('check'));

        $this->probe('load');
        unlink($this->theme . '/src/Hooks.editor.js');
        self::assertSame('stale', $this->probe('check'));

        // So is whether it has a view script.
        $this->probe('load');
        file_put_contents($this->theme . '/src/Hooks.view.js', '');
        self::assertSame('stale', $this->probe('check'));

        $this->probe('load');
        unlink($this->theme . '/src/Hooks.view.js');
        self::assertSame('stale', $this->probe('check'));
    }

    public function testBlockSupportChangesRebuild(): void
    {
        $support = static fn(string $label, string $default): string => "<?php\n\nnamespace DevCacheProbe;\n\nuse Saij\\WpAppFramework\\Block\\Support\\BlockSupport;\n\n#[BlockSupport('framed', label: '{$label}')]\nfinal class Framed\n{\n    public function className(bool \$value = {$default}): ?string\n    {\n        return \$value ? 'framed' : null;\n    }\n}\n";

        $this->writeSource('Framed.php', $support('Framed', 'false'));
        $this->probe('load');
        self::assertSame('fresh', $this->probe('check'));

        $this->writeSource('Framed.php', $support('Frame', 'false'));
        self::assertSame('stale', $this->probe('check'));

        // The default of the value is the default of the attribute.
        $this->probe('load');
        $this->writeSource('Framed.php', $support('Frame', 'true'));
        self::assertSame('stale', $this->probe('check'));
    }

    public function testSettingsChangesRebuild(): void
    {
        $group = static fn(string $title, string $default): string => "<?php\n\nnamespace DevCacheProbe;\n\nuse Saij\\WpAppFramework\\Setting\\SettingsGroup;\n\n#[SettingsGroup('general', title: '{$title}')]\nfinal readonly class General\n{\n    public function __construct(public bool \$wide = {$default})\n    {\n    }\n}\n";

        $this->writeSource('General.php', $group('General', 'false'));
        $this->probe('load');
        self::assertSame('fresh', $this->probe('check'));

        $this->writeSource('General.php', $group('Main', 'false'));
        self::assertSame('stale', $this->probe('check'));

        $this->probe('load');
        $this->writeSource('General.php', $group('Main', 'true'));
        self::assertSame('stale', $this->probe('check'));

        // The name of the theme is the title of the settings page.
        $this->probe('load');
        file_put_contents($this->theme . '/style.css', "/*\nTheme Name: Renamed\n*/\n");
        touch($this->theme . '/style.css', time() + 10);
        self::assertSame('stale', $this->probe('check'));
    }

    public function testStylesheetChangesRebuild(): void
    {
        self::assertSame('no styles', $this->probe('load-styles'));
        self::assertSame('fresh', $this->probe('check'));

        file_put_contents($this->theme . '/src/styles.css', '');
        self::assertSame('stale', $this->probe('check'));
        self::assertSame('styles', $this->probe('load-styles'));
        self::assertSame('fresh', $this->probe('check'));

        // Only the presence of the stylesheet matters, not its content.
        file_put_contents($this->theme . '/src/styles.css', '.a {}');
        touch($this->theme . '/src/styles.css', time() + 10);
        self::assertSame('fresh', $this->probe('check'));

        unlink($this->theme . '/src/styles.css');
        self::assertSame('stale', $this->probe('check'));
        self::assertSame('no styles', $this->probe('load-styles'));
    }

    public function testParentClassesOutsideTheSourcesAreNotTracked(): void
    {
        $outside = dirname($this->theme) . '/outside';
        mkdir($outside);
        file_put_contents($outside . '/Base.php', "<?php\n\nnamespace DevCacheOutside;\n\nabstract class Base\n{\n}\n");
        $this->writeSource('Loader.php', "<?php\n\nnamespace DevCacheProbe;\n\nfinal class Loader extends \\DevCacheOutside\\Base\n{\n}\n");

        self::assertSame('probe_filter', $this->probe('load'));
        self::assertStringNotContainsString('Base.php', (string) file_get_contents($this->cache . '/manifest.meta.php'));
        self::assertSame('fresh', $this->probe('check'));
    }

    public function testNewAndDeletedFilesRebuild(): void
    {
        $this->probe('load');
        $this->writeSource('Extra.php', "<?php\n\nnamespace DevCacheProbe;\n\nfinal class Extra\n{\n}\n");
        self::assertSame('stale', $this->probe('check'));

        $this->probe('load');
        unlink($this->theme . '/src/Extra.php');
        self::assertSame('stale', $this->probe('check'));
    }

    public function testComposerJsonChangeRebuilds(): void
    {
        $this->probe('load');
        file_put_contents($this->theme . '/composer.json', json_encode(
            ['type' => 'wordpress-theme', 'autoload' => ['psr-4' => ['DevCacheProbe\\' => 'src']]],
            JSON_THROW_ON_ERROR,
        ));
        touch($this->theme . '/composer.json', time() + 10);

        self::assertSame('stale', $this->probe('check'));
    }

    public function testPathOutsideTheBaseDirectoriesIsAnError(): void
    {
        self::assertStringContainsString(
            $this->theme . '/src is outside the base directories of the manifest cache (framework: ',
            $this->probe('load-without-project-base'),
        );
    }

    private function writeMarkup(string $name, string $content): void
    {
        $file = $this->theme . '/' . $name;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, $content);
        touch($file, time() + 10);
    }

    public function testBlockMarkupChangesRebuild(): void
    {
        $this->writeMarkup('templates/index.html', '<!-- wp:paragraph /-->');
        $this->probe('load');
        self::assertSame('fresh', $this->probe('check'));

        // A changed template.
        $this->writeMarkup('templates/index.html', '<!-- wp:paragraph {"broken":} /-->');
        self::assertSame('stale', $this->probe('check'));
        self::assertStringContainsString(
            'templates/index.html:1: block core/paragraph: invalid JSON in the block comment',
            $this->probe('load'),
        );

        $this->writeMarkup('templates/index.html', '<!-- wp:paragraph /-->');
        $this->probe('load');
        self::assertSame('fresh', $this->probe('check'));

        // A new directory and a new file in it.
        mkdir($this->theme . '/parts');
        self::assertSame('stale', $this->probe('check'));
        $this->probe('load');
        $this->writeMarkup('parts/header.html', '');
        self::assertSame('stale', $this->probe('check'));

        // A new pattern below a subdirectory, then its removal.
        $this->probe('load');
        $this->writeMarkup('patterns/nested/hero.php', '<?php /** Title: Hero */ ?>');
        self::assertSame('stale', $this->probe('check'));
        $this->probe('load');
        unlink($this->theme . '/patterns/nested/hero.php');
        self::assertSame('stale', $this->probe('check'));

        // A deprecated template directory.
        $this->probe('load');
        mkdir($this->theme . '/block-templates');
        self::assertSame('stale', $this->probe('check'));
    }
}
