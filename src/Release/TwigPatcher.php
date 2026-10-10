<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Release;

use ParseError;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Prefixes the class names Twig's compiler writes into compiled templates.
 *
 * Twig generates the PHP code of a template at runtime from strings such as
 * `"use Twig\Environment;\n"`. No prefixing tool rewrites names inside such
 * strings, so compiled templates of a prefixed Twig would load the unprefixed
 * `Twig\` classes – missing, or those of another plugin. This patch rewrites
 * exactly the known places. Every pattern must occur as often as expected; a
 * Twig update that changes them fails the build instead of shipping a broken
 * release. The release build checks the compiled templates afterwards.
 */
#[NoService]
final class TwigPatcher
{
    /**
     * Patterns per file below Twig's `src/`: search, replacement and expected count.
     * `{p}` is the prefix with single backslashes, `{pp}` with doubled backslashes
     * (inside double-quoted strings or code strings that escape their backslashes).
     */
    private const array PATTERNS = [
        ['Node/ModuleNode.php', '"use Twig\\', '"use {pp}Twig\\', 15],
        ['Node/ModuleNode.php', '"private \\\\Twig\\\\Runtime\\\\EscaperRuntime', '"private \\\\{pp}Twig\\\\Runtime\\\\EscaperRuntime', 1],
        ['Node/CaptureNode.php', "'\\\\Twig\\\\Extension\\\\CoreExtension::captureOutput('", "'\\\\{pp}Twig\\\\Extension\\\\CoreExtension::captureOutput('", 1],
        ['Node/Expression/CallExpression.php', '(\\Twig\\Extension\\SandboxExtension::class)', '(\\{p}Twig\\Extension\\SandboxExtension::class)', 2],
        ['Node/Expression/Binary/HasSomeBinary.php', '(\\Twig\\Extension\\SandboxExtension::class)', '(\\{p}Twig\\Extension\\SandboxExtension::class)', 2],
        ['Node/Expression/Binary/HasEveryBinary.php', '(\\Twig\\Extension\\SandboxExtension::class)', '(\\{p}Twig\\Extension\\SandboxExtension::class)', 2],
        ['Node/Expression/Binary/ObjectDestructuringSetBinary.php', '\\\\Twig\\\\Template::ANY_CALL', '\\\\{pp}Twig\\\\Template::ANY_CALL', 1],
        ['Profiler/Node/EnterProfileNode.php', 'new \\Twig\\Profiler\\Profile(', 'new \\{p}Twig\\Profiler\\Profile(', 1],
    ];

    /**
     * @param string           $twigDirectory The prefixed copy of twig/twig (containing `src/`).
     * @param non-empty-string $prefix        Namespace prefix with a trailing backslash.
     * @return int Number of replacements.
     */
    public static function patch(string $twigDirectory, string $prefix): int
    {
        $source = $twigDirectory . '/src';
        $version = self::version($source);
        $single = $prefix;
        $double = str_replace('\\', '\\\\', $prefix);

        $contents = [];
        $errors = [];
        $replacements = 0;
        foreach (self::PATTERNS as [$file, $search, $replace, $expected]) {
            $path = $source . '/' . $file;
            $contents[$file] ??= is_file($path) ? (string) file_get_contents($path) : null;
            if ($contents[$file] === null) {
                $errors[] = sprintf('%s does not exist.', $path);
                continue;
            }

            $found = substr_count($contents[$file], $search);
            if ($found !== $expected) {
                $errors[] = sprintf('%s contains `%s` %d times, expected %d.', $path, $search, $found, $expected);
                continue;
            }
            $contents[$file] = str_replace($search, strtr($replace, ['{pp}' => $double, '{p}' => $single]), $contents[$file]);
            $replacements += $found;
        }

        if ($errors !== []) {
            throw new ReleaseException(sprintf(
                "Twig %s does not match the patterns of the release build (Release\\TwigPatcher); update them for this Twig version:\n- %s",
                $version,
                implode("\n- ", $errors),
            ));
        }

        foreach ($contents as $file => $code) {
            $path = $source . '/' . $file;
            try {
                $tokens = token_get_all((string) $code, TOKEN_PARSE);
            } catch (ParseError $error) {
                throw new ReleaseException(sprintf('Patching %s produced invalid PHP: %s', $path, $error->getMessage()));
            }
            if ($tokens === []) {
                throw new ReleaseException(sprintf('Patching %s produced an empty file.', $path));
            }
            if (file_put_contents($path, (string) $code) === false) {
                throw new ReleaseException(sprintf('Cannot write %s.', $path));
            }
        }

        return $replacements;
    }

    private static function version(string $source): string
    {
        $file = $source . '/Environment.php';
        $environment = is_file($file) ? (string) file_get_contents($file) : '';
        if (preg_match("/public const VERSION = '([^']+)';/", $environment, $match) !== 1) {
            throw new ReleaseException(sprintf('%s not found or without VERSION; is twig/twig installed?', $file));
        }

        return $match[1];
    }
}
