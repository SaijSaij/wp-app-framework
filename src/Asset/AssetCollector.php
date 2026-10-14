<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Asset;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use SplFileInfo;

/**
 * Lists the static files of a consumer's `assets/` directory during the build:
 * every file except hidden ones and the sources of the script build (.js, .jsx,
 * .ts, .tsx, .css, .scss). Each gets a short hash of its content, which
 * versions its URL, and images their size in pixels, so the framework needs no
 * file access to print them.
 *
 * @internal
 *
 * @phpstan-type AssetEntry array{hash: non-empty-string, width: int|null, height: int|null}
 */
#[NoService]
final class AssetCollector
{
    /** Extensions of files in assets/ that the script build compiles; they are no static assets. */
    public const array SOURCE_EXTENSIONS = ['js', 'jsx', 'ts', 'tsx', 'css', 'scss'];

    /**
     * @param string|null $assetDirectory The consumer's `assets/`; null for fixtures.
     * @return array<string, AssetEntry> By name, the path below the asset directory, sorted.
     */
    public static function collect(?string $assetDirectory): array
    {
        $assets = [];
        foreach (self::files($assetDirectory) as $name => $file) {
            [$width, $height] = self::size($file);
            $hash = substr((string) hash_file('xxh128', $file), 0, 12);
            if ($hash === '') {
                throw new RuntimeException(sprintf('The asset %s cannot be read.', $file));
            }
            $assets[$name] = ['hash' => $hash, 'width' => $width, 'height' => $height];
        }

        return $assets;
    }

    /**
     * @return array<string, string> Absolute paths of the static assets by name, sorted by name.
     */
    public static function files(?string $assetDirectory): array
    {
        if ($assetDirectory === null || !is_dir($assetDirectory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($assetDirectory, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $name = str_replace('\\', '/', substr($file->getPathname(), strlen($assetDirectory) + 1));
            if ($file->isFile() && self::isStatic($name)) {
                $files[$name] = $file->getPathname();
            }
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * Whether a file below the asset directory is a static asset.
     */
    public static function isStatic(string $name): bool
    {
        return preg_match('#(^|/)\.#', $name) !== 1
            && !in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::SOURCE_EXTENSIONS, true);
    }

    /**
     * Width and height of an image in pixels: of raster images from their header, of SVG images from the
     * `width` and `height` of the root element in pixels or without unit, otherwise from its `viewBox`.
     *
     * @return array{int|null, int|null} Null for other files and images without a size.
     */
    public static function size(string $file): array
    {
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'svg') {
            return self::svgSize((string) file_get_contents($file));
        }
        $size = @getimagesize($file);

        return $size === false || $size[0] === 0 ? [null, null] : [$size[0], $size[1]];
    }

    /**
     * @return array{int|null, int|null}
     */
    private static function svgSize(string $svg): array
    {
        if (preg_match('#<svg\b[^>]*>#i', $svg, $match) !== 1) {
            return [null, null];
        }
        $attribute = static fn(string $name): ?string => preg_match('#\s' . $name . '\s*=\s*(["\'])(.*?)\1#i', $match[0], $value) === 1 ? trim($value[2]) : null;
        $pixels = static fn(?string $value): ?int => $value !== null && preg_match('#^(\d+(?:\.\d+)?)(px)?$#', $value, $number) === 1 ? (int) round((float) $number[1]) : null;

        $width = $pixels($attribute('width'));
        $height = $pixels($attribute('height'));
        if ($width !== null && $height !== null) {
            return [$width, $height];
        }
        $viewBox = preg_split('#[\s,]+#', (string) $attribute('viewBox'));
        if (is_array($viewBox) && count($viewBox) === 4 && is_numeric($viewBox[2]) && is_numeric($viewBox[3])) {
            return [(int) round((float) $viewBox[2]), (int) round((float) $viewBox[3])];
        }

        return [null, null];
    }
}
