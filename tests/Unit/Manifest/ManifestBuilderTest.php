<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Manifest;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Manifest\Discovery\Source;
use Saij\WpAppFramework\Tests\Fixtures\Valid;

final class ManifestBuilderTest extends TestCase
{
    public static function fixture(string $name): Source
    {
        return new Source(
            'Saij\\WpAppFramework\\Tests\\Fixtures\\' . $name . '\\',
            (string) realpath(__DIR__ . '/../../Fixtures/' . $name),
        );
    }

    public static function buildValid(): BuildResult
    {
        return new ManifestBuilder([self::fixture('Valid')])->build();
    }

    public function testConcreteClassesAreCandidatesUnlessExcluded(): void
    {
        self::assertSame(
            [Valid\Clock::class, Valid\Dto::class, Valid\Greeter::class, Valid\Hooks::class, Valid\UnusedService::class],
            self::buildValid()->candidates,
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
            self::buildValid()->services,
        );
    }

    public function testReportsAllErrorsWithClassAndParameterNames(): void
    {
        try {
            new ManifestBuilder([self::fixture('Broken')])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            $ns = 'Saij\\WpAppFramework\\Tests\\Fixtures\\Broken\\';
            $expected = [
                "Cannot autowire parameter \$logger of {$ns}InterfaceDependent::__construct(): {$ns}LoggerLike is an interface and cannot be injected (aliases are not supported).",
                "Cannot autowire parameter \$excluded of {$ns}ExcludedDependent::__construct(): {$ns}Excluded is marked #[NoService] and cannot be injected.",
                "Cannot autowire parameter \$name of {$ns}ScalarDependent::__construct(): type string is not a service and the parameter has no default value.",
                "Cannot autowire parameter \$storage of {$ns}OutsideDependent::__construct(): ArrayObject is outside the scanned PSR-4 directories of the theme and the framework and cannot be injected.",
                "Circular dependency: {$ns}CycleA -> {$ns}CycleB -> {$ns}CycleA",
                "{$ns}ExcludedWithHook::run() has #[Hook], but {$ns}ExcludedWithHook is marked #[NoService] and therefore not a service.",
                "{$ns}BrokenHooks::missingReturnType() has #[Hook] and needs a declared return type: void for an action, the filtered type for a filter.",
                "{$ns}BrokenHooks::staticHook() has #[Hook] and must be public and non-static.",
            ];

            foreach ($expected as $message) {
                self::assertContains($message, $exception->errors);
            }
            self::assertCount(count($expected), $exception->errors);
            self::assertStringStartsWith("Manifest build failed:\n - ", $exception->getMessage());
        }
    }

    public function testFileNotDeclaringItsPsr4ClassIsAnError(): void
    {
        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('Declared.php does not declare Saij\\WpAppFramework\\Tests\\Fixtures\\Mismatch\\Declared as expected by PSR-4.');

        new ManifestBuilder([self::fixture('Mismatch')])->build();
    }

    public function testFrameworkSourceIsTheFrameworkSrcDirectory(): void
    {
        $source = ManifestBuilder::frameworkSource();

        self::assertSame('Saij\\WpAppFramework\\', $source->prefix);
        self::assertSame(realpath(__DIR__ . '/../../../src'), $source->directory);
    }

    public function testFrameworkClassesAreNotServicesExceptTheTheme(): void
    {
        $result = new ManifestBuilder([ManifestBuilder::frameworkSource()], [], [\Saij\WpAppFramework\Theme::class])->build();

        self::assertSame([\Saij\WpAppFramework\Theme::class], $result->candidates);
        self::assertSame([], $result->services);
        self::assertSame([], $result->hooks);
    }
}
