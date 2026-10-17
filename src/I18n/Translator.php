<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\I18n;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Translates the texts of one instance with its text domain (the `Text Domain` header of the theme or plugin): the
 * texts a consumer writes in attributes – titles of blocks, labels of post types and values – stay English in the
 * code and the manifest and are translated where they reach WordPress or the editor, and the Twig functions `__()`,
 * `_x()`, `_n()` and `_nx()` translate the texts of templates.
 *
 * Without a text domain (builds of fixtures), texts stay as they are.
 *
 * @internal
 */
#[NoService]
final readonly class Translator
{
    /** Keys of control descriptions (manifest, editor data) whose string values are labels shown to editors. */
    private const array LABEL_KEYS = ['label', 'itemLabel', 'title', 'description'];

    /**
     * @param non-empty-string|null $domain
     */
    public function __construct(private ?string $domain) {}

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function translate(string $text): string
    {
        return $this->domain === null || $text === '' ? $text : translate($text, $this->domain);
    }

    public function translateWithContext(string $text, string $context): string
    {
        return $this->domain === null || $text === '' ? $text : translate_with_gettext_context($text, $context, $this->domain);
    }

    public function translatePlural(string $singular, string $plural, int $number): string
    {
        return $this->domain === null ? ($number === 1 ? $singular : $plural) : _n($singular, $plural, $number, $this->domain);
    }

    public function translatePluralWithContext(string $singular, string $plural, int $number, string $context): string
    {
        return $this->domain === null ? ($number === 1 ? $singular : $plural) : _nx($singular, $plural, $number, $context, $this->domain);
    }

    /**
     * Each string of a list, e.g. the keywords of a block.
     *
     * @param list<string> $texts
     * @return list<string>
     */
    public function translateAll(array $texts): array
    {
        return array_map($this->translate(...), $texts);
    }

    /**
     * The labels in a description of controls or fields (nested values, list items, enum options, meta forms):
     * every string under one of the keys `label`, `itemLabel`, `title` and `description`, at any depth.
     *
     * @template T of array
     * @param T $description
     * @return T
     */
    public function translateLabels(array $description): array
    {
        if ($this->domain === null) {
            return $description;
        }
        foreach ($description as $key => $value) {
            if (is_array($value)) {
                $description[$key] = $this->translateLabels($value);
            } elseif (is_string($value) && in_array($key, self::LABEL_KEYS, true)) {
                $description[$key] = $this->translate($value);
            }
        }

        /** @var T $description */
        return $description;
    }
}
