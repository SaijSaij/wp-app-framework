<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Release;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Release\ReleaseCommand;

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

    public function testVersionMismatchFailsBeforeCopying(): void
    {
        $project = sys_get_temp_dir() . '/wpaf-release-command-' . bin2hex(random_bytes(4));
        mkdir($project);
        file_put_contents($project . '/style.css', "/*\nTheme Name: Test\nVersion: 1.0.0\n*/");

        try {
            [$code, , $stderr] = self::release(['--expect-version=1.0.1'], $project);
        } finally {
            unlink($project . '/style.css');
            rmdir($project);
        }

        self::assertSame(1, $code);
        self::assertStringContainsString('declares version 1.0.0, but version 1.0.1 is being released', $stderr);
        self::assertStringNotContainsString('build directory', $stderr);
    }
}
