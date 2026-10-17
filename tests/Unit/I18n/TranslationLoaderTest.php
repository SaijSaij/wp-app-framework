<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\I18n;

use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Filesystem\FileTree;
use WebCodingCat\WpAppFramework\I18n\TranslationLoader;

/**
 * Loading the translation of an instance: compiled from the PO file in development mode, the compiled file of a
 * release, nothing for other locales.
 */
final class TranslationLoaderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/wpaf-l10n-' . bin2hex(random_bytes(4));
        mkdir($this->directory . '/languages', 0777, true);
        file_put_contents($this->directory . '/languages/probe-de_DE.po', "msgid \"Load map\"\nmsgstr \"Karte laden\"\n");
        $GLOBALS['wp_app_framework_test_locale'] = 'de_DE';
    }

    protected function tearDown(): void
    {
        FileTree::remove($this->directory);
        unset($GLOBALS['wp_app_framework_test_locale'], $GLOBALS['wp_app_framework_test_textdomains'], $GLOBALS['wp_app_framework_test_translations']);
    }

    /**
     * @return mixed The files loaded for the domain probe.
     */
    private static function loaded(): mixed
    {
        $loaded = $GLOBALS['wp_app_framework_test_textdomains'] ?? null;

        return is_array($loaded) ? $loaded['probe'] ?? null : null;
    }

    public function testCompilesThePoFileIntoTheCacheInDevelopmentMode(): void
    {
        $cache = $this->directory . '/cache';
        new TranslationLoader('probe', ['de_DE'], $this->directory, $cache)->load();

        self::assertSame([$cache . '/languages/probe-de_DE.l10n.php'], self::loaded());
        self::assertSame('Karte laden', translate('Load map', 'probe'));

        // A changed PO file is compiled again.
        file_put_contents($this->directory . '/languages/probe-de_DE.po', "msgid \"Load map\"\nmsgstr \"Karte anzeigen\"\n");
        touch($this->directory . '/languages/probe-de_DE.po', time() + 2);
        unset($GLOBALS['wp_app_framework_test_translations']);
        new TranslationLoader('probe', ['de_DE'], $this->directory, $cache)->load();
        self::assertSame('Karte anzeigen', translate('Load map', 'probe'));
    }

    public function testLoadsTheCompiledFileOfARelease(): void
    {
        new TranslationLoader('probe', ['de_DE'], $this->directory, null)->load();

        self::assertSame([$this->directory . '/generated/languages/probe-de_DE.l10n.php'], self::loaded());
    }

    public function testLoadsNothingForAnotherLocaleOrWithoutTextDomain(): void
    {
        new TranslationLoader('probe', ['fr_FR'], $this->directory, $this->directory . '/cache')->load();
        new TranslationLoader(null, ['de_DE'], $this->directory, $this->directory . '/cache')->load();

        self::assertArrayNotHasKey('wp_app_framework_test_textdomains', $GLOBALS);
    }
}
