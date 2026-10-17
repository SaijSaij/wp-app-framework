<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Manifest\DevCache;

use Closure;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use SplFileInfo;
use Throwable;
use WebCodingCat\WpAppFramework\Asset\ImageVariants;
use WebCodingCat\WpAppFramework\Asset\ScriptKind;
use WebCodingCat\WpAppFramework\Cache\CodeGeneration;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Filesystem\AtomicFile;
use WebCodingCat\WpAppFramework\Manifest\BuildResult;
use WebCodingCat\WpAppFramework\Manifest\Discovery\ClassDiscovery;
use WebCodingCat\WpAppFramework\Manifest\Discovery\Source;
use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Manifest\ManifestBuilder;
use WebCodingCat\WpAppFramework\Manifest\ManifestGenerator;
use WebCodingCat\WpAppFramework\Style\StyleAssets;

/**
 * Manifest cache for development mode, following Symfony's ConfigCache in debug mode.
 *
 * Next to the manifest, a meta file lists the resources the build depended on:
 * the file lists of the source directories, files whose content matters, the
 * file lists of the directories with block markup and of the asset directory
 * (also if they are missing) and,
 * per scanned class, its files and signature hash. A request compares file
 * modification times with the manifest; only for newer files the content hash
 * or class signature is recomputed, and only a difference triggers a rebuild.
 * Method bodies do not affect the signature.
 *
 * The meta file stores paths relative to named base directories only (for
 * example ['project', 'src/Foo.php']), never absolute paths.
 *
 * Each watched directory is walked once per request: the walk yields the
 * file list for the check and the entries of the code generation of the
 * cache (getSourceEntries()). The builder is created only for a rebuild.
 *
 * @phpstan-import-type ManifestData from Manifest
 * @phpstan-type PathRef array{0: string, 1: string}
 * @phpstan-type Meta array{
 *     format: int,
 *     directories: list<array{0: string, 1: string, 2: string}>,
 *     files: list<array{0: string, 1: string, 2: string}>,
 *     markup: list<array{0: string, 1: string, 2: string}>,
 *     assets: list<array{0: string, 1: string, 2: string}>,
 *     classes: array<class-string, array{files: list<PathRef>, signature: string}>
 * }
 */
#[NoService]
final class DevCache
{
    private const int META_FORMAT = 3;

    /** @var array<string, array{listing: string, entries: list<string>}> Walked directories of this request. */
    private array $scans = [];

    /**
     * @param string                   $directory Cache directory, outside the project.
     * @param array<string, string>    $bases     Base directories by name, used to make paths relative.
     * @param Closure(): ManifestBuilder $builder Creates the builder of the project, called only for a rebuild.
     */
    public function __construct(
        private readonly string $directory,
        private readonly array $bases,
        private readonly Closure $builder,
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

    /**
     * The entries of the code generation (CodeGeneration::entry()) of the directories the manifest was built from,
     * from the walk of the check; call after load().
     *
     * @return list<array{0: string, 1: list<string>}>
     */
    public function getSourceEntries(): array
    {
        $entries = array_map(fn(string $directory): array => [$directory, $this->scan($directory)['entries']], $this->getSourceDirectories());
        foreach ($this->readMeta($this->metaFile())['assets'] ?? [] as [$base, $relative]) {
            $directory = $this->absolute($base, $relative);
            if (is_dir($directory)) {
                $entries[] = [$directory, $this->scan($directory)['entries']];
            }
        }

        return $entries;
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
            if (!is_dir($directory) || $this->scan($directory)['listing'] !== $hash) {
                return false;
            }
        }

        foreach ($meta['markup'] as [$base, $relative, $hash]) {
            if (self::markupListingHash($this->absolute($base, $relative)) !== $hash) {
                return false;
            }
        }

        foreach ($meta['assets'] as [$base, $relative, $hash]) {
            if (self::assetListingHash($this->absolute($base, $relative)) !== $hash) {
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
        $result = ($this->builder)()->build();
        // Image variants next to the manifest cache: wp-content/cache/{slug}/assets/.
        if ($result->getAssetDirectory() !== null) {
            ImageVariants::generate($result->getAssets(), $result->getAssetDirectory(), dirname($this->directory) . '/' . ImageVariants::DIRECTORY, true);
        }
        // The rebuild reads the current files; walk them again for the meta file and the code generation.
        $this->scans = [];
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
        AtomicFile::write($this->metaFile(), "<?php\n\n// Generated by webcodingcat/wp-app-framework. Do not edit.\n\nreturn " . var_export($meta, true) . ";\n");
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(BuildResult $result): array
    {
        $listed = array_map(static fn(Source $source): string => $source->getDirectory(), $result->getSources());
        // A stylesheet in the script directory is a build error; it is usually a source directory as well.
        $scriptDirectory = $result->getScriptDirectory();
        if ($scriptDirectory !== null && !in_array($scriptDirectory, $listed, true)) {
            $listed[] = $scriptDirectory;
        }
        $directories = [];
        foreach ($listed as $directory) {
            $directories[] = [...$this->relative($directory), $this->scan($directory)['listing']];
        }

        $markup = [];
        $trackedFiles = $result->getTrackedFiles();
        if ($result->getMarkup() !== null) {
            foreach ($result->getMarkup()->getWatchedDirectories() as $directory) {
                $markup[] = [...$this->relative($directory), self::markupListingHash($directory)];
            }
            $trackedFiles = [...$trackedFiles, ...$result->getMarkup()->getAbsoluteFiles()];
        }

        // Directories whose listing matters: the asset directory and the PO files of the translations.
        $assets = [];
        foreach ([$result->getAssetDirectory(), $result->getLanguageDirectory()] as $directory) {
            if ($directory !== null) {
                $assets[] = [...$this->relative($directory), self::assetListingHash($directory)];
            }
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
            'assets' => $assets,
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
     * One walk of a directory per request: the hash of its PHP files, editor, view and settings scripts and stylesheet
     * (adding or removing one changes the manifest) and the entries of the code generation of all its files.
     *
     * @return array{listing: string, entries: list<string>}
     */
    private function scan(string $directory): array
    {
        if (isset($this->scans[$directory])) {
            return $this->scans[$directory];
        }
        $suffixes = ['.php' => [], ScriptKind::Editor->value => [], ScriptKind::View->value => [], ScriptKind::Settings->value => []];
        $entries = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $entries[] = CodeGeneration::entry($file);
            foreach (array_keys($suffixes) as $suffix) {
                if (str_ends_with($file->getFilename(), $suffix)) {
                    $suffixes[$suffix][] = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
                }
            }
        }
        $listing = [];
        foreach ($suffixes as $relatives) {
            // The order of ClassDiscovery::files(), so existing cache entries stay valid.
            sort($relatives, SORT_STRING);
            array_push($listing, ...$relatives);
        }
        // The stylesheet in its former place, src/styles.css, is a build error.
        if (is_file($directory . '/' . StyleAssets::FORMER_SOURCE)) {
            $listing[] = StyleAssets::FORMER_SOURCE;
        }

        return $this->scans[$directory] = ['listing' => hash('xxh128', implode("\n", $listing)), 'entries' => $entries];
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

    /**
     * Hash of the names of all files below the asset directory (stylesheet, scripts without a class, static files),
     * hidden files left out; a missing directory has a hash of its own, so that creating it rebuilds the manifest.
     */
    private static function assetListingHash(string $directory): string
    {
        if (!is_dir($directory)) {
            return 'missing';
        }
        $names = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
            if ($file->isFile() && !preg_match('#(^|/)\.#', $relative)) {
                $names[] = $relative;
            }
        }
        sort($names, SORT_STRING);

        return hash('xxh128', implode("\n", $names));
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
