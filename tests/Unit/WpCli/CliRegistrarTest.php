<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\WpCli;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WebCodingCat\WpAppFramework\DependencyInjection\Container;
use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Manifest\ManifestBuilder;
use WebCodingCat\WpAppFramework\Manifest\ManifestGenerator;
use WebCodingCat\WpAppFramework\Project\ProjectKind;
use WebCodingCat\WpAppFramework\Tests\Fixtures\WpCliValid\Importer;
use WebCodingCat\WpAppFramework\Tests\Fixtures\WpCliValid\ImportMode;
use WebCodingCat\WpAppFramework\Tests\Support\Recorded;
use WebCodingCat\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use WebCodingCat\WpAppFramework\Value\ValueFactory;
use WebCodingCat\WpAppFramework\WpCli\CliRegistrar;
use WP_CLI\ExitException;

/**
 * @phpstan-import-type CliCommandEntry from Manifest
 */
final class CliRegistrarTest extends TestCase
{
    private string $log;
    private string|false $previousLog;

    protected function setUp(): void
    {
        $this->log = (string) tempnam(sys_get_temp_dir(), 'log');
        $this->previousLog = ini_set('error_log', $this->log);
        unset($GLOBALS['wp_app_framework_test_cli_commands'], $GLOBALS['wp_app_framework_test_cli_output'], $GLOBALS['wp_app_framework_test_cli_existing']);
        Importer::reset();
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousLog);
        unlink($this->log);
        unset($GLOBALS['wp_app_framework_test_cli_commands'], $GLOBALS['wp_app_framework_test_cli_output'], $GLOBALS['wp_app_framework_test_cli_existing']);
    }

    /**
     * Registers the commands of the WpCliValid fixture from a generated manifest.
     */
    private static function register(bool $debug = false): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        file_put_contents($file, new ManifestGenerator()->generate(new ManifestBuilder([ManifestBuilderTest::fixture('WpCliValid')])->build()));
        $manifest = Manifest::load($file);
        unlink($file);

        new CliRegistrar(new Container($manifest['services']), new ValueFactory($manifest['values']), $debug, 'fixture', ProjectKind::Theme, 'theme-slug')
            ->register($manifest['cliCommands']);
    }

    /**
     * Runs a registered command like WP-CLI does: with the positional arguments and the options.
     *
     * @param list<string>               $args
     * @param array<string, string|bool> $assocArgs
     */
    private static function runCommand(string $name, array $args = [], array $assocArgs = []): void
    {
        $callable = Recorded::cliCommands()[$name]['callable'];
        self::assertIsCallable($callable);
        $callable($args, $assocArgs);
    }

    /**
     * Runs a command that must fail and returns its exit code and error message.
     *
     * @param list<string>               $args
     * @param array<string, string|bool> $assocArgs
     * @return array{0: int, 1: mixed}
     */
    private static function failCommand(string $name, array $args = [], array $assocArgs = []): array
    {
        try {
            self::runCommand($name, $args, $assocArgs);
        } catch (ExitException $exception) {
            $output = Recorded::cliOutput();
            $last = $output[array_key_last($output) ?? 0] ?? [];

            return [(int) $exception->getCode(), $last === [] || $last[0] !== 'error' ? null : $last[1]];
        }
        self::fail('ExitException expected.');
    }

    public function testRegistersNamespaceAndGroupsBeforeTheirSubcommands(): void
    {
        self::register();
        $commands = Recorded::cliCommands();

        self::assertSame(
            [
                'fixture',
                'fixture events',
                'fixture events import',
                'fixture events delete',
                'fixture events remove',
                'fixture events fail',
                'fixture events crash',
                'fixture events halt',
                'fixture tools',
                'fixture tools ping',
            ],
            array_keys($commands),
        );
        self::assertSame(['shortdesc' => 'Commands of the theme theme-slug.', 'longdesc' => ''], $commands['fixture']['args']);
        self::assertSame(['shortdesc' => 'Manage the events.', 'longdesc' => ''], $commands['fixture events']['args']);
        self::assertIsObject($commands['fixture events']['callable']);
        self::assertSame([], get_class_methods($commands['fixture events']['callable']), 'An empty group has no methods WP-CLI would turn into subcommands.');

        $import = $commands['fixture events import']['args'];
        self::assertSame('Import events from a feed.', $import['shortdesc']);
        self::assertSame("## EXAMPLES\n\n    wp fixture events import https://example.com", $import['longdesc']);
        self::assertIsArray($import['synopsis']);
        self::assertCount(6, $import['synopsis']);
        self::assertSame(0, Importer::getInstances(), 'Services are resolved only when a command runs.');
    }

    public function testNamespaceThatIsAlreadyACommandIsLeftOutWithAWarning(): void
    {
        $GLOBALS['wp_app_framework_test_cli_existing'] = ['fixture'];

        self::register();

        self::assertSame([], Recorded::cliCommands());
        self::assertSame(
            [['warning', 'The WP-CLI commands of the theme theme-slug are not registered: "wp fixture" is already a command. Change the namespace (extra.wp-app-framework.namespace) of the theme.']],
            Recorded::cliOutput(),
        );
    }

    public function testConvertsArgumentsOptionsAndFlags(): void
    {
        self::register();

        self::runCommand('fixture events import', ['https://example.com/feed', '5'], ['limit' => '7', 'mode' => 'replace', 'dry-run' => true]);

        self::assertSame(1, Importer::getInstances());
        self::assertSame(
            [['url' => 'https://example.com/feed', 'count' => 5, 'limit' => 7, 'mode' => ImportMode::Replace, 'ratio' => null, 'dryRun' => true]],
            Importer::getCalls(),
        );
        self::assertSame([['success', 'Imported from https://example.com/feed.']], Recorded::cliOutput());
    }

    public function testMissingOptionalValuesUseTheirDefaults(): void
    {
        self::register();

        self::runCommand('fixture events import', ['https://example.com/feed']);
        self::runCommand('fixture events import', ['https://example.com/feed'], ['dry-run' => false]);

        $defaults = ['url' => 'https://example.com/feed', 'count' => null, 'limit' => 50, 'mode' => ImportMode::Merge, 'ratio' => null, 'dryRun' => false];
        self::assertSame([$defaults, $defaults], Importer::getCalls());
    }

    public function testVariadicArgumentTakesTheRemainingValues(): void
    {
        self::register();

        self::runCommand('fixture events delete', ['1', '2', '3'], ['mode' => 'merge']);
        self::runCommand('fixture events remove', ['4'], ['mode' => 'replace']);

        self::assertSame(
            [['mode' => ImportMode::Merge, 'ids' => [1, 2, 3]], ['mode' => ImportMode::Replace, 'ids' => [4]]],
            Importer::getCalls(),
        );
    }

    public function testInvalidValuesEndTheCommandWithAnError(): void
    {
        self::register();

        self::assertSame([1, 'Invalid value for --limit: limit is not of type integer.'], self::failCommand('fixture events import', ['https://example.com'], ['limit' => 'many']));
        self::assertSame([1, 'Invalid value for --mode: mode is not one of merge, replace.'], self::failCommand('fixture events import', ['https://example.com'], ['mode' => 'merge,replace']));
        self::assertSame([1, 'Invalid value for <count>: count is not of type integer.'], self::failCommand('fixture events import', ['https://example.com', 'two']));
        self::assertSame([1, 'Invalid value for <ids>: ids is not of type integer.'], self::failCommand('fixture events delete', ['1', 'x'], ['mode' => 'merge']));
        self::assertSame([], Importer::getCalls());
    }

    public function testCliExceptionEndsTheCommandWithItsExitCode(): void
    {
        self::register();

        self::assertSame([3, 'No events.'], self::failCommand('fixture events fail'));
        self::assertSame('', (string) file_get_contents($this->log));
    }

    public function testExitExceptionOfWpCliPassesThrough(): void
    {
        self::register();

        self::assertSame([4, null], self::failCommand('fixture events halt'));
        self::assertSame('', (string) file_get_contents($this->log));
    }

    public function testUnexpectedExceptionIsLoggedAndReportedWithoutDebugging(): void
    {
        self::register();

        self::assertSame([1, 'Command failed: RuntimeException: Broken feed.'], self::failCommand('fixture events crash'));
        self::assertStringContainsString(
            'wp-app-framework: WP-CLI command "wp fixture events crash" (WebCodingCat\WpAppFramework\Tests\Fixtures\WpCliValid\EventCommands::crash()) failed: RuntimeException: Broken feed. in ',
            (string) file_get_contents($this->log),
        );
    }

    public function testUnexpectedExceptionIsLoggedAndRethrownWhenDebugging(): void
    {
        self::register(debug: true);

        try {
            self::runCommand('fixture events crash');
            self::fail('RuntimeException expected.');
        } catch (RuntimeException $exception) {
            self::assertSame('Broken feed.', $exception->getMessage());
        }
        self::assertStringContainsString('WP-CLI command "wp fixture events crash"', (string) file_get_contents($this->log));
        self::assertSame([], Recorded::cliOutput());
    }

    public function testOutputWritesMessagesAndTables(): void
    {
        self::register();

        self::runCommand('fixture tools ping');

        self::assertSame(
            [['log', 'pong'], ['format', 'table', [['name' => 'a', 'count' => 1]], ['name', 'count']]],
            Recorded::cliOutput(),
        );
    }
}
