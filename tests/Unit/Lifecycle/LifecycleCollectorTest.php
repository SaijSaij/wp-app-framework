<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Lifecycle;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Lifecycle\LifecycleCollector;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Tests\Fixtures\LifecycleBroken;
use Saij\WpAppFramework\Tests\Fixtures\LifecycleValid;
use Saij\WpAppFramework\Tests\Support\Files;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;

/**
 * #[OnActivation], #[OnDeactivation] and #[OnUninstall] in the build: the manifest entries and every error.
 */
final class LifecycleCollectorTest extends TestCase
{
    private ?string $directory = null;

    protected function tearDown(): void
    {
        if ($this->directory !== null) {
            Files::remove($this->directory);
        }
    }

    public function testMethodsOfEachPhaseInTheOrderOfClassAndMethodName(): void
    {
        $lifecycle = new ManifestBuilder([ManifestBuilderTest::fixture('LifecycleValid')], kind: ProjectKind::Plugin)->build()->getLifecycle();

        $alpha = ['class' => LifecycleValid\Alpha::class, 'method' => 'both', 'arguments' => []];
        self::assertSame(
            [
                'activation' => [$alpha, ['class' => LifecycleValid\Setup::class, 'method' => 'install', 'arguments' => []]],
                'deactivation' => [
                    $alpha,
                    ['class' => LifecycleValid\Setup::class, 'method' => 'pause', 'arguments' => [['param' => 'journal', 'service' => LifecycleValid\Journal::class]]],
                ],
                'uninstall' => [['class' => LifecycleValid\Setup::class, 'method' => 'purge', 'arguments' => []]],
            ],
            $lifecycle,
        );
    }

    public function testReportsEveryErrorWithItsLocation(): void
    {
        $methods = LifecycleBroken\Methods::class . '::';
        $expected = [
            $methods . 'hidden() has #[OnActivation] and must be public and non-static.',
            $methods . 'statically() has #[OnActivation] and must be public and non-static.',
            $methods . 'returns() has #[OnDeactivation] and must return void.',
            'parameter $count of ' . $methods . 'argument(): WordPress passes no arguments the method could use; the parameters of #[OnDeactivation] methods must be services.',
            'parameter $helpers of ' . $methods . 'variadic(): variadic and by-reference parameters are not supported in lifecycle methods.',
            $methods . 'uninstall() has #[OnUninstall], but only plugins can be uninstalled: WordPress loads no code of a theme it deletes, so the data of a theme stays.',
            LifecycleBroken\NotAService::class . '::run() has #[OnActivation], but ' . LifecycleBroken\NotAService::class . ' is marked #[NoService] and therefore not a service.',
        ];

        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('LifecycleBroken')])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            self::assertSame($expected, $exception->getErrors());
        }
    }

    public function testUninstallIsAnErrorInAChildTheme(): void
    {
        $this->expectException(BuildException::class);
        $this->expectExceptionMessage(LifecycleBroken\Methods::class . '::uninstall() has #[OnUninstall], but only plugins can be uninstalled: WordPress loads no code of a theme it deletes, so the data of a child theme stays.');

        new ManifestBuilder([ManifestBuilderTest::fixture('LifecycleBroken')], kind: ProjectKind::ChildTheme, parent: 'parent-theme')->build();
    }

    public function testAnUninstallScriptOfAPluginIsAnError(): void
    {
        $this->directory = sys_get_temp_dir() . '/wpaf-lifecycle-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
        touch($this->directory . '/uninstall.php');
        $message = $this->directory . '/uninstall.php exists: WordPress runs it instead of the uninstall hook of the framework, which removes the data of the plugin. Move its code into a method with #[OnUninstall] and delete the file.';

        self::assertSame($message, LifecycleCollector::uninstallScriptError($this->directory));
        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('LifecycleValid')], kind: ProjectKind::Plugin, pluginDirectory: $this->directory)->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            self::assertSame([$message], $exception->getErrors());
        }
    }

    public function testAPluginWithoutUninstallScriptBuilds(): void
    {
        $this->directory = sys_get_temp_dir() . '/wpaf-lifecycle-' . bin2hex(random_bytes(4));
        mkdir($this->directory);

        self::assertNull(LifecycleCollector::uninstallScriptError($this->directory));
        $lifecycle = new ManifestBuilder([ManifestBuilderTest::fixture('LifecycleValid')], kind: ProjectKind::Plugin, pluginDirectory: $this->directory)->build()->getLifecycle();
        self::assertCount(1, $lifecycle['uninstall']);
    }
}
