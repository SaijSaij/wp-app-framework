<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\I18n;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Filesystem\FileTree;
use Saij\WpAppFramework\I18n\AttributeTexts;
use Saij\WpAppFramework\I18n\PhpTexts;
use Saij\WpAppFramework\I18n\PoFile;
use Saij\WpAppFramework\I18n\PotFile;
use Saij\WpAppFramework\I18n\TemplateTexts;
use Saij\WpAppFramework\Tests\Unit\Block\BlockCollectorTest;

/**
 * The template of the translations: texts of attributes, templates and PHP files, written as a POT file.
 */
final class PotTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/wpaf-pot-' . bin2hex(random_bytes(4));
        mkdir($this->directory . '/src/Block', 0777, true);
    }

    protected function tearDown(): void
    {
        FileTree::remove($this->directory);
    }

    /**
     * @return list<array{0: string|null, 1: string, 2: string|null, 3: list<string>, 4: list<string>}>
     */
    private static function entries(PotFile $pot): array
    {
        return array_map(
            static fn(array $entry): array => [$entry['context'], $entry['text'], $entry['plural'], $entry['references'], $entry['comments']],
            $pot->getEntries(),
        );
    }

    public function testWritesAPotFileThatReadsBack(): void
    {
        $pot = new PotFile();
        $pot->add('Load map', reference: 'src/Map.twig:3');
        $pot->add('Load map', reference: 'src/Map.twig:9');
        $pot->add('May', 'month', reference: 'src/Date.twig:1');
        $pot->add('%d event', plural: '%d events', comment: 'translators: %d: number of events');
        $pot->add("Two\nlines with \"quotes\"");
        $pot->add('');

        $content = $pot->toString(['Project-Id-Version' => 'Probe 1.0', 'X-Domain' => 'probe']);

        self::assertStringContainsString("#: src/Map.twig:3\n#: src/Map.twig:9\nmsgid \"Load map\"\nmsgstr \"\"\n", $content);
        self::assertStringContainsString("msgctxt \"month\"\nmsgid \"May\"\n", $content);
        self::assertStringContainsString("#. translators: %d: number of events\nmsgid \"%d event\"\nmsgid_plural \"%d events\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n", $content);
        self::assertSame(4, $pot->count());
        // A translator's copy of it is a valid PO file; untranslated, it has no messages yet.
        $po = PoFile::parse($content, 'probe.pot');
        self::assertSame(['mime-version' => '1.0', 'content-type' => 'text/plain; charset=UTF-8', 'content-transfer-encoding' => '8bit', 'project-id-version' => 'Probe 1.0', 'x-domain' => 'probe'], $po->getHeaders());
        self::assertSame([], $po->getMessages());
    }

    public function testFindsTheTranslationFunctionsOfTemplates(): void
    {
        file_put_contents($this->directory . '/src/Block/Map.twig', <<<'TWIG'
            <button>{{ __('Load map') }}</button>
            <span>{{ _x('May', 'month') }}</span>
            {% if count %}<p>{{ _n('%d event', '%d events', count)|format(count) }}</p>{% endif %}
            <p>{{ _nx('%d seat', '%d seats', seats, 'free seats') }}</p>
            <p>{{ __(text) }}{{ other('Not a text') }}</p>
            TWIG);
        $pot = new PotFile();
        $texts = new TemplateTexts();
        $texts->collect($this->directory . '/src', 'src', $pot);

        self::assertSame([
            [null, 'Load map', null, ['src/Block/Map.twig:1'], []],
            ['month', 'May', null, ['src/Block/Map.twig:2'], []],
            [null, '%d event', '%d events', ['src/Block/Map.twig:3'], []],
            ['free seats', '%d seat', '%d seats', ['src/Block/Map.twig:4'], []],
        ], self::entries($pot));
        self::assertSame(['__() in src/Block/Map.twig, line 5: the text is no literal string and cannot be translated ahead.'], $texts->notices());
    }

    public function testFindsTheTranslationCallsOfPhpFilesWithTheTextDomain(): void
    {
        $file = $this->directory . '/src/Labels.php';
        file_put_contents($file, <<<'PHP'
            <?php
            $a = __('Load map', 'probe');
            $b = esc_html__("Plan \"route\"", 'probe');
            $c = _x('May', 'month', 'probe');
            /* translators: %d: number of events */
            $d = sprintf(_n('%d event', '%d events', $count, 'probe'), $count);
            $e = __('Other domain', 'other');
            $f = __($variable, 'probe');
            $g = $object->__('A method', 'probe');
            $h = \__('Qualified', 'probe');
            $i = _nx_noop('%d seat', '%d seats', 'free seats', 'probe');
            PHP);
        $pot = new PotFile();
        PhpTexts::collect($file, 'src/Labels.php', 'probe', $pot);

        self::assertSame([
            [null, 'Load map', null, ['src/Labels.php:2'], []],
            [null, 'Plan "route"', null, ['src/Labels.php:3'], []],
            ['month', 'May', null, ['src/Labels.php:4'], []],
            [null, '%d event', '%d events', ['src/Labels.php:6'], ['translators: %d: number of events']],
            [null, 'Qualified', null, ['src/Labels.php:10'], []],
            ['free seats', '%d seat', '%d seats', ['src/Labels.php:11'], []],
        ], self::entries($pot));
    }

    public function testFindsTheTextsOfAttributes(): void
    {
        $pot = new PotFile();
        new AttributeTexts((string) realpath(__DIR__ . '/../../Fixtures'))->collect(BlockCollectorTest::buildValid(), $pot);

        $texts = array_column($pot->getEntries(), 'references', 'text');
        self::assertSame(['BlockValid/Hero.php'], $texts['Hero'] ?? null);
        self::assertArrayHasKey('A hero.', $texts);
        self::assertArrayHasKey('banner', $texts);
    }
}
