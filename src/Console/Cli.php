<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Console;

use ReflectionClass;
use RuntimeException;
use WebCodingCat\WpAppFramework\Asset\ImageVariants;
use WebCodingCat\WpAppFramework\Cache\CodeGeneration;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Filesystem\AtomicFile;
use WebCodingCat\WpAppFramework\I18n\PotCommand;
use WebCodingCat\WpAppFramework\Manifest\BuildException;
use WebCodingCat\WpAppFramework\Manifest\Discovery\Source;
use WebCodingCat\WpAppFramework\Manifest\ManifestBuilder;
use WebCodingCat\WpAppFramework\Manifest\ManifestGenerator;
use WebCodingCat\WpAppFramework\Query\Field\FieldTypes;
use WebCodingCat\WpAppFramework\Query\FieldClassGenerator;
use WebCodingCat\WpAppFramework\Query\MetaFields;
use WebCodingCat\WpAppFramework\Release\ReleaseCommand;
use WebCodingCat\WpAppFramework\Release\ReleaseException;
use WebCodingCat\WpAppFramework\Release\TemplateCompilation;
use WebCodingCat\WpAppFramework\Table\TableFields;
use WebCodingCat\WpAppFramework\Twig\TemplateCallableKind;
use WebCodingCat\WpAppFramework\Twig\TemplateOverrides;
use WebCodingCat\WpAppFramework\Twig\TemplateRenderer;
use WebCodingCat\WpAppFramework\Twig\TemplateVersion;

/**
 * Command line entry point.
 *
 * - `build [--output=<file>]` builds and checks the manifest. Without --output
 *   nothing is written. A source checkout of a theme or plugin must not contain
 *   generated/manifest.php: the framework treats its presence as a release build.
 * - `release [...]` builds the release ZIP (see ReleaseCommand).
 * - `meta-fields <meta class> [--force]` writes the class with #[MetaFields] for a meta class next to it
 *   ({MetaClass}Fields.php); an existing file only with --force.
 * - `table-fields <row class> [--force]` writes the class with #[TableFields] for a row class next to it
 *   ({RowClass}Fields.php); an existing file only with --force. The two are the only commands that write source code.
 * - `pot` writes languages/{text domain}.pot, the texts of the project to translate (attributes, templates, PHP).
 * - `compile-templates --manifest=<file> --output=<directory>` compiles all
 *   templates as release mode would; internal step of `release`, run with the
 *   prefixed classes of the build copy.
 */
#[NoService]
final class Cli
{
    private const string USAGE = "Usage:\n  wp-app-framework build [--output=<file>]\n  wp-app-framework release [--output=<zip>] [--expect-version=<version>] [--package-source=<package>=<directory>]...\n  wp-app-framework meta-fields <meta class> [--force]\n  wp-app-framework table-fields <row class> [--force]\n  wp-app-framework pot";

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
            'meta-fields' => self::fields('meta-fields', $arguments, $projectDirectory, $stdout, $stderr),
            'table-fields' => self::fields('table-fields', $arguments, $projectDirectory, $stdout, $stderr),
            'pot' => self::pot($arguments, $projectDirectory, $stdout, $stderr),
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
     * `pot`: writes the template of the translations.
     *
     * @param list<string> $arguments
     * @param resource     $stdout
     * @param resource     $stderr
     */
    private static function pot(array $arguments, string $projectDirectory, $stdout, $stderr): int
    {
        if ($arguments !== []) {
            return self::usage($stderr, $arguments[0]);
        }
        try {
            ['file' => $file, 'count' => $count, 'notices' => $notices] = PotCommand::run($projectDirectory);
        } catch (BuildException $exception) {
            fwrite($stderr, $exception->getMessage() . "\n");

            return 1;
        }
        foreach ($notices as $notice) {
            fwrite($stdout, $notice . "\n");
        }
        fwrite($stdout, sprintf("Written %s (%d texts).\n", $file, $count));

        return 0;
    }

    /**
     * `meta-fields` and `table-fields`: write the field class of a meta class or row class.
     *
     * @param 'meta-fields'|'table-fields' $command
     * @param list<string>                 $arguments
     * @param resource                     $stdout
     * @param resource                     $stderr
     */
    private static function fields(string $command, array $arguments, string $projectDirectory, $stdout, $stderr): int
    {
        $force = false;
        $class = null;
        foreach ($arguments as $argument) {
            if ($argument === '--force') {
                $force = true;
            } elseif ($class === null && !str_starts_with($argument, '-')) {
                $class = ltrim($argument, '\\');
            } else {
                return self::usage($stderr, $argument);
            }
        }
        if ($class === null) {
            fwrite($stderr, sprintf("Usage: wp-app-framework %s <%s class> [--force]\n", $command, $command === 'meta-fields' ? 'meta' : 'row'));

            return 2;
        }

        try {
            $builder = ManifestBuilder::forProject($projectDirectory);
            /** @var class-string $class Checked by metaFieldValues() and tableFieldValues(). */
            $fields = $command === 'meta-fields' ? FieldTypes::queryable($builder->metaFieldValues($class)) : $builder->tableFieldValues($class);
        } catch (BuildException $exception) {
            fwrite($stderr, $exception->getMessage() . "\n");

            return 1;
        }
        $file = FieldClassGenerator::file((string) new ReflectionClass($class)->getFileName(), $class);
        if (is_file($file) && !$force) {
            fwrite($stderr, sprintf("%s exists; use --force to replace it.\n", $file));

            return 1;
        }
        AtomicFile::write($file, FieldClassGenerator::generate($class, $command === 'meta-fields' ? MetaFields::class : TableFields::class, $command, $fields));
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
                // Image variants next to the release manifest: generated/assets/.
                if ($result->getAssetDirectory() !== null) {
                    try {
                        ImageVariants::generate($result->getAssets(), $result->getAssetDirectory(), dirname($output) . '/' . ImageVariants::DIRECTORY);
                    } catch (RuntimeException $exception) {
                        fwrite($stderr, $exception->getMessage() . "\n");

                        return 1;
                    }
                }
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
            "Manifest OK (%s %s): %d service candidates, %d services, %d hooks, %d routes, %d template functions, %d template filters, %d blocks, %d CLI commands, %d cron events, %d cron tasks, %d tables, %d lifecycle methods.%s\n",
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
            count($result->getCronEvents()),
            count($result->getCronTasks()),
            count($result->getTables()['classes']),
            array_sum(array_map(count(...), $result->getLifecycle())),
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

        if ($result->getUnloadedClasses() !== []) {
            fwrite($stdout, sprintf(
                "Not loaded by the build (building on a class outside the project, its Composer dependencies and PHP): %s.\n",
                implode(', ', array_map(
                    static fn(string $class, string $parent): string => sprintf('%s (%s)', $class, $parent),
                    array_keys($result->getUnloadedClasses()),
                    $result->getUnloadedClasses(),
                )),
            ));
        }
        foreach ($result->getNotices() as $notice) {
            fwrite($stdout, $notice . "\n");
        }
        $variants = count(ImageVariants::files($result->getAssets()));
        if ($result->getAssets() !== []) {
            fwrite($stdout, sprintf("Assets: %d static files, %d image variants.\n", count($result->getAssets()), $variants));
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
