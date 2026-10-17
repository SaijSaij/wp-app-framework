<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Asset;

use FilesystemIterator;
use GdImage;
use Imagick;
use ImagickException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Smaller versions and other formats of image assets, for `srcset` and
 * `<picture>`: generated from the widths and formats the templates ask for
 * (ImageVariantCollector), with Imagick or, without it, GD. Each variant is a
 * file named after the asset, the hash of its content, its width and format,
 * e.g. images/hero.3fa2b1c4d5e6.640.webp, so its URL never needs a version.
 *
 * Development mode writes them to `wp-content/cache/{slug}/assets/`, the
 * release build to `generated/assets/` of the release; never into the
 * sources.
 *
 * @internal
 *
 * @phpstan-import-type AssetEntry from AssetCollector
 */
#[NoService]
final class ImageVariants
{
    /** Directory of the variants below the release's `generated/` and below the cache directory of the instance. */
    public const string DIRECTORY = 'assets';

    /** Formats of variants by name, with the extension of their files, their MIME type and their quality (0–100). */
    public const array FORMATS = [
        'jpeg' => ['extension' => 'jpg', 'type' => 'image/jpeg', 'quality' => 82],
        'png' => ['extension' => 'png', 'type' => 'image/png', 'quality' => 100],
        'webp' => ['extension' => 'webp', 'type' => 'image/webp', 'quality' => 80],
        'avif' => ['extension' => 'avif', 'type' => 'image/avif', 'quality' => 60],
    ];

    /** Extensions of image assets that have variants, with the format of the image. */
    public const array SOURCES = ['jpg' => 'jpeg', 'jpeg' => 'jpeg', 'png' => 'png', 'webp' => 'webp', 'avif' => 'avif'];

    /**
     * The format of an image asset; null if it cannot have variants.
     */
    public static function sourceFormat(string $name): ?string
    {
        return self::SOURCES[strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? null;
    }

    /**
     * The file of a variant below the variants directory.
     *
     * @param key-of<self::FORMATS> $format
     */
    public static function fileName(string $name, string $hash, int $width, string $format): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = $extension === '' ? $name : substr($name, 0, -strlen($extension) - 1);

        return sprintf('%s.%s.%d.%s', $base, $hash, $width, self::FORMATS[$format]['extension']);
    }

    /**
     * The files of all variants of the assets: for each width the format of the image and the requested formats.
     *
     * @param array<string, AssetEntry> $assets
     * @return array<string, array{name: string, width: int, format: key-of<self::FORMATS>}> By file below the variants directory.
     */
    public static function files(array $assets): array
    {
        $files = [];
        foreach ($assets as $name => $asset) {
            $variants = $asset['variants'];
            $source = self::sourceFormat($name);
            if ($variants === null || $source === null) {
                continue;
            }
            foreach ($variants['widths'] as $width) {
                foreach (array_unique([$source, ...$variants['formats']]) as $format) {
                    /** @var key-of<self::FORMATS> $format Checked by the build. */
                    $files[self::fileName($name, $asset['hash'], $width, $format)] = ['name' => $name, 'width' => $width, 'format' => $format];
                }
            }
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * Writes the missing variants of the assets.
     *
     * @param array<string, AssetEntry> $assets
     * @param bool                      $prune  Delete other files in the variants directory (development cache only).
     * @return list<string> The files written, below the variants directory.
     * @throws RuntimeException If neither Imagick nor GD can write a requested format.
     */
    public static function generate(array $assets, string $assetDirectory, string $outputDirectory, bool $prune = false): array
    {
        $files = self::files($assets);
        if ($prune && is_dir($outputDirectory)) {
            $existing = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($outputDirectory, FilesystemIterator::SKIP_DOTS));
            /** @var SplFileInfo $file */
            foreach ($existing as $file) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($outputDirectory) + 1));
                if ($file->isFile() && !isset($files[$relative])) {
                    unlink($file->getPathname());
                }
            }
        }

        $written = [];
        foreach ($files as $file => ['name' => $name, 'width' => $width, 'format' => $format]) {
            $target = $outputDirectory . '/' . $file;
            if (is_file($target)) {
                continue;
            }
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) {
                throw new RuntimeException(sprintf('Cannot create the directory %s for image variants.', dirname($target)));
            }
            self::resize($assetDirectory . '/' . $name, $target, $width, $format);
            $written[] = $file;
        }

        return $written;
    }

    /**
     * Writes one variant: the image scaled to the width, without metadata.
     *
     * @param key-of<self::FORMATS> $format
     */
    public static function resize(string $source, string $target, int $width, string $format): void
    {
        $quality = self::FORMATS[$format]['quality'];
        if (self::imagickSupports($format)) {
            $image = new Imagick($source);
            $image->stripImage();
            $image->resizeImage($width, 0, Imagick::FILTER_LANCZOS, 1);
            $image->setImageFormat($format);
            $image->setImageCompressionQuality($quality);
            $image->writeImage($target);
            $image->clear();

            return;
        }
        if (self::gdSupports($format)) {
            $image = self::gdLoad($source);
            $scaled = imagescale($image, $width);
            if (!$scaled instanceof GdImage) {
                throw new RuntimeException(sprintf('GD cannot scale %s.', $source));
            }
            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
            $saved = match ($format) {
                'jpeg' => imagejpeg($scaled, $target, $quality),
                'png' => imagepng($scaled, $target, 9),
                'webp' => imagewebp($scaled, $target, $quality),
                'avif' => imageavif($scaled, $target, $quality),
            };
            if (!$saved) {
                throw new RuntimeException(sprintf('GD cannot write %s.', $target));
            }

            return;
        }

        throw new RuntimeException(sprintf(
            'The image variant %s needs the format %s, which neither the PHP extension Imagick nor GD of %s can write. Install Imagick or GD with %s support for the PHP that builds the manifest (the web server in development mode, the command line for a release).',
            $target,
            strtoupper($format),
            PHP_BINARY,
            strtoupper($format),
        ));
    }

    /**
     * Whether Imagick can write the format: it may list a format it can only read (AVIF without an encoder of
     * libheif), so a pixel is encoded once per process to find out.
     */
    private static function imagickSupports(string $format): bool
    {
        /** @var array<string, bool> $supported */
        static $supported = [];
        if (!extension_loaded('imagick')) {
            return false;
        }
        if (!isset($supported[$format])) {
            try {
                $probe = new Imagick();
                $probe->newImage(1, 1, 'white');
                $probe->setImageFormat($format);
                $supported[$format] = $probe->getImageBlob() !== '';
                $probe->clear();
            } catch (ImagickException) {
                $supported[$format] = false;
            }
        }

        return $supported[$format];
    }

    private static function gdSupports(string $format): bool
    {
        return extension_loaded('gd') && function_exists('image' . $format);
    }

    private static function gdLoad(string $source): GdImage
    {
        $format = self::sourceFormat($source);
        $image = $format !== null && function_exists('imagecreatefrom' . $format) ? ('imagecreatefrom' . $format)($source) : false;
        if (!$image instanceof GdImage) {
            throw new RuntimeException(sprintf('GD cannot read %s.', $source));
        }

        return $image;
    }
}
