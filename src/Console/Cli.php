<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Console;

use RuntimeException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Manifest\ManifestGenerator;

/**
 * Command line entry point: `wp-app-framework build [--output=<file>]`.
 *
 * Without --output the manifest is only built and checked. A theme source
 * checkout must not contain generated/manifest.php: the framework treats its
 * presence as a release build.
 */
#[NoService]
final class Cli
{
    private const string USAGE = 'Usage: wp-app-framework build [--output=<file>]';

    /**
     * @param list<string> $arguments Command line arguments without the script name.
     * @param resource     $stdout
     * @param resource     $stderr
     */
    public static function run(array $arguments, string $projectDirectory, $stdout, $stderr): int
    {
        $command = array_shift($arguments);
        $output = null;

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--output=') && strlen($argument) > strlen('--output=')) {
                $output = substr($argument, strlen('--output='));
                continue;
            }

            fwrite($stderr, sprintf("Unknown argument \"%s\".\n%s\n", $argument, self::USAGE));

            return 2;
        }

        if ($command !== 'build') {
            fwrite($stderr, self::USAGE . "\n");

            return 2;
        }

        try {
            $result = ManifestBuilder::forTheme($projectDirectory)->build();
            $code = new ManifestGenerator()->generate($result);

            if ($output !== null) {
                self::write($output, $code);
            }
        } catch (BuildException $exception) {
            fwrite($stderr, $exception->getMessage() . "\n");

            return 1;
        }

        fwrite($stdout, sprintf(
            "Manifest OK: %d service candidates, %d services, %d hooks.%s\n",
            count($result->candidates),
            count($result->services),
            count($result->hooks),
            $output !== null ? ' Written to ' . $output . '.' : ' Nothing written (no --output).',
        ));

        return 0;
    }

    private static function write(string $file, string $code): void
    {
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create directory %s.', $directory));
        }

        $temporary = tempnam($directory, basename($file));
        if ($temporary === false || file_put_contents($temporary, $code) === false) {
            throw new RuntimeException(sprintf('Cannot write a temporary file for %s.', $file));
        }

        chmod($temporary, 0644);

        if (!rename($temporary, $file)) {
            unlink($temporary);
            throw new RuntimeException(sprintf('Cannot move the temporary file to %s.', $file));
        }
    }
}
