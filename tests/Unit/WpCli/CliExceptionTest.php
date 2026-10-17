<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\WpCli;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\WpCli\CliException;

final class CliExceptionTest extends TestCase
{
    public function testKeepsMessageAndExitCode(): void
    {
        $exception = new CliException('No events.', 255);

        self::assertSame('No events.', $exception->getMessage());
        self::assertSame(255, $exception->getExitCode());
        self::assertSame(1, new CliException('Failed.')->getExitCode());
    }

    public function testRejectsExitCodesOutsideOneTo255(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CliException "Failed." needs an exit code between 1 and 255, 0 given.');

        new CliException('Failed.', 0);
    }
}
