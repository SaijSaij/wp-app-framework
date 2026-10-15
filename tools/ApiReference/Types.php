<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tools\ApiReference;

use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use UnitEnum;

/**
 * Resolves class names in types as PHP does in the file of a class, and renders
 * types and default values as Markdown, with links to the pages of API classes.
 */
final class Types
{
    /** @var array<string, array<string, string>> Use statements by file: alias => full name. */
    private array $uses = [];

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param string                $prefix Namespace prefix of the framework, e.g. `Saij\WpAppFramework\`.
     * @param array<string, string> $pages  Page paths below `docs/content/` without `.md`, by full class name.
     */
    public function __construct(
        private readonly string $prefix,
        private readonly array $pages,
    ) {}

    /**
     * Errors found while rendering: types that name internal classes of the framework.
     *
     * @return list<string>
     */
    public function errors(): array
    {
        return array_values(array_unique($this->errors));
    }

    /**
     * Renders a type as Markdown: code, with each API class as a link.
     *
     * @param ReflectionClass<object> $context  The class whose file the names are resolved in.
     * @param string                  $page     The page the type is shown on, below `docs/content/` without `.md`.
     * @param string                  $location Where the type is used, for errors.
     * @param bool                    $table    Whether the type is shown in a table cell (`|` is escaped).
     */
    public function render(string $type, ReflectionClass $context, string $page, string $location, bool $table = true): string
    {
        $parts = preg_split('/(\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*)/', $type, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $markdown = '';
        $code = '';
        $previous = '';
        foreach ($parts as $part) {
            // Identifiers after `-` (class-string, non-empty-string) or `::` (Enum::Case) are no class names.
            $isName = preg_match('/^\\\\?[A-Za-z_]/', $part) === 1 && !str_ends_with($previous, '-') && !str_ends_with($previous, '::');
            $class = $isName ? $this->resolve($part, $context) : null;
            if ($class !== null && str_starts_with($class, $this->prefix) && self::exists($class)) {
                if (!isset($this->pages[$class])) {
                    $this->errors[] = sprintf('%s names the internal class %s; mark it @api or change the signature.', $location, $class);
                } else {
                    $markdown .= self::code($code, $table) . sprintf('[`%s`](%s)', self::shortName($class), self::link($page, $this->pages[$class]));
                    $code = '';
                    $previous = $part;
                    continue;
                }
            }
            $code .= $class !== null && str_contains($class, '\\') ? self::shortName($class) : ltrim($part, '\\');
            $previous = $part;
        }

        return $markdown . self::code($code, $table);
    }

    /**
     * A native type as written in a signature, with short class names.
     */
    public static function native(?ReflectionType $type): string
    {
        return match (true) {
            $type === null => '',
            $type instanceof ReflectionNamedType => ($type->allowsNull() && $type->getName() !== 'mixed' && $type->getName() !== 'null' ? '?' : '') . self::shortName($type->getName()),
            $type instanceof ReflectionUnionType => implode('|', array_map(self::native(...), $type->getTypes())),
            $type instanceof ReflectionIntersectionType => implode('&', array_map(self::native(...), $type->getTypes())),
            default => (string) $type,
        };
    }

    /**
     * A default value as PHP code with short class names.
     */
    public static function value(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            is_string($value) => "'" . addcslashes($value, "'\\") . "'",
            $value instanceof UnitEnum => self::shortName($value::class) . '::' . $value->name,
            is_array($value) => '[' . implode(', ', array_map(
                static fn(int|string $key, mixed $item): string => (array_is_list($value) ? '' : self::value($key) . ' => ') . self::value($item),
                array_keys($value),
                $value,
            )) . ']',
            is_object($value) => 'new ' . self::shortName($value::class) . '(…)',
            default => get_debug_type($value),
        };
    }

    public static function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    /**
     * A relative link from one page to another, both below `docs/content/` without `.md`.
     */
    public static function link(string $from, string $to): string
    {
        $fromParts = explode('/', $from);
        array_pop($fromParts);
        $toParts = explode('/', $to);
        while ($fromParts !== [] && $toParts !== [] && $fromParts[0] === $toParts[0] && count($toParts) > 1) {
            array_shift($fromParts);
            array_shift($toParts);
        }

        return ($fromParts === [] ? './' : str_repeat('../', count($fromParts))) . implode('/', $toParts) . '.md';
    }

    /**
     * The full name a name in a doc comment refers to, or null for keywords and type variables of PHPStan.
     *
     * @param ReflectionClass<object> $context
     */
    private function resolve(string $name, ReflectionClass $context): ?string
    {
        if (str_starts_with($name, '\\')) {
            return substr($name, 1);
        }
        $lower = strtolower($name);
        if (in_array($lower, ['self', 'static'], true)) {
            return $context->getName();
        }
        $first = explode('\\', $name)[0];
        $uses = $this->uses($context);
        if (isset($uses[$first])) {
            return $uses[$first] . substr($name, strlen($first));
        }
        $local = $context->getNamespaceName() . '\\' . $name;
        if (self::exists($local)) {
            return $local;
        }

        return self::exists($name) ? $name : null;
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @return array<string, string>
     */
    private function uses(ReflectionClass $class): array
    {
        $file = (string) $class->getFileName();
        if (!isset($this->uses[$file])) {
            $this->uses[$file] = [];
            preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+([A-Za-z0-9_]+))?\s*;/m', (string) file_get_contents($file), $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $this->uses[$file][($match[2] ?? '') !== '' ? $match[2] : self::shortName($match[1])] = $match[1];
            }
        }

        return $this->uses[$file];
    }

    private static function exists(string $class): bool
    {
        return class_exists($class) || interface_exists($class) || enum_exists($class) || trait_exists($class);
    }

    private static function code(string $code, bool $table): string
    {
        if ($code === '') {
            return '';
        }

        return '`' . ($table ? str_replace('|', '\\|', $code) : $code) . '`';
    }
}
