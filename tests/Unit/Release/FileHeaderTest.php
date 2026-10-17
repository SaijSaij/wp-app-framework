<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Release;

use WebCodingCat\WpAppFramework\Release\FileHeader;
use WebCodingCat\WpAppFramework\Release\ReleaseException;

final class FileHeaderTest extends ReleaseTestCase
{
    public function testReadsTheVersionLikeWordPress(): void
    {
        $file = $this->write('style.css', "/*\nTheme Name: Test\n * Version: 1.2.3-beta.1 */\nRequires PHP: 8.5\n*/");

        self::assertSame('1.2.3-beta.1', FileHeader::version($file));
    }

    public function testMissingVersionIsAnError(): void
    {
        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage('has no "Version:" header');

        FileHeader::version($this->write('style.css', "/*\nTheme Name: Test\n*/"));
    }

    public function testVersionMustBeSemantic(): void
    {
        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage('The version "1.2" in');

        FileHeader::version($this->write('style.css', "/*\nVersion: 1.2\n*/"));
    }
}
