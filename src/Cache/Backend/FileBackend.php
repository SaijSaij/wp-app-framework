<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Backend;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Filesystem\AtomicFile;
use SplFileInfo;

/**
 * PHP files below a directory of the instance (wp-content/cache/{slug}/data):
 * written atomically with var_export(), read with include, so OPcache keeps
 * them in memory. Available if the directory exists or can be created and is
 * writable. Expired files are misses; cleanUp() deletes them (WP-Cron).
 *
 * @internal
 */
#[NoService]
final class FileBackend implements Backend
{
    private ?bool $available = null;

    public function __construct(private readonly string $directory) {}

    public function isAvailable(): bool
    {
        if ($this->available === null) {
            $this->available = (is_dir($this->directory) || mkdir($this->directory, 0775, true) || is_dir($this->directory))
                && is_writable($this->directory);
        }

        return $this->available;
    }

    public function get(string $key): ?array
    {
        $file = $this->file($key);
        if (!is_file($file)) {
            return null;
        }

        $entry = include $file;
        if (!is_array($entry) || !is_int($entry['expires'] ?? null) || !array_key_exists('value', $entry) || $entry['expires'] <= time()) {
            return null;
        }

        return ['value' => $entry['value']];
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $entry = ['expires' => time() + $ttl, 'value' => $value];
        AtomicFile::write($this->file($key), "<?php\n\nreturn " . var_export($entry, true) . ";\n", true);
    }

    /**
     * Deletes expired entries and the files left over by interrupted writes.
     */
    public function cleanUp(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            if (!str_ends_with($path, '.php')) {
                // A temporary file of AtomicFile::write() older than a minute was left behind.
                if ($file->getMTime() < time() - 60) {
                    unlink($path);
                }
                continue;
            }
            $entry = include $path;
            if (!is_array($entry) || !is_int($entry['expires'] ?? null) || $entry['expires'] <= time()) {
                unlink($path);
            }
        }
    }

    private function file(string $key): string
    {
        return sprintf('%s/%d/%s/%s.php', $this->directory, get_current_blog_id(), substr($key, 0, 2), $key);
    }
}
