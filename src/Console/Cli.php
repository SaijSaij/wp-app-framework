<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Console;

use RuntimeException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Release\ReleaseCommand;
use Saij\WpAppFramework\Release\ReleaseException;
use Saij\WpAppFramework\Release\TemplateCompilation;
use Saij\WpAppFramework\Twig\TemplateCallableKind;
use Saij\WpAppFramework\Twig\TemplateOverrides;
use Saij\WpAppFramework\Twig\TemplateRenderer;
use Saij\WpAppFramework\Twig\TemplateVersion;

/**
 * Command line entry point.
 *
 * - `build [--output=<file>]` builds and checks the manifest. Without --output
 *   nothing is written. A theme source checkout must not contain
 *   generated/manifest.php: the framework treats its presence as a release build.
 * - `release [...]` builds the release ZIP (see ReleaseCommand).
 * - `compile-templates --manifest=<file> --output=<directory>` compiles all
 *   templates as release mode would; internal step of `release`, run with the
 *   prefixed classes of the build copy.
 */
#[NoService]
final class Cli
{
    private const string USAGE = "Usage:\n  wp-app-framework build [--output=<file>]\n  wp-app-framework release [--output=<zip>] [--expect-version=<version>] [--package-source=<package>=<directory>]...";

    /**
     * @param list<string> $arguments Command line arguments without the script name.
     * @param resource     $stdout
     * @param resource     $stderr
     */
    public static function run(array $arguments, string $projectDirectory, $stdout, $stderr): int
    {
        $command = array_shift($arguments);

        return match ($command) {
            'build' => self::build($arguments, $projectDirectory, $stdout, $stderr),
            'release' => ReleaseCommand::run($arguments, $projectDirectory, $stdout, $stderr),
            'compile-templates' => self::compileTemplates($arguments, $projectDirectory, $stdout, $stderr),
            default => self::usage($stderr),
        };
    }

    /**
     * @param resource $stderr
     */
    private static function usage($stderr, ?string $argument = null): int
    {
        if ($argument !== null) {
            fwrite($stderr, sprintf("Unknown argument \"%s\".\n", $argument));
        }
        fwrite($stderr, self::USAGE . "\n");

        return 2;
    }

    /**
     * @param list<string> $arguments
     * @param resource     $stdout
     * @param resource     $stderr
     */
    private static function compileTemplates(array $arguments, string $projectDirectory, $stdout, $stderr): int
    {
        $options = [];
        foreach ($arguments as $argument) {
            if (preg_match('/^--(manifest|output)=(.+)$/', $argument, $match) !== 1) {
                return self::usage($stderr, $argument);
            }
            $options[$match[1]] = $match[2];
        }
        if (!isset($options['manifest'], $options['output'])) {
            fwrite($stderr, "Usage: wp-app-framework compile-templates --manifest=<file> --output=<directory>\n");

            return 2;
        }

        try {
            $count = TemplateCompilation::compile($projectDirectory, $options['manifest'], $options['output']);
        } catch (ReleaseException $exception) {
            fwrite($stderr, $exception->getMessage() . "\n");

            return 1;
        }
        fwrite($stdout, sprintf("%d templates compiled to %s.\n", $count, $options['output']));

        return 0;
    }

    /**
     * @param list<string> $arguments
     * @param resource     $stdout
     * @param resource     $stderr
     */
    private static function build(array $arguments, string $projectDirectory, $stdout, $stderr): int
    {
        $output = null;
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--output=') && strlen($argument) > strlen('--output=')) {
                $output = substr($argument, strlen('--output='));
                continue;
            }

            return self::usage($stderr, $argument);
        }

        try {
            $result = ManifestBuilder::forTheme($projectDirectory)->build();

            if ($output !== null) {
                // A written manifest is a release manifest: its Twig cache directory is named after the templates.
                self::write($output, new ManifestGenerator()->generate($result, self::templateVersion($projectDirectory)));
            }
        } catch (BuildException $exception) {
            fwrite($stderr, $exception->getMessage() . "\n");

            return 1;
        }

        $functions = count(array_filter(
            $result->getTemplateCallables(),
            static fn($callable) => $callable->getKind() === TemplateCallableKind::Function,
        ));

        fwrite($stdout, sprintf(
            "Manifest OK: %d service candidates, %d services, %d hooks, %d routes, %d template functions, %d template filters, %d blocks.%s\n",
            count($result->getCandidates()),
            count($result->getServices()),
            count($result->getHooks()),
            count($result->getRoutes()),
            $functions,
            count($result->getTemplateCallables()) - $functions,
            count($result->getBlocks()),
            $output !== null ? ' Written to ' . $output . '.' : ' Nothing written (no --output).',
        ));

        $overrides = TemplateOverrides::find(
            $projectDirectory . '/' . TemplateRenderer::TEMPLATE_DIRECTORY,
            ManifestBuilder::frameworkSource()->getDirectory(),
        );
        foreach ($overrides as $name) {
            fwrite($stdout, sprintf("Template %s of the theme overrides the framework template.\n", $name));
        }

        return 0;
    }

    /**
     * @return non-empty-string
     */
    private static function templateVersion(string $projectDirectory): string
    {
        $directories = [];
        $themeTemplates = $projectDirectory . '/' . TemplateRenderer::TEMPLATE_DIRECTORY;
        // A theme without src/ has no templates of its own; the framework's templates are versioned anyway.
        if (is_dir($themeTemplates)) {
            $directories[] = $themeTemplates;
        }
        $directories[] = ManifestBuilder::frameworkSource()->getDirectory();

        return TemplateVersion::compute($directories);
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
