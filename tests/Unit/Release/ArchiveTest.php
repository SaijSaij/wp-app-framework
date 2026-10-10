<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Release;

use Saij\WpAppFramework\Release\Archive;
use Saij\WpAppFramework\Release\ReleaseException;
use ZipArchive;

final class ArchiveTest extends ReleaseTestCase
{
    public function testFilesLieBelowTheRootFolder(): void
    {
        $this->write('build/style.css', 'css');
        $this->write('build/src/Block/Hero.twig', 'twig');

        self::assertSame(2, Archive::create($this->root . '/build', 'my-theme', $this->root . '/dist/my-theme-1.0.0.zip'));

        $zip = new ZipArchive();
        self::assertTrue($zip->open($this->root . '/dist/my-theme-1.0.0.zip'));
        $names = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $names[] = $zip->getNameIndex($index);
        }
        self::assertSame(['my-theme/', 'my-theme/src/', 'my-theme/src/Block/', 'my-theme/src/Block/Hero.twig', 'my-theme/style.css'], $names);
        self::assertSame('twig', $zip->getFromName('my-theme/src/Block/Hero.twig'));
        $zip->close();
        self::assertSame(['my-theme-1.0.0.zip'], array_values(array_diff((array) scandir($this->root . '/dist'), ['.', '..'])));
    }

    public function testSymbolicLinksAreRejected(): void
    {
        $this->write('build/style.css', 'css');
        symlink('style.css', $this->root . '/build/link.css');

        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage('link.css is a symbolic link');

        Archive::create($this->root . '/build', 'my-theme', $this->root . '/theme.zip');
    }
}
