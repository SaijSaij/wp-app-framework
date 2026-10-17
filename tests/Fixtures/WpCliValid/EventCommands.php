<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\WpCliValid;

use RuntimeException;
use WebCodingCat\WpAppFramework\WpCli\Argument;
use WebCodingCat\WpAppFramework\WpCli\CliCommand;
use WebCodingCat\WpAppFramework\WpCli\CliException;
use WebCodingCat\WpAppFramework\WpCli\CliOutput;
use WebCodingCat\WpAppFramework\WpCli\CliSubcommand;
use WebCodingCat\WpAppFramework\WpCli\Option;
use WP_CLI;

#[CliCommand('events', 'Manage the events.')]
final readonly class EventCommands
{
    public function __construct(private Importer $importer) {}

    #[CliSubcommand('import', 'Import events from a feed.', longDescription: "## EXAMPLES\n\n    wp fixture events import https://example.com")]
    public function import(
        CliOutput $output,
        #[Argument('URL of the feed.')]
        string $url,
        Importer $importer,
        #[Argument]
        ?int $count = null,
        #[Option('Maximum number of events.')]
        int $limit = 50,
        #[Option('How existing events are treated.')]
        ImportMode $mode = ImportMode::Merge,
        #[Option]
        ?float $ratio = null,
        #[Option('Only show what would be imported.')]
        bool $dryRun = false,
    ): void {
        $importer->record(['url' => $url, 'count' => $count, 'limit' => $limit, 'mode' => $mode, 'ratio' => $ratio, 'dryRun' => $dryRun]);
        $output->success(sprintf('Imported from %s.', $url));
    }

    #[CliSubcommand('delete', 'Delete events.')]
    #[CliSubcommand('remove', 'Remove events.')]
    public function delete(#[Option] ImportMode $mode, #[Argument('IDs of the events.')] int ...$ids): void
    {
        $this->importer->record(['mode' => $mode, 'ids' => $ids]);
    }

    #[CliSubcommand('fail', 'Fail with exit code 3.')]
    public function fail(): void
    {
        throw new CliException('No events.', 3);
    }

    #[CliSubcommand('crash', 'Fail unexpectedly.')]
    public function crash(): void
    {
        throw new RuntimeException('Broken feed.');
    }

    #[CliSubcommand('halt', 'Halt WP-CLI with exit code 4.')]
    public function halt(): void
    {
        WP_CLI::halt(4);
    }
}
