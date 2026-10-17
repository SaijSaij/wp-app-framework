<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\I18n;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\I18n\PoFile;

/**
 * PO files compiled into the messages of a translation file of WordPress.
 */
final class PoFileTest extends TestCase
{
    private const string PO = <<<'PO'
        # German translation.
        msgid ""
        msgstr ""
        "Project-Id-Version: Datebook 0.1.0\n"
        "Language: de_DE\n"
        "Plural-Forms: nplurals=2; plural=(n != 1);\n"

        #: src/Block/Map/Map.twig:3
        msgid "Load map"
        msgstr "Karte laden"

        msgctxt "month"
        msgid "May"
        msgstr "Mai"

        msgid "%d event"
        msgid_plural "%d events"
        msgstr[0] "%d Termin"
        msgstr[1] "%d Termine"

        msgid ""
        "A text over "
        "two lines\n"
        "with \"quotes\""
        msgstr "Ein Text über zwei Zeilen\nmit \"Anführungszeichen\" und \\"

        #, fuzzy
        msgid "Plan route"
        msgstr "Route planen?"

        msgid "Untranslated"
        msgstr ""
        PO;

    public function testReadsHeadersContextsPluralsAndMultilineStrings(): void
    {
        $file = PoFile::parse(self::PO, 'de_DE.po');

        self::assertSame(['project-id-version' => 'Datebook 0.1.0', 'language' => 'de_DE', 'plural-forms' => 'nplurals=2; plural=(n != 1);'], $file->getHeaders());
        self::assertSame([
            'Load map' => 'Karte laden',
            "month\4May" => 'Mai',
            "%d event\0%d events" => "%d Termin\0%d Termine",
            "A text over two lines\nwith \"quotes\"" => "Ein Text über zwei Zeilen\nmit \"Anführungszeichen\" und \\",
        ], $file->getMessages(), 'Fuzzy and untranslated entries are left out.');
    }

    public function testWritesATranslationFileOfWordPress(): void
    {
        $php = PoFile::parse(self::PO, 'de_DE.po')->toPhp();
        $data = eval('?>' . $php);

        self::assertIsArray($data);
        self::assertSame('nplurals=2; plural=(n != 1);', $data['plural-forms'] ?? null);
        self::assertSame('de_DE', $data['language'] ?? null);
        $messages = $data['messages'] ?? null;
        self::assertIsArray($messages);
        self::assertSame('Karte laden', $messages['Load map'] ?? null);
    }

    public function testNamesFileAndLineOfALineItDoesNotUnderstand(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('de_DE.po:2: not a line of a PO file: msgstr Karte laden');
        PoFile::parse("msgid \"Load map\"\nmsgstr Karte laden\n", 'de_DE.po');
    }
}
