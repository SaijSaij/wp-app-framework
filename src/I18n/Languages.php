<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\I18n;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * The translations of a consumer in the build: the locales of its PO files `languages/{domain}-{locale}.po`.
 *
 * @internal
 */
#[NoService]
final readonly class Languages
{
    /** A locale of WordPress: language, optional country and variant (`de_DE`, `de_DE_formal`, `pt_BR`). */
    private const string FILE = '/^(?<domain>.+)-(?<locale>[a-z]{2,3}(?:_[A-Z]{2})?(?:_[a-z0-9]+)?)\.po$/';

    /**
     * @param list<string> $locales Sorted.
     * @param list<string> $errors
     */
    private function __construct(
        private array $locales,
        private array $errors,
    ) {}

    public static function of(?string $directory, ?string $domain): self
    {
        if ($directory === null || $domain === null || !is_dir($directory)) {
            return new self([], []);
        }
        $locales = [];
        $errors = [];
        foreach (scandir($directory) ?: [] as $name) {
            if (!str_ends_with($name, '.po')) {
                continue;
            }
            if (preg_match(self::FILE, $name, $match) !== 1 || $match['domain'] !== $domain) {
                $errors[] = sprintf(
                    '%s/%s: a translation is named {text domain}-{locale}.po, here %s-de_DE.po for German (the text domain is the Text Domain header).',
                    $directory,
                    $name,
                    $domain,
                );
                continue;
            }
            $locales[] = $match['locale'];
        }
        sort($locales, SORT_STRING);

        return new self($locales, $errors);
    }

    /**
     * @return list<string>
     */
    public function getLocales(): array
    {
        return $this->locales;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
