<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Table;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WebCodingCat\WpAppFramework\Table\RowCodec;
use WebCodingCat\WpAppFramework\Tests\Fixtures\TableValid\Level;
use WebCodingCat\WpAppFramework\Tests\Fixtures\TableValid\LogEntry;
use WebCodingCat\WpAppFramework\Tests\Fixtures\TableValid\Priority;
use WebCodingCat\WpAppFramework\Tests\Support\Tables;

/**
 * Values of columns between wpdb (strings) and PHP.
 */
final class RowCodecTest extends TestCase
{
    public function testStoredStringsBecomeRawValues(): void
    {
        $columns = Tables::data()['classes'][LogEntry::class]['columns'];
        $decode = static fn(string $property, ?string $stored): mixed => RowCodec::decode($columns[$property], $stored, 'wp_dev_test_log', 7);

        self::assertSame(
            [5, -3, 0.1, 1.0E-300, true, false, 'Text', 'error', 9, '2026-10-14 08:30:05', null],
            [
                $decode('id', '5'),
                $decode('attempts', '-3'),
                $decode('ratio', '0.1'),
                $decode('ratio', '1e-300'),
                $decode('flagged', '1'),
                $decode('flagged', '0'),
                $decode('message', 'Text'),
                $decode('level', 'error'),
                $decode('priority', '9'),
                $decode('created', '2026-10-14 08:30:05'),
                $decode('postId', null),
            ],
        );

        foreach ([['attempts', '1.5'], ['ratio', 'x'], ['flagged', '2'], ['priority', 'high']] as [$property, $stored]) {
            try {
                $decode($property, $stored);
                self::fail(sprintf('%s must reject %s.', $property, $stored));
            } catch (RuntimeException $exception) {
                self::assertStringStartsWith(sprintf('The column %s of the row 7 in the table wp_dev_test_log holds \'%s\', which is no valid', $columns[$property]['column'], $stored), $exception->getMessage());
            }
        }
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("The column created of the row 7 in the table wp_dev_test_log holds '0000-00-00 00:00:00', which is no valid datetime (the zero date stands for a time that was never set, e.g. in a column added to rows that existed already).");
        $decode('created', '0000-00-00 00:00:00');
    }

    public function testValuesAreEncodedWithoutLoss(): void
    {
        $columns = Tables::data()['classes'][LogEntry::class]['columns'];
        $encode = static fn(string $property, mixed $value): array => RowCodec::encode($columns[$property], $value, LogEntry::class . '::$' . $property);

        self::assertSame([
            [null, '%s'],
            [3, '%d'],
            ['0.12345678901234568', '%s'],
            ['1.0E-300', '%s'],
            [1, '%d'],
            [0, '%d'],
            ['error', '%s'],
            [9, '%d'],
            ['2026-10-14 06:30:05', '%s'],
        ], [
            $encode('postId', null),
            $encode('attempts', 3),
            $encode('ratio', 0.1234567890123456789),
            $encode('ratio', 1.0E-300),
            $encode('flagged', true),
            $encode('flagged', false),
            $encode('level', Level::Error),
            $encode('priority', Priority::High),
            $encode('created', new DateTimeImmutable('2026-10-14 08:30:05', new DateTimeZone('Europe/Berlin'))),
        ]);

        try {
            $encode('ratio', INF);
            self::fail('INF must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertSame(LogEntry::class . '::$ratio is INF; the database stores finite floats only.', $exception->getMessage());
        }
        $this->expectException(LogicException::class);
        $encode('message', [1]);
    }
}
