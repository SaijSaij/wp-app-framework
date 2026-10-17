<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\I18n;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * The translations of the framework's own texts – the controls of the editor, the settings page and the meta boxes,
 * notices – with the text domain `wp-app-framework`. They ship with the framework, compiled
 * (`languages/wp-app-framework-{locale}.l10n.php`, from the PO files next to them), because a release must not
 * write. The first instance of a request loads them for PHP; the scripts of the editor, the settings page and the
 * meta boxes get them as locale data of `wp.i18n`.
 *
 * @internal
 */
#[NoService]
final class FrameworkTranslations
{
    public const string DOMAIN = 'wp-app-framework';

    /** Locales of WordPress and the translation that serves them. */
    private const array LOCALES = [
        'de_DE' => 'de_DE',
        'de_DE_formal' => 'de_DE',
        'de_AT' => 'de_DE',
        'de_CH' => 'de_DE',
        'de_CH_informal' => 'de_DE',
    ];

    /** @var array<string, string|null> Scripts with the locale data by locale, built once per request. */
    private static array $scripts = [];

    /**
     * The compiled translation for a locale, or null if the framework has none.
     */
    public static function file(string $locale): ?string
    {
        $translation = self::LOCALES[$locale] ?? null;

        return $translation === null ? null : dirname(__DIR__, 2) . '/languages/' . self::DOMAIN . '-' . $translation . '.l10n.php';
    }

    /**
     * Loads the translation for the locale of the request, unless another instance did.
     */
    public static function load(): void
    {
        $locale = determine_locale();
        $file = self::file($locale);
        if ($file !== null && !is_textdomain_loaded(self::DOMAIN)) {
            load_textdomain(self::DOMAIN, $file, $locale);
        }
    }

    /**
     * The script that hands the translation to `wp.i18n` (as locale data of the domain), for an inline script
     * before a script of the framework; null without a translation for the locale.
     */
    public static function localeDataScript(): ?string
    {
        $locale = determine_locale();
        if (array_key_exists($locale, self::$scripts)) {
            return self::$scripts[$locale];
        }
        $file = self::file($locale);
        $data = $file === null ? null : include $file;
        if (!is_array($data) || !is_array($data['messages'] ?? null)) {
            return self::$scripts[$locale] = null;
        }
        $localeData = ['' => ['domain' => self::DOMAIN, 'lang' => $locale, 'plural_forms' => is_string($data['plural-forms'] ?? null) ? $data['plural-forms'] : 'nplurals=2; plural=(n != 1);']];
        foreach ($data['messages'] as $key => $translation) {
            // Jed, the format of wp.i18n, keys a plural by its singular.
            $localeData[explode("\0", (string) $key)[0]] = explode("\0", is_string($translation) ? $translation : '');
        }

        return self::$scripts[$locale] = sprintf(
            'wp.i18n.setLocaleData(%s, %s);',
            wp_json_encode($localeData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP),
            wp_json_encode(self::DOMAIN),
        );
    }

    /**
     * Adds the locale data before a script of the framework that uses `wp.i18n`.
     */
    public static function addTo(string $handle): void
    {
        $script = self::localeDataScript();
        if ($script !== null) {
            wp_add_inline_script($handle, $script, 'before');
        }
    }
}
