<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Release;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Release\ReleaseCommand;
use Saij\WpAppFramework\Tests\Support\Files;

/**
 * Argument handling and the checks before anything is copied. The pipeline
 * itself needs Composer, Strauss and npm; the test theme runs it
 * (`composer release`, see theme-dev-test).
 */
final class ReleaseCommandTest extends TestCase
{
    /**
     * @param list<string> $arguments
     * @return array{int, string, string}
     */
    private static function release(array $arguments, string $project): array
    {
        $stdout = fopen('php://temp', 'w+');
        $stderr = fopen('php://temp', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        $code = ReleaseCommand::run($arguments, $project, $stdout, $stderr);
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }

    public function testUnknownArgumentsPrintTheUsage(): void
    {
        [$code, , $stderr] = self::release(['--keep'], sys_get_temp_dir());

        self::assertSame(2, $code);
        self::assertStringContainsString('Unknown argument "--keep".', $stderr);
        self::assertStringContainsString(ReleaseCommand::USAGE, $stderr);
    }

    public function testMalformedPackageSourceIsAnUnknownArgument(): void
    {
        [$code] = self::release(['--package-source=../framework'], sys_get_temp_dir());

        self::assertSame(2, $code);
    }

    /**
     * A project directory with composer.json and src/; the directory name is the slug.
     *
     * @param array<string, string> $files
     */
    private static function project(string $slug, ?string $type, array $files): string
    {
        $project = sys_get_temp_dir() . '/wpaf-release-command-' . bin2hex(random_bytes(4)) . '/' . $slug;
        mkdir($project . '/src', 0777, true);
        file_put_contents($project . '/composer.json', (string) json_encode(array_filter([
            'type' => $type,
            'autoload' => ['psr-4' => ['Test\\' => 'src/']],
        ])));
        foreach ($files as $name => $content) {
            file_put_contents($project . '/' . $name, $content);
        }

        return $project;
    }

    public function testThemeVersionMismatchFailsBeforeCopying(): void
    {
        $project = self::project('my-theme', 'wordpress-theme', ['style.css' => "/*\nTheme Name: Test\nVersion: 1.0.0\n*/"]);

        try {
            [$code, , $stderr] = self::release(['--expect-version=1.0.1'], $project);
        } finally {
            Files::remove(dirname($project));
        }

        self::assertSame(1, $code);
        self::assertStringContainsString('style.css declares version 1.0.0, but version 1.0.1 is being released', $stderr);
        self::assertStringNotContainsString('build directory', $stderr);
    }

    public function testPluginVersionComesFromTheMainFile(): void
    {
        $project = self::project('my-plugin', 'wordpress-plugin', [
            'style.css' => "/*\nVersion: 9.9.9\n*/",
            'my-plugin.php' => "<?php\n/**\n * Plugin Name: Test\n * Version: 2.0.0\n */\n",
        ]);

        try {
            [$code, , $stderr] = self::release(['--expect-version=2.0.1'], $project);
        } finally {
            Files::remove(dirname($project));
        }

        self::assertSame(1, $code);
        self::assertStringContainsString('my-plugin.php declares version 2.0.0, but version 2.0.1 is being released', $stderr);
    }

    public function testProjectWithoutTypeIsRejected(): void
    {
        $project = self::project('my-theme', null, ['style.css' => "/*\nVersion: 1.0.0\n*/"]);

        try {
            [$code, , $stderr] = self::release([], $project);
        } finally {
            Files::remove(dirname($project));
        }

        self::assertSame(1, $code);
        self::assertStringContainsString('composer.json must declare "type": "wordpress-theme" or "wordpress-plugin", found null.', $stderr);
    }
}
