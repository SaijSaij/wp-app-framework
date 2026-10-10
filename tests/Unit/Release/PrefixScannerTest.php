<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Release;

use Saij\WpAppFramework\Release\PrefixScanner;
use Saij\WpAppFramework\Release\ReleaseException;

final class PrefixScannerTest extends ReleaseTestCase
{
    private const string CODE = <<<'PHP'
        <?php
        namespace Acme\Theme;

        use Twig\Environment;
        use Acme\Theme\Vendor\Twig\Markup;

        $a = new \Twig\Markup('', '');
        $b = new \Acme\Theme\Vendor\Twig\Markup('', '');
        $c = 'Twig\\Template';
        $d = "use Acme\\Theme\\Vendor\\Twig\\Source;";
        $e = 'MyTwig\\Thing';
        $f = 'Other\\Lib\\Thing';
        PHP;

    public function testFindsUnprefixedNamesAndOptionallyStrings(): void
    {
        $file = $this->write('code.php', self::CODE);
        $scanner = new PrefixScanner(['Twig\\', 'Other\\Lib\\']);

        self::assertSame(
            [$file . ':4: Twig\\Environment', $file . ':7: \\Twig\\Markup'],
            $scanner->scanFile($file, false),
        );
        self::assertSame(
            [$file . ':4: Twig\\Environment', $file . ':7: \\Twig\\Markup', $file . ":9: 'Twig\\\\Template'", $file . ":12: 'Other\\\\Lib\\\\Thing'"],
            $scanner->scanFile($file, true),
        );
    }

    public function testDirectoryScanDecidesStringsPerFile(): void
    {
        $this->write('tree/vendor-prefixed/lib.php', "<?php\n\$m = 'Use a \\Twig\\Template instance.';\n");
        $this->write('tree/src/Theme.php', "<?php\n\$m = 'Twig\\\\Template';\n");
        $scanner = new PrefixScanner(['Twig\\']);

        $findings = $scanner->scanDirectory($this->root . '/tree', static fn(string $file): bool => !str_starts_with($file, 'vendor-prefixed/'));

        self::assertSame([$this->root . "/tree/src/Theme.php:2: 'Twig\\\\Template'"], $findings);
    }

    public function testNamespacesComeFromTheInstalledPackages(): void
    {
        $this->write('vendor/composer/installed.json', (string) json_encode(['packages' => [
            ['name' => 'twig/twig', 'autoload' => ['psr-4' => ['Twig\\' => 'src/']]],
            ['name' => 'old/lib', 'autoload' => ['psr-0' => ['Old_' => 'lib/']]],
            ['name' => 'symfony/deprecation-contracts', 'autoload' => ['files' => ['function.php']]],
        ]]));
        $file = $this->write('code.php', "<?php\nuse Twig\\Environment;\n");

        self::assertSame([$file . ':2: Twig\\Environment'], PrefixScanner::forInstalledPackages($this->root . '/vendor')->scanFile($file, false));
    }

    public function testMissingInstalledPackagesAreAnError(): void
    {
        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage('installed.json not found');

        PrefixScanner::forInstalledPackages($this->root . '/vendor');
    }
}
