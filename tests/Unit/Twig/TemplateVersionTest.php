<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Saij\WpAppFramework\Tests\Support\Files;
use Saij\WpAppFramework\Twig\TemplateVersion;

final class TemplateVersionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wpaf-version-' . bin2hex(random_bytes(4));
        $this->write('a/theme/Block/Hero.twig', 'hero');
        $this->write('a/theme/Block/Hero.php', '<?php');
        $this->write('a/framework/Part.twig', 'part');
    }

    protected function tearDown(): void
    {
        Files::remove($this->root);
    }

    private function write(string $path, string $contents): void
    {
        $file = $this->root . '/' . $path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, $contents);
    }

    private function version(string $tree = 'a'): string
    {
        return TemplateVersion::compute([$this->root . '/' . $tree . '/theme', $this->root . '/' . $tree . '/framework']);
    }

    public function testVersionIsShortHexAndStable(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $this->version());
        self::assertSame($this->version(), $this->version());
    }

    public function testVersionDoesNotDependOnTheLocation(): void
    {
        $this->write('b/theme/Block/Hero.twig', 'hero');
        $this->write('b/framework/Part.twig', 'part');

        self::assertSame($this->version('a'), $this->version('b'));
    }

    public function testOnlyTemplatesCount(): void
    {
        $before = $this->version();
        $this->write('a/theme/Block/Hero.php', '<?php // changed');

        self::assertSame($before, $this->version());
    }

    public function testChangedAddedRenamedOrMovedTemplatesChangeTheVersion(): void
    {
        $versions = [$this->version()];

        $this->write('a/theme/Block/Hero.twig', 'hero!');
        $versions[] = $this->version();

        $this->write('a/theme/Block/Extra.twig', '');
        $versions[] = $this->version();

        rename($this->root . '/a/theme/Block/Extra.twig', $this->root . '/a/theme/Block/Other.twig');
        $versions[] = $this->version();

        // Same name and contents on the other level.
        rename($this->root . '/a/theme/Block/Other.twig', $this->root . '/a/framework/Other.twig');
        mkdir($this->root . '/a/framework/Block');
        rename($this->root . '/a/framework/Other.twig', $this->root . '/a/framework/Block/Other.twig');
        $versions[] = $this->version();

        self::assertCount(5, array_unique($versions));
    }

    public function testMissingDirectoryIsAnError(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('/missing does not exist');

        TemplateVersion::compute([$this->root . '/missing']);
    }
}
