<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\WpCli;

use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Manifest\BuildException;
use WebCodingCat\WpAppFramework\Manifest\ManifestBuilder;
use WebCodingCat\WpAppFramework\Tests\Fixtures\WpCliValid;
use WebCodingCat\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use WebCodingCat\WpAppFramework\WpCli\CliCommandCollector;
use WebCodingCat\WpAppFramework\WpCli\CliCommandDefinition;

/**
 * @phpstan-import-type CliSubcommandEntry from CliCommandDefinition
 */
final class CliCommandCollectorTest extends TestCase
{
    /**
     * @return array<string, CliCommandDefinition> By command name.
     */
    private static function commands(): array
    {
        $commands = [];
        foreach (new ManifestBuilder([ManifestBuilderTest::fixture('WpCliValid')])->build()->getCliCommands() as $command) {
            $commands[$command->getName()] = $command;
        }

        return $commands;
    }

    /**
     * @return array<string, CliSubcommandEntry> By subcommand name.
     */
    private static function subcommands(string $command): array
    {
        return array_column(self::commands()[$command]->getSubcommands(), null, 'name');
    }

    public function testCommandsWithTheirSubcommands(): void
    {
        $commands = self::commands();

        self::assertSame(['events', 'tools'], array_keys($commands));
        self::assertSame('Manage the events.', $commands['events']->getDescription());
        self::assertSame(WpCliValid\EventCommands::class, $commands['events']->getClass());
        self::assertSame(['import', 'delete', 'remove', 'fail', 'crash', 'halt'], array_column($commands['events']->getSubcommands(), 'name'));

        $subcommands = self::subcommands('events');
        self::assertSame('delete', $subcommands['remove']['method'], 'A repeated #[CliSubcommand] registers the method under each name.');
        self::assertSame('Remove events.', $subcommands['remove']['description']);
        self::assertSame("## EXAMPLES\n\n    wp fixture events import https://example.com", $subcommands['import']['longDescription']);
    }

    public function testSynopsisListsPositionalArgumentsOptionsAndFlags(): void
    {
        $subcommands = self::subcommands('events');

        self::assertSame(
            [
                ['type' => 'positional', 'name' => 'url', 'optional' => false, 'repeating' => false, 'description' => 'URL of the feed.'],
                ['type' => 'positional', 'name' => 'count', 'optional' => true, 'repeating' => false],
                ['type' => 'assoc', 'name' => 'limit', 'optional' => true, 'description' => 'Maximum number of events.', 'default' => 50],
                ['type' => 'assoc', 'name' => 'mode', 'optional' => true, 'description' => 'How existing events are treated.', 'default' => 'merge', 'options' => ['merge', 'replace']],
                ['type' => 'assoc', 'name' => 'ratio', 'optional' => true],
                ['type' => 'flag', 'name' => 'dry-run', 'optional' => true, 'description' => 'Only show what would be imported.'],
            ],
            $subcommands['import']['synopsis'],
        );
        self::assertSame(
            [
                ['type' => 'positional', 'name' => 'ids', 'optional' => false, 'repeating' => true, 'description' => 'IDs of the events.'],
                ['type' => 'assoc', 'name' => 'mode', 'optional' => false, 'options' => ['merge', 'replace']],
            ],
            $subcommands['delete']['synopsis'],
        );
        self::assertSame([], $subcommands['fail']['synopsis']);
    }

    public function testArgumentsKeepTheDeclarationOrder(): void
    {
        $subcommands = self::subcommands('events');
        $mode = ['type' => 'enum', 'class' => WpCliValid\ImportMode::class, 'nullable' => false];

        self::assertSame(
            [
                ['param' => 'output', 'kind' => 'output'],
                ['param' => 'url', 'kind' => 'positional', 'name' => 'url', 'index' => 0, 'variadic' => false, 'value' => ['type' => 'string', 'nullable' => false], 'schema' => ['type' => 'string']],
                ['param' => 'importer', 'kind' => 'service', 'class' => WpCliValid\Importer::class],
                ['param' => 'count', 'kind' => 'positional', 'name' => 'count', 'index' => 1, 'variadic' => false, 'value' => ['type' => 'int', 'nullable' => true], 'schema' => ['type' => ['integer', 'null']], 'default' => null],
                ['param' => 'limit', 'kind' => 'option', 'name' => 'limit', 'value' => ['type' => 'int', 'nullable' => false], 'schema' => ['type' => 'integer'], 'default' => 50],
                ['param' => 'mode', 'kind' => 'option', 'name' => 'mode', 'value' => $mode, 'schema' => ['type' => 'string', 'enum' => ['merge', 'replace']], 'default' => 'merge'],
                ['param' => 'ratio', 'kind' => 'option', 'name' => 'ratio', 'value' => ['type' => 'float', 'nullable' => true], 'schema' => ['type' => ['number', 'null']], 'default' => null],
                ['param' => 'dryRun', 'kind' => 'flag', 'name' => 'dry-run'],
            ],
            $subcommands['import']['arguments'],
        );
        self::assertSame(
            [
                ['param' => 'mode', 'kind' => 'option', 'name' => 'mode', 'value' => $mode, 'schema' => ['type' => 'string', 'enum' => ['merge', 'replace']]],
                ['param' => 'ids', 'kind' => 'positional', 'name' => 'ids', 'index' => 0, 'variadic' => true, 'value' => ['type' => 'int', 'nullable' => false], 'schema' => ['type' => 'integer']],
            ],
            $subcommands['delete']['arguments'],
        );
    }

    public function testCommandsAndTheirServicesAreReachable(): void
    {
        self::assertSame(
            [
                WpCliValid\EventCommands::class => ['importer' => WpCliValid\Importer::class],
                WpCliValid\Importer::class => [],
                WpCliValid\ToolCommands::class => [],
            ],
            new ManifestBuilder([ManifestBuilderTest::fixture('WpCliValid')])->build()->getServices(),
        );
    }

    public function testParameterNamesBecomeKebabCase(): void
    {
        self::assertSame('dry-run', CliCommandCollector::cliName('dryRun'));
        self::assertSame('dry-run', CliCommandCollector::cliName('dry_run'));
        self::assertSame('limit2', CliCommandCollector::cliName('limit2'));
        self::assertSame('url', CliCommandCollector::cliName('URL'));
        self::assertSame('post-id', CliCommandCollector::cliName('postID'));
    }

    public function testNamespaceOfAWpCliCommandIsRejected(): void
    {
        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('WpCliNamespace')], namespace: 'post')->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            self::assertSame(
                ['The namespace "post" (extra.wp-app-framework.namespace) is a command of WP-CLI, "wp post"; WP-CLI commands of the project (WebCodingCat\\WpAppFramework\\Tests\\Fixtures\\WpCliNamespace\\PostCommands) cannot be registered under it. Choose another namespace.'],
                $exception->getErrors(),
            );
        }

        self::assertCount(1, new ManifestBuilder([ManifestBuilderTest::fixture('WpCliNamespace')], namespace: 'events')->build()->getCliCommands());
    }

    public function testReportsAllErrorsWithClassMethodAndParameterNames(): void
    {
        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('WpCliBroken')])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            $ns = 'WebCodingCat\\WpAppFramework\\Tests\\Fixtures\\WpCliBroken\\';
            $c = $ns . 'BrokenCommands';
            $supported = 'use string, int, float, a backed enum or, for an option, bool';
            $marker = 'and must not have #[Argument] or #[Option].';
            $flag = 'is a flag and must be declared as bool $force = false.';
            $expected = [
                "{$ns}AbstractCommands has #[CliCommand], but {$ns}AbstractCommands is an abstract class and therefore not a service.",
                "#[CliCommand] on {$ns}BadName has the invalid name \"Bad_Name\": start with a lowercase letter, then use lowercase letters, digits and \"-\".",
                "{$ns}NoCommand has #[CliSubcommand] methods, but no #[CliCommand] attribute naming their command group.",
                "{$ns}NoSubcommands has #[CliCommand], but no #[CliSubcommand] methods.",
                "Duplicate WP-CLI command \"wp fixture dup\" in {$ns}FirstDuplicate and {$ns}SecondDuplicate.",
                "{$c}::hidden() has #[CliSubcommand] and must be public and non-static.",
                "{$c}::staticMethod() has #[CliSubcommand] and must be public and non-static.",
                "{$c}::value() has #[CliSubcommand] and must return void; write output with a CliOutput parameter.",
                "#[CliSubcommand] on {$c}::badName() has the invalid name \"Bad\": start with a lowercase letter, then use lowercase letters, digits and \"-\".",
                "Duplicate WP-CLI command \"wp fixture broken twice\" in {$c}::first() and {$c}::second().",
                "#[CliSubcommand] on {$c}::emptyDescription() has an empty description; WP-CLI shows it in the list of commands.",
                "#[CliSubcommand] on {$c}::longDescription() has a description of 83 characters; WP-CLI allows at most 80.",
                "#[CliSubcommand] on {$c}::lineBreak() has a description with a line break; put further text into longDescription of #[CliSubcommand].",
                "parameter \$text of {$c}::unmarked() is neither a CliOutput nor a service; mark it #[Argument] for a positional argument or #[Option] for an option or flag.",
                "parameter \$text of {$c}::bothMarkers() has #[Argument] and #[Option]; use one of them.",
                "parameter \$service of {$c}::markedService() is a service ({$ns}Service) {$marker}",
                "parameter \$output of {$c}::markedOutput() is the CliOutput {$marker}",
                "parameter \$item of {$c}::valueClass() is a service ({$ns}Item) {$marker}",
                "parameter \$list of {$c}::arrayParameter(): type array is not supported in WP-CLI commands; {$supported}, optionally nullable. For several values, declare a variadic #[Argument], e.g. int ...\$ids.",
                "parameter \$items of {$c}::listOf(): type array is not supported in WP-CLI commands; {$supported}, optionally nullable. For several values, declare a variadic #[Argument], e.g. int ...\$ids.",
                "parameter \$value of {$c}::union(): type string|int is not supported in WP-CLI commands; {$supported}, optionally nullable.",
                "parameter \$value of {$c}::mixed(): type mixed is not supported in WP-CLI commands; {$supported}, optionally nullable.",
                "parameter \$value of {$c}::untyped() has no type; {$supported}.",
                "parameter \$post of {$c}::reference() has #[PostReference], which marks fields of blocks and settings groups, not WP-CLI arguments; declare the ID as int.",
                "parameter \$limit of {$c}::param() has #[Param], which adds schema keywords to REST arguments, not to WP-CLI arguments.",
                "parameter \$text of {$c}::byReference(): by-reference parameters are not supported in WP-CLI commands.",
                "parameter \$flag of {$c}::boolArgument() is bool; a positional argument cannot be a flag, mark it #[Option] instead.",
                "parameter \$rest of {$c}::variadicAfterOptional() is variadic and needs at least one value, but follows the optional argument <first>; WP-CLI could not tell which value belongs to which argument.",
                "parameter \$count of {$c}::nullableArgument() is nullable and needs a default value.",
                "parameter \$flags of {$c}::variadicBool() is bool; a positional argument cannot be a flag, mark it #[Option] instead.",
                "parameter \$force of {$c}::trueFlag() {$flag}",
                "parameter \$force of {$c}::nullableFlag() {$flag}",
                "parameter \$force of {$c}::requiredFlag() {$flag}",
                "parameter \$limit of {$c}::nullableOption() is nullable and needs a default value.",
                "parameter \$limits of {$c}::variadicOption() is variadic; only an #[Argument] parameter can take several values.",
                "parameter \$dry_run of {$c}::sameName() and parameter \$dryRun both become \"dry-run\" in WP-CLI; rename one of them.",
                "parameter \$url of {$c}::reserved() becomes the option --url, which WP-CLI reserves for itself (a global parameter or --help); rename the parameter.",
                "parameter \$noCache of {$c}::negated() becomes the option --no-cache; WP-CLI reads --no-{name} as {name} set to false, so an option cannot start with \"no-\".",
                "parameter \$_text of {$c}::invalidName() becomes \"-text\" in WP-CLI, which is not a valid name: in kebab-case it must start with a letter and contain only letters, digits and \"-\".",
            ];

            $errors = $exception->getErrors();
            foreach ($expected as $message) {
                self::assertContains($message, $errors);
            }
            self::assertCount(count($expected), $errors);
        }
    }
}
