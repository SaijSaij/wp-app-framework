<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Twig;

use Closure;
use FilesystemIterator;
use SplFileInfo;
use Throwable;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Filesystem\FileTree;
use WebCodingCat\WpAppFramework\Hook\HookRegistrar;

/**
 * Deletes compiled templates of older releases (WP-Cron, release mode only).
 *
 * Release mode compiles into `twig/{templateVersion}/`, below it into
 * `{overrideKey}/` when the active theme or child theme overrides templates of
 * the instance. Everything in `twig/` except the directory of the current
 * template version is left over from an older release or from development mode.
 * Below the current version, override directories other than the current key
 * belong to older versions of the theme or child theme. On multisite they stay:
 * the cache directory is shared by all sites, which may run other themes and
 * therefore other keys; they go with the next release.
 *
 * @internal
 */
#[NoService]
final readonly class TwigCacheCleanUp
{
    /** Name of an override directory: Instance::themeVersionKey(). */
    private const string OVERRIDE_KEY = '/^[0-9a-f]{16}$/';

    /**
     * @param string                          $directory       The Twig cache directory of the instance, `{slug}/twig`.
     * @param non-empty-string                $templateVersion Template version of the release.
     * @param Closure(): (non-empty-string|null) $cacheKey     The current override key, null without overrides.
     */
    public function __construct(
        private string $directory,
        private string $templateVersion,
        private Closure $cacheKey,
        private bool $debug,
    ) {}

    public function cleanUp(): void
    {
        try {
            $current = $this->directory . '/' . $this->templateVersion;
            foreach ($this->entries($this->directory) as $entry) {
                if ($entry->getPathname() !== $current) {
                    FileTree::remove($entry->getPathname());
                }
            }
            if (is_multisite()) {
                return;
            }

            $key = ($this->cacheKey)();
            foreach ($this->entries($current) as $entry) {
                if ($entry->getFilename() !== $key && $entry->isDir() && preg_match(self::OVERRIDE_KEY, $entry->getFilename()) === 1) {
                    FileTree::remove($entry->getPathname());
                }
            }
        } catch (Throwable $exception) {
            HookRegistrar::rethrowOrLog($exception, $this->debug, sprintf('clean-up of the Twig cache %s', $this->directory));
        }
    }

    /**
     * @return list<SplFileInfo> The entries of the directory; none if it does not exist.
     */
    private function entries(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        // As a list: removing entries while iterating would disturb the iterator.
        $entries = [];
        /** @var SplFileInfo $entry */
        foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $entry) {
            $entries[] = $entry;
        }

        return $entries;
    }
}
