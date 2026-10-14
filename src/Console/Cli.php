<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Console;

use ReflectionClass;
use Saij\WpAppFramework\Cache\CodeGeneration;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Filesystem\AtomicFile;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\Discovery\Source;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Query\MetaFieldsGenerator;
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
 *   nothing is written. A source checkout of a theme or plugin must not contain
 *   generated/manifest.php: the framework treats its presence as a release build.
 * - `release [...]` builds the release ZIP (see ReleaseCommand).
 * - `meta-fields <meta class> [--force]` writes the class with #[MetaFields] for a meta class next to it
 *   ({MetaClass}Fields.php); an existing file only with --force. The only command that writes source code.
 * - `compile-templates --manifest=<file> --output=<directory>` compiles all
 *   templates as release mode would; internal step of `release`, run with the
 *   prefixed classes of the build copy.
 */
#[NoService]
final class Cli
{
    private const string USAGE = "Usage:\n  wp-app-framework build [--output=<file>]\n  wp-app-framework release [--output=<zip>] [--expect-version=<version>] [--package-source=<package>=<directory>]...\n  wp-app-framework meta-fields <meta class> [--force]";

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
            'meta-fields' => self::metaFields($arguments, $projectDirectory, $stdout, $stderr),
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
    private static function metaFields(array $arguments, string $projectDirectory, $stdout, $stderr): int
    {
        $force = false;
        $metaClass = null;
        foreach ($arguments as $argument) {
            if ($argument === '--force') {
                $force = true;
            } elseif ($metaClass === null && !str_starts_with($argument, '-')) {
                $metaClass = ltrim($argument, '\\');
            } else {
                return self::usage($stderr, $argument);
            }
        }
        if ($metaClass === null) {
            fwrite($stderr, "Usage: wp-app-framework meta-fields <meta class> [--force]\n");

            return 2;
        }

        try {
            /** @var class-string $metaClass Checked by metaFieldValues(). */
            $values = ManifestBuilder::forProject($projectDirectory)->metaFieldValues($metaClass);
        } catch (BuildException $exception) {
            fwrite($stderr, $exception->getMessage() . "\n");

            return 1;
        }
        $file = dirname((string) new ReflectionClass($metaClass)->getFileName()) . '/' . substr($metaClass, (int) strrpos('\\' . $metaClass, '\\')) . MetaFieldsGenerator::SUFFIX . '.php';
        if (is_file($file) && !$force) {
            fwrite($stderr, sprintf("%s exists; use --force to replace it.\n", $file));

            return 1;
        }
        AtomicFile::write($file, MetaFieldsGenerator::generate($metaClass, $values));
        fwrite($stdout, sprintf("Written %s.\n", $file));

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
            $result = ManifestBuilder::forProject($projectDirectory)->build();

            if ($output !== null) {
                // A written manifest is a release manifest: its Twig cache directory is named after the templates, its
                // cache entries after the templates and the PHP code.
                $codeVersion = CodeGeneration::codeVersion(array_map(static fn(Source $source): string => $source->getDirectory(), $result->getSources()));
                AtomicFile::write($output, new ManifestGenerator()->generate($result, self::templateVersion($projectDirectory), $codeVersion), true);
            }
        } catch (BuildException $exception) {
            fwrite($stderr, $exception->getMessage() . "\n");

            return 1;
        }

        $functions = count(array_filter(
            $result->getTemplateCallables(),
            static fn($callable) => $callable->getKind() === TemplateCallableKind::Function,
        ));

        $public = count(array_filter($result->getTemplateCallables(), static fn($callable) => $callable->isPublic()));
        fwrite($stdout, sprintf(
            "Manifest OK (%s %s): %d service candidates, %d services, %d hooks, %d routes, %d template functions, %d template filters, %d blocks, %d CLI commands.%s\n",
            $result->getKind()->getLabel(),
            $result->getSlug() ?? '',
            count($result->getCandidates()),
            count($result->getServices()),
            count($result->getHooks()),
            count($result->getRoutes()),
            $functions,
            count($result->getTemplateCallables()) - $functions,
            count($result->getBlocks()),
            array_sum(array_map(static fn($command) => count($command->getSubcommands()), $result->getCliCommands())),
            $output !== null ? ' Written to ' . $output . '.' : ' Nothing written (no --output).',
        ));
        if ($result->getMarkup() !== null) {
            fwrite($stdout, sprintf("Block markup OK: %d templates, template parts and patterns checked.\n", count($result->getMarkup()->getFiles())));
        }
        if ($result->getPublicApis() !== [] || $public > 0) {
            fwrite($stdout, sprintf("Public: %d APIs, %d template functions and filters.\n", count($result->getPublicApis()), $public));
        }
        if ($result->getTemplateProviders() !== []) {
            fwrite($stdout, sprintf("Templates use the public template callables of: %s.\n", implode(', ', $result->getTemplateProviders())));
        }

        $overrides = TemplateOverrides::find(
            $projectDirectory . '/' . TemplateRenderer::TEMPLATE_DIRECTORY,
            ManifestBuilder::frameworkSource()->getDirectory(),
        );
        foreach ($overrides as $name) {
            fwrite($stdout, sprintf("Template %s of the %s overrides the framework template.\n", $name, $result->getKind()->getLabel()));
        }

        return 0;
    }

    /**
     * @return non-empty-string
     */
    private static function templateVersion(string $projectDirectory): string
    {
        $directories = [];
        $projectTemplates = $projectDirectory . '/' . TemplateRenderer::TEMPLATE_DIRECTORY;
        // A project without src/ has no templates of its own; the framework's templates are versioned anyway.
        if (is_dir($projectTemplates)) {
            $directories[] = $projectTemplates;
        }
        $directories[] = ManifestBuilder::frameworkSource()->getDirectory();

        return TemplateVersion::compute($directories);
    }
}
