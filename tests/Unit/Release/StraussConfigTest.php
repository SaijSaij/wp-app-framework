<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Release;

use Saij\WpAppFramework\Release\ReleaseException;
use Saij\WpAppFramework\Release\StraussConfig;

final class StraussConfigTest extends ReleaseTestCase
{
    /**
     * @param array<string, mixed> $strauss
     */
    private function composer(array $strauss): string
    {
        return $this->write('composer.json', (string) json_encode([
            'autoload' => ['psr-4' => ['Vendor\\Theme\\' => 'src/']],
            'extra' => ['strauss' => $strauss],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private static function valid(): array
    {
        return [
            'target_directory' => 'vendor-prefixed',
            'namespace_prefix' => 'Vendor\\Theme\\Vendor\\',
            'include_root_autoload' => true,
            'update_call_sites' => ['src', 'functions.php'],
            'delete_vendor_packages' => false,
        ];
    }

    public function testValidSettingsProvideThePrefix(): void
    {
        self::assertSame('Vendor\\Theme\\Vendor\\', StraussConfig::fromComposerFile($this->composer(self::valid()), 'functions.php')->getNamespacePrefix());
    }

    public function testEveryWrongSettingIsReported(): void
    {
        $file = $this->composer([
            'target_directory' => 'vendor',
            'namespace_prefix' => 'Vendor\\Theme\\',
            'update_call_sites' => true,
            'delete_vendor_files' => true,
        ]);

        try {
            StraussConfig::fromComposerFile($file, 'functions.php');
            self::fail('Expected a ReleaseException.');
        } catch (ReleaseException $exception) {
            $message = $exception->getMessage();
        }

        self::assertStringContainsString('extra.strauss.target_directory must be "vendor-prefixed", found "vendor".', $message);
        self::assertStringContainsString('extra.strauss.include_root_autoload must be true, found null.', $message);
        self::assertStringContainsString('extra.strauss.delete_vendor_files must be false or absent, found true.', $message);
        self::assertStringContainsString('extra.strauss.update_call_sites must list', $message);
        self::assertStringContainsString('is the namespace of the project itself', $message);
    }

    public function testPrefixNeedsATrailingBackslash(): void
    {
        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage('extra.strauss.namespace_prefix must be a namespace with a trailing backslash');

        StraussConfig::fromComposerFile($this->composer(['namespace_prefix' => 'Vendor\\Theme\\Vendor'] + self::valid()), 'functions.php');
    }

    public function testMissingSettingsAreAnError(): void
    {
        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage('has no extra.strauss settings');

        StraussConfig::fromComposerFile($this->write('composer.json', '{}'), 'functions.php');
    }

    public function testTheBootFileMustBeRewritten(): void
    {
        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage('extra.strauss.update_call_sites must contain "my-plugin.php", which boots the framework, found ["src","functions.php"].');

        StraussConfig::fromComposerFile($this->composer(self::valid()), 'my-plugin.php');
    }
}
