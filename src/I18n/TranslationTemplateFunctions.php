<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\I18n;

use WebCodingCat\WpAppFramework\Twig\TemplateFunction;

/**
 * Template functions that translate the texts of templates with the text domain of the theme or plugin (its
 * `Text Domain` header), like WordPress' `__()`, `_x()`, `_n()` and `_nx()` without the domain argument.
 * `wp-app-framework pot` finds their calls when it lists the texts to translate. The result is text and is escaped
 * by Twig; fill placeholders with Twig's `format` filter.
 */
final readonly class TranslationTemplateFunctions
{
    public function __construct(private Translator $translator) {}

    /**
     * The translation of a text.
     *
     * ```twig
     * <button type="button">{{ __('Load map') }}</button>
     * ```
     *
     * @param string $text The text in English, as it appears in the template.
     */
    #[TemplateFunction('__')]
    public function translate(string $text): string
    {
        return $this->translator->translate($text);
    }

    /**
     * The translation of a text in a context, for texts that are the same in English but differ in other languages.
     *
     * ```twig
     * <span>{{ _x('May', 'month') }}</span>
     * ```
     *
     * @param string $text    The text in English.
     * @param string $context What the text means, for translators.
     */
    #[TemplateFunction('_x')]
    public function translateWithContext(string $text, string $context): string
    {
        return $this->translator->translateWithContext($text, $context);
    }

    /**
     * The singular or plural form of a text for a number, translated.
     *
     * ```twig
     * <p>{{ _n('%d event', '%d events', events|length)|format(events|length) }}</p>
     * ```
     *
     * @param string $singular The singular form in English.
     * @param string $plural   The plural form in English.
     * @param int    $number   The number that decides the form.
     */
    #[TemplateFunction('_n')]
    public function translatePlural(string $singular, string $plural, int $number): string
    {
        return $this->translator->translatePlural($singular, $plural, $number);
    }

    /**
     * The singular or plural form of a text for a number in a context, translated.
     *
     * ```twig
     * <p>{{ _nx('%d seat', '%d seats', seats, 'free seats')|format(seats) }}</p>
     * ```
     *
     * @param string $singular The singular form in English.
     * @param string $plural   The plural form in English.
     * @param int    $number   The number that decides the form.
     * @param string $context  What the text means, for translators.
     */
    #[TemplateFunction('_nx')]
    public function translatePluralWithContext(string $singular, string $plural, int $number, string $context): string
    {
        return $this->translator->translatePluralWithContext($singular, $plural, $number, $context);
    }
}
