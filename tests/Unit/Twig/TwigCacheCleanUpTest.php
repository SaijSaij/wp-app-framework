<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Saij\WpAppFramework\Tests\Support\Files;
use Saij\WpAppFramework\Twig\TwigCacheCleanUp;

/**
 * Deleting compiled templates of older releases.
 */
final class TwigCacheCleanUpTest extends TestCase
{
    private const string VERSION = '0123456789abcdef';
    private const string KEY = 'fedcba9876543210';
    private const string OLD_KEY = 'aaaaaaaaaaaaaaaa';

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wpaf-twig-clean-up-' . bin2hex(random_bytes(4));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        Files::remove($this->root);
        unset($GLOBALS['wp_app_framework_test_multisite']);
    }

    private function compiled(string $path): void
    {
        mkdir(dirname($this->root . '/' . $path), 0777, true);
        file_put_contents($this->root . '/' . $path, '<?php');
    }

    /**
     * @param non-empty-string|null $key
     */
    private function cleanUp(?string $key, bool $debug = true): void
    {
        new TwigCacheCleanUp($this->root, self::VERSION, static fn(): ?string => $key, $debug)->cleanUp();
    }

    /**
     * @return list<string> Paths of the files below the cache directory, sorted.
     */
    private function remaining(): array
    {
        $files = [];
        foreach (Files::below($this->root) as $entry) {
            if ($entry->isFile()) {
                $files[] = substr($entry->getPathname(), strlen($this->root) + 1);
            }
        }
        sort($files);

        return $files;
    }

    public function testOlderVersionsAndLeftoversOfDevelopmentModeAreRemoved(): void
    {
        $this->compiled(self::VERSION . '/ab/abcd.php');
        $this->compiled('1111111111111111/ab/abcd.php');
        $this->compiled('1111111111111111/' . self::KEY . '/cd/cdef.php');
        $this->compiled('ef/ef01.php');

        $this->cleanUp(null);

        self::assertSame([self::VERSION . '/ab/abcd.php'], $this->remaining());
    }

    public function testOverrideDirectoriesOfOtherKeysAreRemoved(): void
    {
        $this->compiled(self::VERSION . '/ab/abcd.php');
        $this->compiled(self::VERSION . '/' . self::KEY . '/cd/cdef.php');
        $this->compiled(self::VERSION . '/' . self::OLD_KEY . '/cd/cdef.php');

        $this->cleanUp(self::KEY);

        self::assertSame([self::VERSION . '/ab/abcd.php', self::VERSION . '/' . self::KEY . '/cd/cdef.php'], $this->remaining());
    }

    public function testWithoutOverridesEveryOverrideDirectoryIsRemoved(): void
    {
        $this->compiled(self::VERSION . '/ab/abcd.php');
        $this->compiled(self::VERSION . '/' . self::OLD_KEY . '/cd/cdef.php');

        $this->cleanUp(null);

        self::assertSame([self::VERSION . '/ab/abcd.php'], $this->remaining());
    }

    public function testOverrideDirectoriesStayOnMultisite(): void
    {
        $GLOBALS['wp_app_framework_test_multisite'] = true;
        $this->compiled(self::VERSION . '/' . self::KEY . '/cd/cdef.php');
        $this->compiled(self::VERSION . '/' . self::OLD_KEY . '/cd/cdef.php');
        $this->compiled('1111111111111111/ab/abcd.php');

        $this->cleanUp(self::KEY);

        self::assertSame([self::VERSION . '/' . self::OLD_KEY . '/cd/cdef.php', self::VERSION . '/' . self::KEY . '/cd/cdef.php'], $this->remaining());
    }

    public function testAMissingDirectoryIsNothingToCleanUp(): void
    {
        new TwigCacheCleanUp($this->root . '/missing', self::VERSION, static fn(): ?string => null, true)->cleanUp();

        self::assertDirectoryDoesNotExist($this->root . '/missing');
    }

    public function testFailuresAreThrownWithDebugging(): void
    {
        $this->compiled('1111111111111111/ab/abcd.php');
        chmod($this->root . '/1111111111111111/ab', 0o555);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(sprintf('Cannot remove %s/1111111111111111/ab/abcd.php: Permission denied.', $this->root));
            $this->cleanUp(null);
        } finally {
            chmod($this->root . '/1111111111111111/ab', 0o755);
        }
    }

    public function testFailuresAreLoggedWithoutDebugging(): void
    {
        $this->compiled('1111111111111111/ab/abcd.php');
        chmod($this->root . '/1111111111111111/ab', 0o555);
        $log = $this->root . '/../' . basename($this->root) . '.log';
        $previous = ini_set('error_log', $log);

        try {
            $this->cleanUp(null, false);
        } finally {
            chmod($this->root . '/1111111111111111/ab', 0o755);
            ini_set('error_log', $previous === false ? '' : $previous);
        }

        $logged = (string) file_get_contents($log);
        unlink($log);
        self::assertStringContainsString(sprintf('wp-app-framework: clean-up of the Twig cache %s failed: RuntimeException: Cannot remove %s/1111111111111111/ab/abcd.php: Permission denied.', $this->root, $this->root), $logged);
    }
}
