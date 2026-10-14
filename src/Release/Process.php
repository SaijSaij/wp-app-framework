<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Release;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Runs the external tools of the release build (git, composer, strauss, npm,
 * php) without a shell. A non-zero exit code is an error naming the command.
 */
#[NoService]
final class Process
{
    /**
     * @param resource $stdout Receives the output of the commands.
     * @param resource $stderr Receives the error output of the commands.
     */
    public function __construct(
        private $stdout,
        private $stderr,
    ) {}

    /**
     * Runs a command; its output goes to stdout and stderr.
     *
     * @param non-empty-list<string> $command
     * @param array<string, string>  $environment Variables added to the inherited environment.
     */
    public function run(array $command, string $directory, array $environment = []): void
    {
        fwrite($this->stdout, '$ ' . self::display($command) . "\n");
        $this->execute($command, $directory, [1 => $this->stdout, 2 => $this->stderr], null, $environment);
    }

    /**
     * Runs a command and returns its standard output.
     *
     * @param non-empty-list<string> $command
     */
    public function capture(array $command, string $directory, ?string $input = null): string
    {
        return $this->execute($command, $directory, [1 => ['pipe', 'w'], 2 => $this->stderr], $input, []);
    }

    /**
     * @param non-empty-list<string>                      $command
     * @param array{1: resource|list<string>, 2: resource} $output
     * @param array<string, string>                       $environment
     */
    private function execute(array $command, string $directory, array $output, ?string $input, array $environment): string
    {
        if (!self::isAvailable($command[0])) {
            throw new ReleaseException(sprintf('Command `%s` was not found; the release build needs %s in the PATH.', self::display($command), $command[0]));
        }

        // Input comes from a file: writing to a pipe while the command fills its output pipe could block both.
        $inputFile = null;
        if ($input !== null) {
            $inputFile = tempnam(sys_get_temp_dir(), 'wpaf-input');
            if ($inputFile === false || file_put_contents($inputFile, $input) === false) {
                throw new ReleaseException(sprintf('Cannot write the input of `%s` to a temporary file.', self::display($command)));
            }
        }

        try {
            $descriptors = [0 => ['file', $inputFile ?? '/dev/null', 'r'], ...$output];
            $variables = $environment === [] ? null : [...getenv(), ...$environment];

            $process = proc_open($command, $descriptors, $pipes, $directory, $variables);
            if (!is_resource($process)) {
                throw new ReleaseException(sprintf('Cannot start `%s` in %s.', self::display($command), $directory));
            }

            $captured = '';
            if (isset($pipes[1])) {
                $captured = (string) stream_get_contents($pipes[1]);
                fclose($pipes[1]);
            }
            $code = proc_close($process);
        } finally {
            if ($inputFile !== null) {
                unlink($inputFile);
            }
        }

        if ($code !== 0) {
            throw new ReleaseException(sprintf('Command `%s` failed in %s with exit code %d.', self::display($command), $directory, $code));
        }

        return $captured;
    }

    /**
     * proc_open() only warns if a program does not exist; check it first for a clear message.
     */
    /**
     * Path of a program in the PATH, for commands that start it through another one (e.g. `php`).
     */
    public function locate(string $program): string
    {
        return self::find($program) ?? throw new ReleaseException(sprintf('Command `%s` was not found; the release build needs %s in the PATH.', $program, $program));
    }

    private static function isAvailable(string $program): bool
    {
        if (str_contains($program, '/')) {
            return is_file($program) && is_executable($program);
        }

        return self::find($program) !== null;
    }

    private static function find(string $program): ?string
    {
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
            if ($directory !== '' && is_file($directory . '/' . $program) && is_executable($directory . '/' . $program)) {
                return $directory . '/' . $program;
            }
        }

        return null;
    }

    /**
     * @param list<string> $command
     */
    private static function display(array $command): string
    {
        return implode(' ', array_map(
            static fn(string $part): string => preg_match('/^[\w.\/:=@+-]+$/', $part) === 1 ? $part : escapeshellarg($part),
            $command,
        ));
    }
}
