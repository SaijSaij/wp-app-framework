<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Release;

use FilesystemIterator;
use JsonException;
use ParseError;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Saij\WpAppFramework\DependencyInjection\NoService;
use SplFileInfo;

/**
 * Finds references to namespaces of dependencies that were not prefixed.
 *
 * Names in code (`use Twig\Environment;`, `\Twig\Markup`) are always checked.
 * String literals are checked where they are code or class names (compiled
 * templates, the theme's own files, the manifest); dependencies mention their
 * own class names in messages, which is harmless.
 */
#[NoService]
final class PrefixScanner
{
    /**
     * @param list<non-empty-string> $namespaces Original namespaces of the dependencies, with a trailing backslash.
     */
    public function __construct(
        private readonly array $namespaces,
    ) {}

    /**
     * Namespaces of the packages Composer installed (PSR-4 and PSR-0 prefixes).
     */
    public static function forInstalledPackages(string $vendorDirectory): self
    {
        $file = $vendorDirectory . '/composer/installed.json';
        if (!is_file($file)) {
            throw new ReleaseException(sprintf('%s not found; run composer install first.', $file));
        }
        try {
            $installed = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ReleaseException(sprintf('%s is not valid JSON: %s', $file, $exception->getMessage()));
        }

        $packages = is_array($installed) && is_array($installed['packages'] ?? null) ? $installed['packages'] : [];
        $namespaces = [];
        foreach ($packages as $package) {
            $autoload = is_array($package) && is_array($package['autoload'] ?? null) ? $package['autoload'] : [];
            foreach (['psr-4', 'psr-0'] as $type) {
                foreach (array_keys(is_array($autoload[$type] ?? null) ? $autoload[$type] : []) as $namespace) {
                    $namespace = rtrim((string) $namespace, '\\');
                    if ($namespace !== '') {
                        $namespaces[$namespace . '\\'] = true;
                    }
                }
            }
        }

        return new self(array_keys($namespaces));
    }

    /**
     * @return list<string> Findings as `file:line: reference`.
     */
    public function scanFile(string $file, bool $strings): array
    {
        try {
            $tokens = token_get_all((string) file_get_contents($file), TOKEN_PARSE);
        } catch (ParseError $error) {
            return [sprintf('%s:%d: cannot be parsed: %s', $file, $error->getLine(), $error->getMessage())];
        }

        $findings = [];
        foreach ($tokens as $token) {
            if (!is_array($token)) {
                continue;
            }
            [$type, $text, $line] = $token;

            if ($type === T_NAME_QUALIFIED || $type === T_NAME_FULLY_QUALIFIED) {
                $name = ltrim($text, '\\');
                foreach ($this->namespaces as $namespace) {
                    if (str_starts_with($name, $namespace)) {
                        $findings[] = sprintf('%s:%d: %s', $file, $line, $text);
                    }
                }
            } elseif ($strings && ($type === T_CONSTANT_ENCAPSED_STRING || $type === T_ENCAPSED_AND_WHITESPACE)) {
                $value = str_replace('\\\\', '\\', $text);
                foreach ($this->namespaces as $namespace) {
                    // Not part of a longer name: the prefixed form has a backslash or a letter before it.
                    if (preg_match('/(?<![\w\\\\])' . preg_quote($namespace, '/') . '/', $value) === 1) {
                        $findings[] = sprintf('%s:%d: %s', $file, $line, $text);
                    }
                }
            }
        }

        return $findings;
    }

    /**
     * @param callable(string): bool $withStrings Receives the path relative to the directory.
     * @return list<string> Findings of all PHP files below the directory.
     */
    public function scanDirectory(string $directory, callable $withStrings): array
    {
        $findings = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        $files = [];
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        foreach ($files as $file) {
            array_push($findings, ...$this->scanFile($file, $withStrings(substr($file, strlen($directory) + 1))));
        }

        return $findings;
    }
}
