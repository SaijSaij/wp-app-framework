<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Filesystem;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Filesystem\FileTree;
use Saij\WpAppFramework\Tests\Support\Files;

final class FileTreeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wpaf-file-tree-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/tree/nested', 0777, true);
        mkdir($this->root . '/target');
        file_put_contents($this->root . '/tree/nested/file.txt', 'x');
        file_put_contents($this->root . '/target/kept.txt', 'x');
    }

    protected function tearDown(): void
    {
        Files::remove($this->root);
    }

    public function testRemovesADirectoryTree(): void
    {
        FileTree::remove($this->root . '/tree');

        self::assertDirectoryDoesNotExist($this->root . '/tree');
    }

    public function testRemovesLinksButNotTheirTargets(): void
    {
        symlink($this->root . '/target', $this->root . '/tree/link');
        symlink($this->root . '/target', $this->root . '/link');

        FileTree::remove($this->root . '/tree');
        FileTree::remove($this->root . '/link');

        self::assertDirectoryDoesNotExist($this->root . '/tree');
        self::assertFalse(is_link($this->root . '/link'));
        self::assertFileExists($this->root . '/target/kept.txt');
    }

    public function testRemovesAFile(): void
    {
        FileTree::remove($this->root . '/target/kept.txt');

        self::assertFileDoesNotExist($this->root . '/target/kept.txt');
    }

    public function testAMissingPathIsNothingToRemove(): void
    {
        FileTree::remove($this->root . '/missing');

        self::assertDirectoryExists($this->root . '/tree');
    }
}
