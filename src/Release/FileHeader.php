<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Release;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Reads the headers of a theme's style.css or a plugin's main file the way
 * WordPress does (get_file_data(): first 8 KiB, `Name: value` on its own line).
 */
#[NoService]
final class FileHeader
{
    /**
     * @return non-empty-string
     */
    public static function version(string $file): string
    {
        $version = self::value($file, 'Version')
            ?? throw new ReleaseException(sprintf('%s has no "Version:" header.', $file));
        if (preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.+-]+)?$/', $version) !== 1) {
            throw new ReleaseException(sprintf('The version "%s" in %s is not a semantic version (e.g. 1.2.3).', $version, $file));
        }

        return $version;
    }

    /**
     * The value of a header, or null if the file does not declare it or declares it empty.
     *
     * @return non-empty-string|null
     */
    public static function value(string $file, string $name): ?string
    {
        if (!is_file($file)) {
            throw new ReleaseException(sprintf('%s not found; a theme declares its headers in style.css, a plugin in its main file {slug}.php.', $file));
        }

        $contents = (string) file_get_contents($file, false, null, 0, 8192);
        $contents = str_replace("\r", "\n", $contents);
        if (preg_match('/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote($name, '/') . ':(.*)$/mi', $contents, $match) !== 1) {
            return null;
        }

        // Like _cleanup_header_comment(): a closing comment on the same line is not part of the value.
        $value = trim((string) preg_replace('/\s*(?:\*\/|\?>).*/', '', $match[1]));

        return $value === '' ? null : $value;
    }
}
