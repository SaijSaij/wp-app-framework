<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Manifest\DevCache;

use ReflectionClass;
use RuntimeException;
use Saij\WpAppFramework\Asset\ScriptKind;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Filesystem\AtomicFile;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\Discovery\ClassDiscovery;
use Saij\WpAppFramework\Manifest\Discovery\Source;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Style\StyleAssets;
use Throwable;

/**
 * Manifest cache for development mode, following Symfony's ConfigCache in debug mode.
 *
 * Next to the manifest, a meta file lists the resources the build depended on:
 * the file lists of the source directories, files whose content matters, the
 * file lists of the directories with block markup (also if they are missing) and,
 * per scanned class, its files and signature hash. A request compares file
 * modification times with the manifest; only for newer files the content hash
 * or class signature is recomputed, and only a difference triggers a rebuild.
 * Method bodies do not affect the signature.
 *
 * The meta file stores paths relative to named base directories only (for
 * example ['project', 'src/Foo.php']), never absolute paths.
 *
 * @phpstan-import-type ManifestData from Manifest
 * @phpstan-type PathRef array{0: string, 1: string}
 * @phpstan-type Meta array{
 *     format: int,
 *     directories: list<array{0: string, 1: string, 2: string}>,
 *     files: list<array{0: string, 1: string, 2: string}>,
 *     markup: list<array{0: string, 1: string, 2: string}>,
 *     classes: array<class-string, array{files: list<PathRef>, signature: string}>
 * }
 */
#[NoService]
final class DevCache
{
    private const int META_FORMAT = 2;

    /**
     * @param string                $directory Cache directory, outside the project.
     * @param array<string, string> $bases     Base directories by name, used to make paths relative.
     */
    public function __construct(
        private readonly string $directory,
        private readonly array $bases,
        private readonly ManifestBuilder $builder,
    ) {}

    /**
     * Loads the cached manifest, rebuilding it first if it is not fresh.
     *
     * @return ManifestData
     */
    public function load(): array
    {
        if (!$this->isFresh()) {
            $this->rebuild();
        }

        return Manifest::load($this->manifestFile());
    }

    /**
     * The directories the manifest was built from (source directories and the script directory), as absolute paths;
     * call after load().
     *
     * @return list<string>
     */
    public function getSourceDirectories(): array
    {
        $meta = is_file($this->metaFile()) ? $this->readMeta($this->metaFile()) : null;
        if ($meta === null) {
            throw new RuntimeException(sprintf('The manifest cache %s has no meta file; load the manifest first.', $this->directory));
        }

        return array_map(fn(array $directory): string => $this->absolute($directory[0], $directory[1]), $meta['directories']);
    }

    public function isFresh(): bool
    {
        $manifestFile = $this->manifestFile();
        $metaFile = $this->metaFile();

        if (!is_file($manifestFile) || !is_file($metaFile)) {
            return false;
        }

        $meta = $this->readMeta($metaFile);
        if ($meta === null) {
            return false;
        }

        // Files changed in the same second as the build are rechecked (>=), as mtimes have a resolution of one second.
        $time = (int) filemtime($manifestFile);

        foreach ($meta['directories'] as [$base, $relative, $hash]) {
            $directory = $this->absolute($base, $relative);
            if (!is_dir($directory) || self::listingHash($directory) !== $hash) {
                return false;
            }
        }

        foreach ($meta['markup'] as [$base, $relative, $hash]) {
            if (self::markupListingHash($this->absolute($base, $relative)) !== $hash) {
                return false;
            }
        }

        foreach ($meta['files'] as [$base, $relative, $hash]) {
            $file = $this->absolute($base, $relative);
            if (!is_file($file)) {
                return false;
            }
            if (filemtime($file) >= $time && hash_file('xxh128', $file) !== $hash) {
                return false;
            }
        }

        foreach ($meta['classes'] as $class => ['files' => $files, 'signature' => $signature]) {
            foreach ($files as [$base, $relative]) {
                $file = $this->absolute($base, $relative);
                if (!is_file($file)) {
                    return false;
                }
                if (filemtime($file) < $time) {
                    continue;
                }

                try {
                    $exists = class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class);
                    if (!$exists || Signature::of(new ReflectionClass($class)) !== $signature) {
                        return false;
                    }
                } catch (Throwable) {
                    // Not loadable any more: the rebuild reports the actual error.
                    return false;
                }

                break;
            }
        }

        return true;
    }

    /**
     * @return Meta|null Null if the file has another format version.
     */
    private function readMeta(string $file): ?array
    {
        $meta = require $file;
        if (!is_array($meta) || ($meta['format'] ?? null) !== self::META_FORMAT) {
            return null;
        }

        /** @var Meta $meta Written by meta() in this format version. */
        return $meta;
    }

    private function rebuild(): void
    {
        $result = $this->builder->build();
        $code = new ManifestGenerator()->generate($result);
        $meta = $this->meta($result);

        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException(sprintf('Cannot create the manifest cache directory %s.', $this->directory));
        }
        if (!is_writable($this->directory)) {
            throw new RuntimeException(sprintf('The manifest cache directory %s is not writable.', $this->directory));
        }

        // Manifest first: if writing the meta file fails, the old meta no longer matches and forces a rebuild.
        AtomicFile::write($this->manifestFile(), $code);
        AtomicFile::write($this->metaFile(), "<?php\n\n// Generated by saij/wp-app-framework. Do not edit.\n\nreturn " . var_export($meta, true) . ";\n");
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(BuildResult $result): array
    {
        $listed = array_map(static fn(Source $source): string => $source->getDirectory(), $result->getSources());
        // The script directory decides whether the consumer has a stylesheet; it is usually a source directory as well.
        $scriptDirectory = $result->getScriptDirectory();
        if ($scriptDirectory !== null && !in_array($scriptDirectory, $listed, true)) {
            $listed[] = $scriptDirectory;
        }
        $directories = [];
        foreach ($listed as $directory) {
            $directories[] = [...$this->relative($directory), self::listingHash($directory)];
        }

        $markup = [];
        $trackedFiles = $result->getTrackedFiles();
        if ($result->getMarkup() !== null) {
            foreach ($result->getMarkup()->getWatchedDirectories() as $directory) {
                $markup[] = [...$this->relative($directory), self::markupListingHash($directory)];
            }
            $trackedFiles = [...$trackedFiles, ...$result->getMarkup()->getAbsoluteFiles()];
        }

        $files = [];
        foreach ($trackedFiles as $file) {
            $files[] = [...$this->relative($file), hash_file('xxh128', $file)];
        }

        // Only files in the source directories: parent classes from dependencies may be loaded from the vendor
        // directory of another consumer in development mode. Dependencies change with composer install, which
        // changes vendor/composer/installed.json (a tracked file); the boot checks that the shared Twig matches.
        $sourceDirectories = array_map(static fn(Source $source): string => $source->getDirectory() . '/', $result->getSources());
        $inSources = static function (string $file) use ($sourceDirectories): bool {
            foreach ($sourceDirectories as $directory) {
                if (str_starts_with($file, $directory)) {
                    return true;
                }
            }

            return false;
        };

        $classes = [];
        foreach ($result->getClasses() as $name => $class) {
            $classes[$name] = [
                'files' => array_map($this->relative(...), array_values(array_filter(Signature::files($class), $inSources))),
                'signature' => Signature::of($class),
            ];
        }

        return [
            'format' => self::META_FORMAT,
            'directories' => $directories,
            'files' => $files,
            'markup' => $markup,
            'classes' => $classes,
        ];
    }

    /**
     * Splits an absolute path into the name of the longest matching base and the relative rest.
     *
     * @return array{0: string, 1: string}
     */
    private function relative(string $path): array
    {
        $match = null;
        foreach ($this->bases as $name => $base) {
            if (($path === $base || str_starts_with($path, $base . '/'))
                && ($match === null || strlen($base) > strlen($this->bases[$match]))) {
                $match = $name;
            }
        }

        if ($match === null) {
            throw new RuntimeException(sprintf(
                '%s is outside the base directories of the manifest cache (%s); its path cannot be stored relatively.',
                $path,
                implode(', ', array_map(
                    static fn(string $name, string $base): string => $name . ': ' . $base,
                    array_keys($this->bases),
                    $this->bases,
                )),
            ));
        }

        return [$match, ltrim(substr($path, strlen($this->bases[$match])), '/')];
    }

    private function absolute(string $base, string $relative): string
    {
        if (!isset($this->bases[$base])) {
            throw new RuntimeException(sprintf('Unknown base directory "%s" in %s.', $base, $this->metaFile()));
        }

        return $relative === '' ? $this->bases[$base] : $this->bases[$base] . '/' . $relative;
    }

    /**
     * Hash of the PHP files, editor scripts and view scripts below a directory
     * and of the stylesheet in it: adding or removing a script or the
     * stylesheet changes the manifest as well.
     */
    private static function listingHash(string $directory): string
    {
        return hash('xxh128', implode("\n", [
            ...ClassDiscovery::files($directory),
            ...ClassDiscovery::files($directory, ScriptKind::Editor->value),
            ...ClassDiscovery::files($directory, ScriptKind::View->value),
            ...(is_file($directory . '/' . StyleAssets::SOURCE) ? [StyleAssets::SOURCE] : []),
        ]));
    }

    /**
     * Hash of all files below a directory with block markup; a missing directory has a hash of its own, so that
     * creating it rebuilds the manifest.
     */
    private static function markupListingHash(string $directory): string
    {
        if (!file_exists($directory)) {
            return 'missing';
        }

        return hash('xxh128', implode("\n", is_dir($directory) ? ClassDiscovery::files($directory, '') : ['']));
    }

    private function manifestFile(): string
    {
        return $this->directory . '/manifest.php';
    }

    private function metaFile(): string
    {
        return $this->directory . '/manifest.meta.php';
    }
}
