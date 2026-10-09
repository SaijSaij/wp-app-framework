<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Manifest\DevCache;

use ReflectionClass;
use RuntimeException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\Discovery\ClassDiscovery;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Throwable;

/**
 * Manifest cache for development mode, following Symfony's ConfigCache in debug mode.
 *
 * Next to the manifest, a meta file lists the resources the build depended on:
 * the file lists of the source directories, files whose content matters and,
 * per scanned class, its files and signature hash. A request compares file
 * modification times with the manifest; only for newer files the content hash
 * or class signature is recomputed, and only a difference triggers a rebuild.
 * Method bodies do not affect the signature.
 *
 * The meta file stores paths relative to named base directories only (for
 * example ['theme', 'src/Foo.php']), never absolute paths.
 *
 * @phpstan-type PathRef array{0: string, 1: string}
 * @phpstan-type Meta array{
 *     format: int,
 *     directories: list<array{0: string, 1: string, 2: string}>,
 *     files: list<array{0: string, 1: string, 2: string}>,
 *     classes: array<class-string, array{files: list<PathRef>, signature: string}>
 * }
 */
#[NoService]
final class DevCache
{
    private const int META_FORMAT = 1;

    /**
     * @param string                $directory Cache directory, outside the theme.
     * @param array<string, string> $bases     Base directories by name, used to make paths relative.
     */
    public function __construct(
        private readonly string $directory,
        private readonly array $bases,
        private readonly ManifestBuilder $builder,
    ) {
    }

    /**
     * Loads the cached manifest, rebuilding it first if it is not fresh.
     *
     * @return array<string, mixed>
     */
    public function load(): array
    {
        if (!$this->isFresh()) {
            $this->rebuild();
        }

        return Manifest::load($this->manifestFile());
    }

    public function isFresh(): bool
    {
        $manifestFile = $this->manifestFile();
        $metaFile = $this->metaFile();

        if (!is_file($manifestFile) || !is_file($metaFile)) {
            return false;
        }

        $meta = require $metaFile;
        if (!is_array($meta) || ($meta['format'] ?? null) !== self::META_FORMAT) {
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
        self::write($this->manifestFile(), $code);
        self::write($this->metaFile(), "<?php\n\n// Generated by saij/wp-app-framework. Do not edit.\n\nreturn " . var_export($meta, true) . ";\n");
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(BuildResult $result): array
    {
        $directories = [];
        foreach ($result->getSources() as $source) {
            $directories[] = [...$this->relative($source->getDirectory()), self::listingHash($source->getDirectory())];
        }

        $files = [];
        foreach ($result->getTrackedFiles() as $file) {
            $files[] = [...$this->relative($file), hash_file('xxh128', $file)];
        }

        $classes = [];
        foreach ($result->getClasses() as $name => $class) {
            $classes[$name] = [
                'files' => array_map($this->relative(...), Signature::files($class)),
                'signature' => Signature::of($class),
            ];
        }

        return [
            'format' => self::META_FORMAT,
            'directories' => $directories,
            'files' => $files,
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
                    static fn (string $name, string $base): string => $name . ': ' . $base,
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

    private static function listingHash(string $directory): string
    {
        return hash('xxh128', implode("\n", ClassDiscovery::files($directory)));
    }

    private static function write(string $file, string $content): void
    {
        $temporary = tempnam(dirname($file), basename($file));
        if ($temporary === false || file_put_contents($temporary, $content) === false) {
            throw new RuntimeException(sprintf('Cannot write a temporary file for %s.', $file));
        }

        chmod($temporary, 0644);

        if (!rename($temporary, $file)) {
            unlink($temporary);
            throw new RuntimeException(sprintf('Cannot move the temporary file to %s.', $file));
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
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
