<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Manifest\Discovery;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Manifest\Discovery\ClassDeclaration;

final class ClassDeclarationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, list<string>, list<string>}>
     */
    public static function declarations(): iterable
    {
        yield 'global parent' => ['<?php namespace App; final class Widget extends \WP_Widget {}', 'App\Widget', ['WP_Widget'], []];
        yield 'unqualified parent in the namespace' => ['<?php namespace App; final class Table extends Base {}', 'App\Table', ['App\Base'], []];
        yield 'alias and group import' => [
            '<?php namespace App; use WP_Widget as W; use Lib\{Contracts\A, Traits\T as Uses}; use function Lib\helper; use const Lib\X;
            final class Widget extends W implements A, \Countable { use Uses, namespace\Local; }',
            'App\Widget',
            ['WP_Widget', 'Lib\Contracts\A', 'Countable', 'Lib\Traits\T', 'App\Local'],
            [],
        ];
        yield 'qualified name through an import' => ['<?php namespace App; use Lib\Base; class C extends Base\Widget {}', 'App\C', ['Lib\Base\Widget'], []];
        yield 'interface extending interfaces' => ['<?php namespace App; interface I extends A, \B {}', 'App\I', ['App\A', 'B'], []];
        yield 'backed enum' => ['<?php namespace App; enum E: string implements \JsonSerializable { case A = "a"; }', 'App\E', ['JsonSerializable'], []];
        yield 'braced namespace' => ['<?php namespace App { use Lib\Base; class C extends Base {} }', 'App\C', ['Lib\Base'], []];
        yield 'no namespace' => ['<?php class C extends \WP_Widget {}', 'C', ['WP_Widget'], []];
        yield 'class constants, anonymous classes and closures are no declarations' => [
            '<?php namespace App; $a = Other::class; $b = new class extends \WP_Widget {};
            final class C { public function f(): void { $x = 1; $g = function () use ($x) { return new class extends \Exception {}; }; } }',
            'App\C',
            [],
            [],
        ];
        yield 'attributes with nested arguments' => [
            '<?php namespace App; use WebCodingCat\WpAppFramework\Hook\Hook; use Lib\Attr;
            #[\AllowDynamicProperties, Attr([1, 2], new Value(#[Inner] fn() => 1))]
            final class C { #[Hook("init"), \Override] public function f(#[Param] int $a): void {} }',
            'App\C',
            [],
            ['AllowDynamicProperties', 'Lib\Attr', 'App\Inner', 'WebCodingCat\WpAppFramework\Hook\Hook', 'Override', 'App\Param'],
        ];
    }

    /**
     * @param list<string> $parents
     * @param list<string> $attributes
     */
    #[DataProvider('declarations')]
    public function testReadsParentsAndAttributesAsFullNames(string $code, string $name, array $parents, array $attributes): void
    {
        $declaration = ClassDeclaration::read(self::file($code), $name);

        self::assertNotNull($declaration);
        self::assertSame($parents, $declaration->getParents());
        self::assertSame($attributes, $declaration->getAttributes());
    }

    public function testFileWithoutTheDeclarationHasNone(): void
    {
        self::assertNull(ClassDeclaration::read(self::file('<?php namespace App; final class Other {}'), 'App\Widget'));
    }

    public function testNamesAreCaseInsensitive(): void
    {
        self::assertNotNull(ClassDeclaration::read(self::file('<?php namespace App; final class Widget {}'), 'app\widget'));
    }

    private static function file(string $code): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'wpaf-declaration-');
        file_put_contents($file, $code);
        register_shutdown_function(static fn() => @unlink($file));

        return $file;
    }
}
