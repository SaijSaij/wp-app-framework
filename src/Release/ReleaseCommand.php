<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Release;

use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Filesystem\FileTree;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Project\ComposerProject;
use SplFileInfo;

/**
 * `wp-app-framework release`: builds the release ZIP of a theme or plugin (the
 * `type` in composer.json). The version is read from the theme's style.css or
 * the plugin's main file {slug}.php.
 *
 * Works on a copy of the project, never on the checkout:
 * 1. copy the files git does not ignore,
 * 2. `composer install --no-dev` from the lock file (optionally replacing packages by local checkouts),
 * 3. Strauss with call sites, then the Twig patch,
 * 4. the manifest with the prefixed classes, then compile and check all templates,
 * 5. `npm ci && npm run build`, then the check that every built file the
 *    manifest needs at run time exists (BuildCheck),
 * 6. remove the development files and check the tree for unprefixed names,
 * 7. write the ZIP with the slug as root folder.
 */
#[NoService]
final class ReleaseCommand
{
    public const string USAGE = 'Usage: wp-app-framework release [--output=<zip>] [--expect-version=<version>] [--package-source=<package>=<directory>]...';

    /** Package name of the framework; its prefixed copy runs the build steps that need prefixed classes. */
    private const string FRAMEWORK_PACKAGE = 'saij/wp-app-framework';

    /** Build tool files and directories; never shipped. */
    private const array DEVELOPMENT_FILES = ['node_modules', 'vendor', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json'];

    private const int MAX_LISTED_FINDINGS = 20;

    /**
     * Memory limit of the Strauss run. Strauss parses every file of `vendor/`, including the class map that
     * `--classmap-authoritative` makes large; PHP's default of 128 MB ran out in CI while it sufficed locally.
     */
    private const string STRAUSS_MEMORY_LIMIT = '512M';

    private ?string $workDirectory = null;

    /**
     * @param array<string, string> $packageSources Package name => local checkout replacing the locked version.
     * @param resource              $stdout
     */
    private function __construct(
        private readonly string $projectDirectory,
        private readonly ?string $output,
        private readonly ?string $expectedVersion,
        private readonly array $packageSources,
        private readonly Process $process,
        private $stdout,
    ) {}

    /**
     * @param list<string> $arguments Arguments after `release`.
     * @param resource     $stdout
     * @param resource     $stderr
     */
    public static function run(array $arguments, string $projectDirectory, $stdout, $stderr): int
    {
        $output = null;
        $expectedVersion = null;
        $packageSources = [];
        foreach ($arguments as $argument) {
            if (preg_match('/^--output=(.+)$/', $argument, $match) === 1) {
                $output = $match[1];
            } elseif (preg_match('/^--expect-version=(.+)$/', $argument, $match) === 1) {
                $expectedVersion = $match[1];
            } elseif (preg_match('/^--package-source=([a-z0-9_.-]+\/[a-z0-9_.-]+)=(.+)$/', $argument, $match) === 1) {
                $packageSources[$match[1]] = $match[2];
            } else {
                fwrite($stderr, sprintf("Unknown argument \"%s\".\n%s\n", $argument, self::USAGE));

                return 2;
            }
        }

        $release = new self($projectDirectory, $output, $expectedVersion, $packageSources, new Process($stdout, $stderr), $stdout);
        try {
            $release->build();
        } catch (ReleaseException $exception) {
            fwrite($stderr, "Release failed: " . $exception->getMessage() . "\n");
            if ($release->workDirectory !== null) {
                fwrite($stderr, sprintf("The build directory %s is kept for inspection.\n", $release->workDirectory));
            }

            return 1;
        }

        return 0;
    }

    private function build(): void
    {
        $this->step('Checking the project');
        $project = realpath($this->projectDirectory);
        if ($project === false) {
            throw new ReleaseException(sprintf('Project directory %s does not exist.', $this->projectDirectory));
        }
        try {
            $composerProject = ComposerProject::load($project);
        } catch (BuildException $exception) {
            throw new ReleaseException($exception->getMessage(), 0, $exception);
        }
        $slug = $composerProject->getSlug();
        $kind = $composerProject->getKind();
        $headerFile = $kind->getHeaderFile($slug);
        $version = FileHeader::version($project . '/' . $headerFile);
        if ($this->expectedVersion !== null && $this->expectedVersion !== $version) {
            throw new ReleaseException(sprintf('%s/%s declares version %s, but version %s is being released (e.g. by the tag).', $project, $headerFile, $version, $this->expectedVersion));
        }
        $strauss = StraussConfig::fromComposerFile($project . '/composer.json', $kind->getBootFile($slug));
        self::checkRepositories($project . '/composer.json');
        if (!is_file($project . '/composer.lock')) {
            throw new ReleaseException(sprintf('%s/composer.lock not found; a release installs the locked versions.', $project));
        }
        $packageSources = [];
        foreach ($this->packageSources as $package => $directory) {
            $path = realpath(str_starts_with($directory, '/') ? $directory : $project . '/' . $directory);
            if ($path === false || !is_dir($path)) {
                throw new ReleaseException(sprintf('Directory %s of --package-source=%s does not exist.', $directory, $package));
            }
            $packageSources[$package] = $path;
        }
        $this->print(sprintf('%s %s %s, Strauss prefix %s', $kind->getLabel(), $slug, $version, $strauss->getNamespacePrefix()));
        $this->process->run(['strauss', '--version'], $project);

        $this->step('Copying the project');
        $files = ProjectFiles::of($this->process, $project);
        if (in_array('generated/manifest.php', $files->getFiles(), true)) {
            throw new ReleaseException(sprintf('%s/generated/manifest.php exists in the checkout; only the release build writes it.', $project));
        }
        $work = sys_get_temp_dir() . '/wp-app-framework-release-' . bin2hex(random_bytes(4));
        $this->workDirectory = $work;
        $build = $work . '/' . $slug;
        if (!mkdir($build, 0775, true)) {
            throw new ReleaseException(sprintf('Cannot create %s.', $build));
        }
        $files->copyTo($build, true);
        $this->print(sprintf('%d files copied to %s', count($files->getFiles()), $build));

        $this->step('Installing dependencies');
        $this->process->run(['composer', 'install', '--no-dev', '--classmap-authoritative', '--no-interaction', '--no-progress'], $build);
        foreach ($packageSources as $package => $directory) {
            $this->replacePackage($build, $package, $directory);
        }
        $scanner = PrefixScanner::forInstalledPackages($build . '/vendor');

        $this->step('Prefixing with Strauss');
        $this->process->run([PHP_BINARY, '-d', 'memory_limit=' . self::STRAUSS_MEMORY_LIMIT, $this->process->locate('strauss')], $build);
        $prefixed = $build . '/' . StraussConfig::TARGET_DIRECTORY;
        $replacements = TwigPatcher::patch($prefixed . '/twig/twig', $strauss->getNamespacePrefix());
        $this->print(sprintf('Twig compiler patched (%d replacements)', $replacements));

        $this->step('Building the manifest');
        $tool = $prefixed . '/' . self::FRAMEWORK_PACKAGE . '/bin/wp-app-framework-prefixed.php';
        if (!is_file($tool)) {
            throw new ReleaseException(sprintf('%s not found; Strauss did not copy %s.', $tool, self::FRAMEWORK_PACKAGE));
        }
        $prefixedTool = [PHP_BINARY, $tool, $prefixed . '/autoload.php', $build];
        $this->process->run([...$prefixedTool, 'build', '--output=' . $build . '/generated/manifest.php'], $build);

        $this->step('Checking the compiled templates');
        $compiled = $work . '/compiled-templates';
        $this->process->run([...$prefixedTool, 'compile-templates', '--manifest=' . $build . '/generated/manifest.php', '--output=' . $compiled], $build);
        $this->checkFindings('compiled templates', $scanner->scanDirectory($compiled, static fn(string $file): bool => true));

        if (is_file($build . '/package.json')) {
            $this->step('Building scripts and styles');
            $this->process->run(['npm', 'ci', '--no-audit', '--no-fund'], $build);
            $this->process->run(['npm', 'run', 'build'], $build);
        }
        // Before package.json is removed with the development files: the message names it.
        BuildCheck::check(Manifest::load($build . '/generated/manifest.php'), $build);

        $this->step('Removing development files');
        foreach ([...self::DEVELOPMENT_FILES, ...$files->getExportIgnored()] as $path) {
            self::remove($build . '/' . $path);
        }
        self::removeEmptyDirectories($build);
        foreach (array_unique([$headerFile, $kind->getBootFile($slug), 'generated/manifest.php', StraussConfig::TARGET_DIRECTORY . '/autoload.php']) as $required) {
            if (!is_file($build . '/' . $required)) {
                throw new ReleaseException(sprintf('%s is missing in the release; it must not be marked export-ignore.', $required));
            }
        }
        // Dependencies mention their own class names in messages; strings are checked in the project's own files.
        $this->checkFindings('release', $scanner->scanDirectory(
            $build,
            static fn(string $file): bool => !str_starts_with($file, StraussConfig::TARGET_DIRECTORY . '/'),
        ));

        $this->step('Writing the archive');
        $archive = $this->output ?? $project . '/dist/' . $slug . '-' . $version . '.zip';
        $count = Archive::create($build, $slug, $archive);

        self::remove($work);
        $this->workDirectory = null;
        $this->print(sprintf('Release %s %s written to %s (%d files).', $slug, $version, $archive, $count));
        if ($packageSources !== []) {
            $this->print(sprintf('Not built from the lock file: %s.', implode(', ', array_map(
                static fn(string $package, string $directory): string => $package . ' from ' . $directory,
                array_keys($packageSources),
                $packageSources,
            ))));
        }
    }

    /**
     * A path repository resolves relative to the checkout and does not exist in the copy.
     */
    private static function checkRepositories(string $composerFile): void
    {
        try {
            $composer = json_decode((string) file_get_contents($composerFile), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ReleaseException(sprintf('%s is not valid JSON: %s', $composerFile, $exception->getMessage()));
        }
        $repositories = is_array($composer) && is_array($composer['repositories'] ?? null) ? $composer['repositories'] : [];
        foreach ($repositories as $repository) {
            if (is_array($repository) && ($repository['type'] ?? null) === 'path') {
                throw new ReleaseException(sprintf(
                    '%s declares the path repository "%s"; a release installs the locked packages in a copy of the project. Require the package from a vcs or composer repository, and use --package-source for a local checkout.',
                    $composerFile,
                    is_string($repository['url'] ?? null) ? $repository['url'] : '?',
                ));
            }
        }
    }

    private function replacePackage(string $build, string $package, string $directory): void
    {
        $target = $build . '/vendor/' . $package;
        if (!is_dir($target)) {
            throw new ReleaseException(sprintf('Package %s of --package-source is not installed in %s/vendor.', $package, $build));
        }
        self::remove($target);
        // Like a distribution archive of the package: without its export-ignore files.
        ProjectFiles::of($this->process, $directory)->copyTo($target, false);
        $this->print(sprintf('%s replaced by %s (not from the lock file)', $package, $directory));
    }

    /**
     * @param list<string> $findings
     */
    private function checkFindings(string $scope, array $findings): void
    {
        if ($findings === []) {
            $this->print(sprintf('No unprefixed references in the %s.', $scope));

            return;
        }

        $listed = array_slice($findings, 0, self::MAX_LISTED_FINDINGS);
        throw new ReleaseException(sprintf(
            "%d unprefixed references to dependencies in the %s:\n- %s%s",
            count($findings),
            $scope,
            implode("\n- ", $listed),
            count($findings) > count($listed) ? "\n- …" : '',
        ));
    }

    /**
     * Removes a file, a symbolic link (not its target) or a directory tree.
     */
    private static function remove(string $path): void
    {
        try {
            FileTree::remove($path);
        } catch (RuntimeException $exception) {
            throw new ReleaseException($exception->getMessage(), 0, $exception);
        }
    }

    private static function removeEmptyDirectories(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink() && new FilesystemIterator($entry->getPathname())->valid() === false && !rmdir($entry->getPathname())) {
                throw new ReleaseException(sprintf('Cannot remove the empty directory %s.', $entry->getPathname()));
            }
        }
    }

    private function step(string $title): void
    {
        fwrite($this->stdout, "\n==> " . $title . "\n");
    }

    private function print(string $line): void
    {
        fwrite($this->stdout, $line . "\n");
    }
}
