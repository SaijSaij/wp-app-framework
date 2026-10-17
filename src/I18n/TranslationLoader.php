<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\I18n;

use RuntimeException;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Filesystem\AtomicFile;

/**
 * Loads the translation of an instance for the current locale (`determine_locale()`), if the consumer has one:
 * `languages/{domain}-{locale}.po` in its directory, the locales listed in the manifest.
 *
 * - A release has the PO files compiled into `generated/languages/{domain}-{locale}.l10n.php` (release build); it is
 *   loaded without any check of the file system.
 * - In development mode the PO file is compiled into the cache directory of the instance whenever it is newer than
 *   the compiled file, so a changed translation shows on the next request.
 *
 * WordPress' own translations of the domain (`wp-content/languages/plugins|themes/`, loaded just in time) are added to
 * these; an entry of the consumer wins, because it is loaded first.
 *
 * @internal
 */
#[NoService]
final readonly class TranslationLoader
{
    /** The directory of the PO files in a theme or plugin. */
    public const string DIRECTORY = 'languages';

    /** The directory of the compiled translations below `generated/` of a release and the cache directory. */
    public const string COMPILED_DIRECTORY = 'languages';

    /**
     * @param non-empty-string|null $domain     Null without a text domain (no translations).
     * @param list<string>          $locales    Locales with a PO file, from the manifest.
     * @param string                $directory  Directory of the theme or plugin.
     * @param string|null           $cache      Cache directory of the instance in development mode; null in a release.
     */
    public function __construct(
        private ?string $domain,
        private array $locales,
        private string $directory,
        private ?string $cache,
    ) {}

    public function load(): void
    {
        $locale = determine_locale();
        if ($this->domain === null || !in_array($locale, $this->locales, true)) {
            return;
        }
        $name = $this->domain . '-' . $locale;
        if ($this->cache === null) {
            load_textdomain($this->domain, $this->directory . '/generated/' . self::COMPILED_DIRECTORY . '/' . $name . '.l10n.php', $locale);

            return;
        }
        $source = $this->directory . '/' . self::DIRECTORY . '/' . $name . '.po';
        $compiled = $this->cache . '/' . self::COMPILED_DIRECTORY . '/' . $name . '.l10n.php';
        if (!is_file($source)) {
            // The manifest is rebuilt on the next request: the language directory is part of its listing.
            return;
        }
        if (!is_file($compiled) || filemtime($source) >= filemtime($compiled)) {
            $content = file_get_contents($source);
            if ($content === false) {
                throw new RuntimeException(sprintf('The translation %s cannot be read.', $source));
            }
            AtomicFile::write($compiled, PoFile::parse($content, $source)->toPhp(), createDirectory: true);
        }
        load_textdomain($this->domain, $compiled, $locale);
    }
}
