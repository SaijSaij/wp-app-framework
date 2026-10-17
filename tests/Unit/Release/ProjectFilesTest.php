<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Release;

use WebCodingCat\WpAppFramework\Release\ProjectFiles;
use WebCodingCat\WpAppFramework\Release\ReleaseException;

final class ProjectFilesTest extends ReleaseTestCase
{
    private string $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = $this->root . '/project';
        mkdir($this->project);
        $this->git('project', 'init', '--quiet');
        $this->write('project/.gitignore', "*.log\n/vendor/\n");
        $this->write('project/.gitattributes', "/tests/** export-ignore\n/phpstan.neon export-ignore\n");
        $this->write('project/tracked.txt', 'tracked');
        $this->write('project/deleted.txt', 'deleted');
        $this->git('project', 'add', 'tracked.txt', 'deleted.txt');
        unlink($this->project . '/deleted.txt');
        $this->write('project/untracked.txt', 'untracked');
        $this->write('project/ignored.log', 'ignored');
        $this->write('project/vendor/package/file.php', '<?php');
        $this->write('project/tests/Unit/Test.php', '<?php');
        $this->write('project/phpstan.neon', '');
        chmod($this->project . '/untracked.txt', 0755);
    }

    public function testListsTheWorkingTreeWithoutIgnoredOrDeletedFiles(): void
    {
        $files = ProjectFiles::of($this->process(), $this->project);

        self::assertSame(
            ['.gitattributes', '.gitignore', 'phpstan.neon', 'tests/Unit/Test.php', 'tracked.txt', 'untracked.txt'],
            $files->getFiles(),
        );
        self::assertSame(['phpstan.neon', 'tests/Unit/Test.php'], $files->getExportIgnored());
    }

    public function testCopiesWithOrWithoutExportIgnoredFiles(): void
    {
        $files = ProjectFiles::of($this->process(), $this->project);

        $files->copyTo($this->root . '/full', true);
        $files->copyTo($this->root . '/dist', false);

        self::assertFileExists($this->root . '/full/tests/Unit/Test.php');
        self::assertFileDoesNotExist($this->root . '/dist/tests/Unit/Test.php');
        self::assertFileDoesNotExist($this->root . '/dist/phpstan.neon');
        self::assertSame('untracked', file_get_contents($this->root . '/dist/untracked.txt'));
        self::assertSame(0755, fileperms($this->root . '/dist/untracked.txt') & 0777);
        self::assertFileDoesNotExist($this->root . '/dist/vendor/package/file.php');
    }

    public function testSymbolicLinksAreRejected(): void
    {
        symlink('tracked.txt', $this->project . '/link.txt');
        $files = ProjectFiles::of($this->process(), $this->project);

        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage('link.txt is a symbolic link');

        $files->copyTo($this->root . '/copy', true);
    }

    public function testDirectoryOutsideAWorkingTreeIsAnError(): void
    {
        mkdir($this->root . '/plain');

        $this->expectException(ReleaseException::class);

        ProjectFiles::of($this->process(), $this->root . '/plain');
    }
}
