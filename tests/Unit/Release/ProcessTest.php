<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Release;

use WebCodingCat\WpAppFramework\Release\ReleaseException;

final class ProcessTest extends ReleaseTestCase
{
    public function testRunEchoesTheCommandAndPassesTheEnvironment(): void
    {
        $this->process()->run(['sh', '-c', 'echo "value: $RELEASE_TEST"'], $this->root, ['RELEASE_TEST' => 'x y']);

        self::assertSame("$ sh -c 'echo \"value: \$RELEASE_TEST\"'\nvalue: x y\n", $this->printed());
    }

    public function testCaptureReturnsTheOutputAndReadsTheInput(): void
    {
        self::assertSame(str_repeat('line', 50000), $this->process()->capture(['cat'], $this->root, str_repeat('line', 50000)));
    }

    public function testFailingCommandNamesCommandDirectoryAndExitCode(): void
    {
        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage(sprintf('Command `sh -c \'exit 3\'` failed in %s with exit code 3.', $this->root));

        $this->process()->run(['sh', '-c', 'exit 3'], $this->root);
    }

    public function testMissingCommandIsReported(): void
    {
        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage('the release build needs wpaf-missing-tool in the PATH');

        $this->process()->capture(['wpaf-missing-tool'], $this->root);
    }
}
