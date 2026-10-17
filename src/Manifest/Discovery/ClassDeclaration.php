<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Manifest\Discovery;

use PhpToken;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * The declaration of a class, interface, trait or enum read from its file with the tokenizer, without loading it:
 * the classes it builds on (`extends`, `implements`, traits) and the attributes in the file, as full names.
 */
#[NoService]
final readonly class ClassDeclaration
{
    /**
     * @param list<string> $parents    Full names of the extended classes and interfaces, implemented interfaces and used traits.
     * @param list<string> $attributes Full names of all attributes in the file.
     */
    private function __construct(
        private array $parents,
        private array $attributes,
    ) {}

    /**
     * Reads the declaration of the class with the full name from the file, or null if the file does not declare it.
     */
    public static function read(string $file, string $name): ?self
    {
        $code = file_get_contents($file);
        if ($code === false) {
            return null;
        }

        $tokens = array_values(array_filter(
            PhpToken::tokenize($code),
            static fn(PhpToken $token): bool => !$token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
        ));
        $count = count($tokens);

        $namespace = '';
        /** @var array<string, string> $imports Lowercase alias => full name. */
        $imports = [];
        $found = false;
        $parents = [];
        $attributeNames = [];
        $depth = 0;
        $classDepth = null;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token->is(T_NAMESPACE) && $depth === 0 && self::isName($tokens[$i + 1] ?? null)) {
                $namespace = $tokens[++$i]->text;
                $imports = [];
                continue;
            }

            // Imports at the top level or in a braced namespace; `use (` belongs to a closure.
            if ($token->is(T_USE) && $classDepth === null && $depth <= 1 && ($tokens[$i + 1] ?? null)?->text !== '(') {
                $i = self::readImports($tokens, $i + 1, $imports);
                continue;
            }

            if ($token->is(T_USE) && $classDepth !== null && $depth === $classDepth + 1) {
                // Traits used in the class body.
                for ($i++; $i < $count && self::isName($tokens[$i]); $i++) {
                    $parents[] = self::resolve($tokens[$i]->text, $namespace, $imports);
                    if (($tokens[$i + 1] ?? null)?->text !== ',') {
                        break;
                    }
                    $i++;
                }
                continue;
            }

            if ($token->is(T_ATTRIBUTE)) {
                $i = self::readAttributes($tokens, $i + 1, $namespace, $imports, $attributeNames);
                continue;
            }

            if ($token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM]) && $classDepth === null
                && !($tokens[$i - 1] ?? null)?->is([T_DOUBLE_COLON, T_NEW])
                && ($tokens[$i + 1] ?? null)?->is(T_STRING)
                && strcasecmp(ltrim($namespace . '\\' . $tokens[$i + 1]->text, '\\'), $name) === 0) {
                $found = true;
                $classDepth = $depth;
                for ($i += 2; $i < $count && $tokens[$i]->text !== '{'; $i++) {
                    if (self::isName($tokens[$i]) && ($tokens[$i - 1]->is([T_EXTENDS, T_IMPLEMENTS]) || $tokens[$i - 1]->text === ',')) {
                        $parents[] = self::resolve($tokens[$i]->text, $namespace, $imports);
                    }
                }
                $depth++;
                continue;
            }

            if ($token->text === '{' || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;
            } elseif ($token->text === '}') {
                $depth--;
                if ($classDepth !== null && $depth === $classDepth) {
                    $classDepth = null;
                }
            }
        }

        return $found ? new self(array_values(array_unique($parents)), array_values(array_unique($attributeNames))) : null;
    }

    /**
     * @return list<string> Full names of the extended classes and interfaces, implemented interfaces and used traits.
     */
    public function getParents(): array
    {
        return $this->parents;
    }

    /**
     * @return list<string> Full names of all attributes in the file.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    private static function isName(?PhpToken $token): bool
    {
        return $token !== null && $token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE]);
    }

    /**
     * Reads a `use` statement for classes (`use A\B as C, D;`, `use A\{B, C as D};`); functions and constants are
     * skipped. Returns the index of the closing `;`.
     *
     * @param list<PhpToken>        $tokens
     * @param array<string, string> $imports
     */
    private static function readImports(array $tokens, int $i, array &$imports): int
    {
        $skip = ($tokens[$i] ?? null)?->is([T_FUNCTION, T_CONST]) ?? false;
        $prefix = '';
        $name = null;
        $alias = null;

        for ($count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token->is(T_AS)) {
                $alias = ($tokens[++$i] ?? null)?->text;
            } elseif (self::isName($token) && ($tokens[$i + 1] ?? null)?->is(T_NS_SEPARATOR)) {
                // Group use: `A\{` is tokenized as the name followed by a separator.
                $prefix = $token->text . '\\';
                $i++;
            } elseif (self::isName($token)) {
                $name = $token->text;
            } elseif (in_array($token->text, [',', '}', ';'], true)) {
                if ($name !== null && !$skip) {
                    $full = ltrim($prefix . $name, '\\');
                    $imports[strtolower($alias ?? substr($full, (int) strrpos('\\' . $full, '\\')))] = $full;
                }
                $name = null;
                $alias = null;
                if ($token->text === ';') {
                    return $i;
                }
            }
        }

        return $i;
    }

    /**
     * Reads an attribute group `#[A, B(…)]` and returns the index of its closing `]`.
     *
     * @param list<PhpToken>        $tokens
     * @param array<string, string> $imports
     * @param list<string>          $names
     */
    private static function readAttributes(array $tokens, int $i, string $namespace, array $imports, array &$names): int
    {
        $nesting = 0;
        $expectName = true;
        for ($count = count($tokens); $i < $count; $i++) {
            $text = $tokens[$i]->text;
            if ($nesting === 0 && $text === ']') {
                return $i;
            }
            if ($tokens[$i]->is(T_ATTRIBUTE)) {
                // An attribute in an argument, e.g. on a closure.
                $i = self::readAttributes($tokens, $i + 1, $namespace, $imports, $names);
            } elseif ($text === '(' || $text === '[') {
                $nesting++;
            } elseif ($text === ')' || $text === ']') {
                $nesting--;
            } elseif ($nesting === 0 && $text === ',') {
                $expectName = true;
            } elseif ($nesting === 0 && $expectName && self::isName($tokens[$i])) {
                $names[] = self::resolve($text, $namespace, $imports);
                $expectName = false;
            }
        }

        return $i;
    }

    /**
     * The full name of a class name as written in the file.
     *
     * @param array<string, string> $imports
     */
    private static function resolve(string $name, string $namespace, array $imports): string
    {
        if (str_starts_with($name, '\\')) {
            return substr($name, 1);
        }
        if (str_starts_with(strtolower($name), 'namespace\\')) {
            return ltrim($namespace . '\\' . substr($name, strlen('namespace\\')), '\\');
        }

        $first = strtolower(explode('\\', $name, 2)[0]);
        if (isset($imports[$first])) {
            return $imports[$first] . substr($name, strlen($first));
        }

        return ltrim($namespace . '\\' . $name, '\\');
    }
}
