<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\WpCliBroken;

use WebCodingCat\WpAppFramework\Rest\Param;
use WebCodingCat\WpAppFramework\Value\ListOf;
use WebCodingCat\WpAppFramework\Value\PostReference;
use WebCodingCat\WpAppFramework\WpCli\Argument;
use WebCodingCat\WpAppFramework\WpCli\CliCommand;
use WebCodingCat\WpAppFramework\WpCli\CliOutput;
use WebCodingCat\WpAppFramework\WpCli\CliSubcommand;
use WebCodingCat\WpAppFramework\WpCli\Option;

#[CliCommand('broken', 'Broken commands.')]
final class BrokenCommands
{
    // @phpstan-ignore method.unused (private on purpose: a build error)
    #[CliSubcommand('hidden', 'Hidden.')]
    private function hidden(): void {}

    #[CliSubcommand('static', 'Static.')]
    public static function staticMethod(): void {}

    #[CliSubcommand('value', 'Value.')]
    public function value(): int
    {
        return 1;
    }

    #[CliSubcommand('Bad', 'Bad name.')]
    public function badName(): void {}

    #[CliSubcommand('twice', 'First.')]
    public function first(): void {}

    #[CliSubcommand('twice', 'Second.')]
    public function second(): void {}

    #[CliSubcommand('empty', ' ')]
    public function emptyDescription(): void {}

    #[CliSubcommand('long', 'A description that is much too long for the list of commands that WP-CLI shows: 81.')]
    public function longDescription(): void {}

    #[CliSubcommand('lines', "Two\nlines.")]
    public function lineBreak(): void {}

    #[CliSubcommand('unmarked', 'Unmarked.')]
    public function unmarked(string $text): void {}

    #[CliSubcommand('both', 'Both markers.')]
    public function bothMarkers(#[Argument] #[Option] string $text): void {}

    #[CliSubcommand('marked-service', 'Marked service.')]
    public function markedService(#[Argument] Service $service): void {}

    #[CliSubcommand('marked-output', 'Marked output.')]
    public function markedOutput(#[Option] CliOutput $output): void {}

    #[CliSubcommand('value-class', 'Value class.')]
    public function valueClass(#[Argument] Item $item): void {}

    /**
     * @param list<string> $list
     */
    #[CliSubcommand('array', 'Array.')]
    public function arrayParameter(#[Argument] array $list): void {}

    /**
     * @param list<Item> $items
     */
    #[CliSubcommand('list-of', 'List of.')]
    public function listOf(#[Option] #[ListOf(Item::class)] array $items = []): void {}

    #[CliSubcommand('union', 'Union.')]
    public function union(#[Argument] int|string $value): void {}

    #[CliSubcommand('mixed', 'Mixed.')]
    public function mixed(#[Argument] mixed $value): void {}

    /** @phpstan-ignore missingType.parameter (untyped on purpose) */
    #[CliSubcommand('untyped', 'Untyped.')]
    public function untyped(#[Argument] $value): void {}

    #[CliSubcommand('reference', 'Reference.')]
    public function reference(#[Option] #[PostReference] ?int $post = null): void {}

    #[CliSubcommand('param', 'Param.')]
    public function param(#[Option] #[Param(minimum: 1)] int $limit = 1): void {}

    #[CliSubcommand('reference-parameter', 'By reference.')]
    public function byReference(#[Argument] string &$text): void {}

    #[CliSubcommand('bool-argument', 'Bool argument.')]
    public function boolArgument(#[Argument] bool $flag): void {}

    #[CliSubcommand('variadic-after-optional', 'Variadic after optional.')]
    public function variadicAfterOptional(#[Argument] string $first = '', #[Argument] string ...$rest): void {}

    #[CliSubcommand('nullable-argument', 'Nullable argument.')]
    public function nullableArgument(#[Argument] ?int $count): void {}

    #[CliSubcommand('variadic-bool', 'Variadic bool.')]
    public function variadicBool(#[Argument] bool ...$flags): void {}

    #[CliSubcommand('true-flag', 'True flag.')]
    public function trueFlag(#[Option] bool $force = true): void {}

    #[CliSubcommand('nullable-flag', 'Nullable flag.')]
    public function nullableFlag(#[Option] ?bool $force = null): void {}

    #[CliSubcommand('required-flag', 'Required flag.')]
    public function requiredFlag(#[Option] bool $force): void {}

    #[CliSubcommand('nullable-option', 'Nullable option.')]
    public function nullableOption(#[Option] ?int $limit): void {}

    #[CliSubcommand('variadic-option', 'Variadic option.')]
    public function variadicOption(#[Option] int ...$limits): void {}

    #[CliSubcommand('same-name', 'Same name.')]
    public function sameName(#[Option] bool $dryRun = false, #[Option] bool $dry_run = false): void {}

    #[CliSubcommand('reserved', 'Reserved.')]
    public function reserved(#[Option] string $url = ''): void {}

    #[CliSubcommand('negated', 'Negated.')]
    public function negated(#[Option] bool $noCache = false): void {}

    #[CliSubcommand('invalid-name', 'Invalid name.')]
    public function invalidName(#[Argument] string $_text): void {}
}
