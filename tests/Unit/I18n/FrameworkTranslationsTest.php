<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\I18n;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\I18n\FrameworkTranslations;
use Saij\WpAppFramework\I18n\PoFile;

/**
 * The translations of the framework's own texts: complete, compiled and handed to the scripts.
 */
final class FrameworkTranslationsTest extends TestCase
{
    private const string LANGUAGES = __DIR__ . '/../../../languages';

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_app_framework_test_locale']);
    }

    public function testTheGermanTranslationIsCompleteAndCompiled(): void
    {
        $template = PoFile::parse((string) file_get_contents(self::LANGUAGES . '/wp-app-framework.pot'), 'wp-app-framework.pot');
        $po = (string) file_get_contents(self::LANGUAGES . '/wp-app-framework-de_DE.po');
        $german = PoFile::parse($po, 'wp-app-framework-de_DE.po');

        preg_match_all('/^msgid "(.+)"$/m', (string) file_get_contents(self::LANGUAGES . '/wp-app-framework.pot'), $texts);
        self::assertSame([], $template->getMessages(), 'The template has no translations.');
        foreach ($texts[1] as $text) {
            self::assertArrayHasKey(stripcslashes($text), $german->getMessages(), sprintf('"%s" is not translated; run php tools/translations after translating it.', $text));
        }
        self::assertSame(
            $german->toPhp(),
            file_get_contents(self::LANGUAGES . '/wp-app-framework-de_DE.l10n.php'),
            'The compiled translation is not up to date; run php tools/translations.',
        );
    }

    public function testHandsTheTranslationToTheScriptsAsLocaleData(): void
    {
        $GLOBALS['wp_app_framework_test_locale'] = 'de_DE_formal';
        $script = (string) FrameworkTranslations::localeDataScript();

        self::assertStringStartsWith('wp.i18n.setLocaleData({"":{"domain":"wp-app-framework","lang":"de_DE_formal","plural_forms":"nplurals=2; plural=(n != 1);"}', $script);
        self::assertStringContainsString('"Add item":["Eintrag hinzufügen"]', $script);
        self::assertStringEndsWith(', "wp-app-framework");', $script);

        $GLOBALS['wp_app_framework_test_locale'] = 'en_US';
        self::assertNull(FrameworkTranslations::localeDataScript());
        self::assertNull(FrameworkTranslations::file('fr_FR'));
    }
}
