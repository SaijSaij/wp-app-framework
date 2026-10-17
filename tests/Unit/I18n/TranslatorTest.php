<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\I18n;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\I18n\Translator;

/**
 * Texts of an instance translated with its text domain: single texts, plurals and the labels of control descriptions.
 */
final class TranslatorTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wp_app_framework_test_translations'] = ['probe' => [
            'Start' => 'Beginn',
            'Badge' => 'Abzeichen',
            'Primary' => 'Primär',
            "month\4May" => 'Mai',
            "%d event\0%d events" => "%d Termin\0%d Termine",
        ]];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_app_framework_test_translations']);
    }

    public function testTranslatesTextsWithItsDomain(): void
    {
        $translator = new Translator('probe');

        self::assertSame('Beginn', $translator->translate('Start'));
        self::assertSame('Mai', $translator->translateWithContext('May', 'month'));
        self::assertSame('%d Termine', $translator->translatePlural('%d event', '%d events', 3));
        self::assertSame(['Beginn', 'End'], $translator->translateAll(['Start', 'End']));
        self::assertSame('Start', new Translator('other')->translate('Start'));
    }

    public function testTranslatesTheLabelsOfControlsAtAnyDepth(): void
    {
        $controls = [
            'start' => ['type' => 'date', 'label' => 'Start', 'nullable' => false],
            'badges' => [
                'type' => 'list',
                'label' => 'Badges',
                'itemLabel' => 'Badge',
                'item' => ['type' => 'object', 'fields' => ['tone' => ['type' => 'enum', 'label' => 'Tone', 'options' => [['value' => 'primary', 'label' => 'Primary']]]]],
            ],
        ];

        $expected = $controls;
        $expected['start']['label'] = 'Beginn';
        $expected['badges']['itemLabel'] = 'Abzeichen';
        // Values stay; "Badges" and "Tone" have no translation.
        $expected['badges']['item']['fields']['tone']['options'][0]['label'] = 'Primär';

        self::assertSame($expected, new Translator('probe')->translateLabels($controls));
        self::assertSame($controls, new Translator(null)->translateLabels($controls), 'Without a text domain, nothing changes.');
    }
}
