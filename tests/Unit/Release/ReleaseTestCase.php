<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Release;

use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Release\Process;
use WebCodingCat\WpAppFramework\Tests\Support\Files;

/**
 * A temporary directory per test and a Process writing to temporary files.
 */
abstract class ReleaseTestCase extends TestCase
{
    protected string $root;

    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wpaf-release-' . bin2hex(random_bytes(4));
        mkdir($this->root);
        $this->stdout = $this->open();
        $this->stderr = $this->open();
    }

    protected function tearDown(): void
    {
        fclose($this->stdout);
        fclose($this->stderr);
        Files::remove($this->root);
    }

    /**
     * @return resource
     */
    private function open()
    {
        $stream = tmpfile();
        self::assertIsResource($stream);

        return $stream;
    }

    protected function process(): Process
    {
        return new Process($this->stdout, $this->stderr);
    }

    protected function printed(): string
    {
        rewind($this->stdout);

        return (string) stream_get_contents($this->stdout);
    }

    protected function write(string $path, string $contents): string
    {
        $file = $this->root . '/' . $path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, $contents);

        return $file;
    }

    /**
     * Initializes a git repository in a directory below the root.
     */
    protected function git(string $directory, string ...$arguments): string
    {
        return $this->process()->capture(['git', ...array_values($arguments)], $this->root . '/' . $directory);
    }
}
