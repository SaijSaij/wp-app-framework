<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Release;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Saij\WpAppFramework\Release\ReleaseException;
use Saij\WpAppFramework\Release\TwigPatcher;
use SplFileInfo;
use Twig\Environment;

final class TwigPatcherTest extends ReleaseTestCase
{
    private string $twig;

    protected function setUp(): void
    {
        parent::setUp();
        // A copy of the installed Twig: the patterns must match the version the framework requires.
        $source = dirname((string) new \ReflectionClass(Environment::class)->getFileName());
        $this->twig = $this->root . '/twig';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $target = $this->twig . '/src/' . substr($file->getPathname(), strlen($source) + 1);
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0777, true);
            }
            copy($file->getPathname(), $target);
        }
    }

    public function testPrefixesTheNamesTheCompilerWritesIntoTemplates(): void
    {
        self::assertSame(25, TwigPatcher::patch($this->twig, 'Acme\\Theme\\Vendor\\'));

        $module = (string) file_get_contents($this->twig . '/src/Node/ModuleNode.php');
        self::assertStringContainsString('->write("use Acme\\\\Theme\\\\Vendor\\\\Twig\\Environment;\\n")', $module);
        self::assertStringNotContainsString('"use Twig\\', $module);
        self::assertStringContainsString(
            "'\\\\Acme\\\\Theme\\\\Vendor\\\\Twig\\\\Extension\\\\CoreExtension::captureOutput('",
            (string) file_get_contents($this->twig . '/src/Node/CaptureNode.php'),
        );
        self::assertStringContainsString(
            '(\\Acme\\Theme\\Vendor\\Twig\\Extension\\SandboxExtension::class)',
            (string) file_get_contents($this->twig . '/src/Node/Expression/CallExpression.php'),
        );
    }

    public function testDeviatingTwigFailsWithoutChangingAnyFile(): void
    {
        $capture = $this->twig . '/src/Node/CaptureNode.php';
        file_put_contents($capture, str_replace('captureOutput(', 'capture(', (string) file_get_contents($capture)));
        $module = (string) file_get_contents($this->twig . '/src/Node/ModuleNode.php');

        try {
            TwigPatcher::patch($this->twig, 'Acme\\Theme\\Vendor\\');
            self::fail('Expected a ReleaseException.');
        } catch (ReleaseException $exception) {
            self::assertStringContainsString('Twig ' . Environment::VERSION . ' does not match the patterns', $exception->getMessage());
            self::assertStringContainsString('Node/CaptureNode.php contains', $exception->getMessage());
            self::assertStringContainsString('0 times, expected 1', $exception->getMessage());
        }
        self::assertSame($module, file_get_contents($this->twig . '/src/Node/ModuleNode.php'));
    }

    public function testMissingTwigIsAnError(): void
    {
        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage('is twig/twig installed?');

        TwigPatcher::patch($this->root . '/missing', 'Acme\\Theme\\Vendor\\');
    }
}
