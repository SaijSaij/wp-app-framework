<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\View;

use InvalidArgumentException;
use LogicException;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use stdClass;
use WebCodingCat\WpAppFramework\View\ViewData;

final class ViewDataTest extends TestCase
{
    private string $log;

    private string|false $previousLog;

    protected function setUp(): void
    {
        $this->log = (string) tempnam(sys_get_temp_dir(), 'log');
        $this->previousLog = ini_set('error_log', $this->log);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousLog);
        unlink($this->log);
    }

    public function testValuesAreSetAndPrinted(): void
    {
        $data = new ViewData('my-theme', true);
        self::assertFalse($data->has('map'));
        $data->set('map', ['zoom' => 12, 'center' => [1.5, 2.5], 'label' => '</script><b>&']);
        $data->set('map', ['zoom' => 13]);
        $data->set('enabled', true);

        self::assertTrue($data->has('map'));
        self::assertSame(['zoom' => 13], $data->get('map'));
        self::assertSame(
            'window.wpAppFramework = window.wpAppFramework || {}; window.wpAppFramework["my-theme"] = window.wpAppFramework["my-theme"] || {}; window.wpAppFramework["my-theme"].data = {"map":{"zoom":13},"enabled":true};',
            $data->print('test'),
        );
    }

    public function testPrintedValuesAreEscapedForAScriptElement(): void
    {
        $data = new ViewData('my-theme', true);
        $data->set('label', '</script>&');

        $script = $data->print('test');
        self::assertStringNotContainsString('</script>', $script);
        self::assertStringContainsString(substr((string) json_encode(['label' => '</script>&'], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP), 1, -1), $script);
        self::assertStringEndsWith('.data = {};', new ViewData('my-theme', true)->print('test'), 'An empty object without values.');
    }

    public function testUnknownKeysThrow(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('my-theme has no view data "map".');

        new ViewData('my-theme', true)->get('map');
    }

    public function testInvalidKeysThrow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The view data key "1st" of my-theme is invalid: use letters, digits, "-" and "_", starting with a letter.');

        new ViewData('my-theme', true)->set('1st', 1);
    }

    public function testObjectsAndNonFiniteNumbersAreNoViewData(): void
    {
        $data = new ViewData('my-theme', true);
        foreach ([
            'map[center][0] is stdClass' => ['center' => [new stdClass()]],
            'map[zoom] is INF' => ['zoom' => INF],
        ] as $reason => $value) {
            try {
                $data->set('map', $value);
                self::fail('Expected an exception for ' . $reason);
            } catch (InvalidArgumentException $exception) {
                self::assertSame(sprintf('The view data "map" of my-theme must be null, a scalar or an array of these; %s.', $reason), $exception->getMessage());
            }
        }
    }

    public function testSettingAfterPrintingThrowsWithDebug(): void
    {
        $data = new ViewData('my-theme', true);
        $data->print('in the footer');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The view data "late" of my-theme was set after it was printed (in the footer); set view data earlier, e.g. on the action "wp" or in render().');
        $data->set('late', 1);
    }

    public function testSettingAfterPrintingIsLoggedWithoutDebug(): void
    {
        $data = new ViewData('my-theme', false);
        $data->print('in the footer');
        $data->set('late', 1);

        self::assertFalse($data->has('late'));
        self::assertStringContainsString('wp-app-framework: The view data "late" of my-theme was set after it was printed (in the footer)', (string) file_get_contents($this->log));
    }

    public function testRecordingsCollectNestedValuesAndReplaySetsThem(): void
    {
        $data = new ViewData('my-theme', true);
        $data->set('before', 0);
        $data->startRecording();
        $data->set('outer', 1);
        $data->startRecording();
        $data->set('inner', 2);
        self::assertSame(['inner' => 2], $data->stopRecording());
        self::assertSame(['outer' => 1, 'inner' => 2], $data->stopRecording(), 'Values of nested blocks belong to the outer block too.');

        $replayed = new ViewData('my-theme', true);
        $replayed->replay(['outer' => 1, 'inner' => 2]);
        self::assertSame(2, $replayed->get('inner'));
    }
}
