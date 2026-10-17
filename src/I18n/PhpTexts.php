<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\I18n;

use PhpToken;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Finds the texts of the PHP files of a project: the calls of WordPress' translation functions (`__()`, `_x()`,
 * `_n()`, `esc_html__()` and the others) with the text domain of the project and literal strings, read with the
 * tokenizer. A comment starting with `translators:` before a call becomes the comment of its text.
 *
 * @internal
 */
#[NoService]
final class PhpTexts
{
    /** The arguments of each function by position: text, plural, context and domain; null for the number. */
    private const array FUNCTIONS = [
        '__' => ['text', 'domain'],
        '_e' => ['text', 'domain'],
        'esc_html__' => ['text', 'domain'],
        'esc_html_e' => ['text', 'domain'],
        'esc_attr__' => ['text', 'domain'],
        'esc_attr_e' => ['text', 'domain'],
        '_x' => ['text', 'context', 'domain'],
        '_ex' => ['text', 'context', 'domain'],
        'esc_html_x' => ['text', 'context', 'domain'],
        'esc_attr_x' => ['text', 'context', 'domain'],
        '_n' => ['text', 'plural', null, 'domain'],
        '_nx' => ['text', 'plural', null, 'context', 'domain'],
        '_n_noop' => ['text', 'plural', 'domain'],
        '_nx_noop' => ['text', 'plural', 'context', 'domain'],
    ];

    /** Tokens before a name that make it no call of a global function: a method, a declaration, a constant. */
    private const array NO_CALL = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST];

    /**
     * @param string $file     Absolute path of the PHP file.
     * @param string $relative Path relative to the project, for the references.
     */
    public static function collect(string $file, string $relative, string $domain, PotFile $pot): void
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize((string) file_get_contents($file)),
            static fn(PhpToken $token): bool => !$token->is(T_WHITESPACE),
        ));
        $comment = null;
        foreach ($tokens as $index => $token) {
            if ($token->is([T_COMMENT, T_DOC_COMMENT])) {
                $comment = self::translatorsComment($token->text) ?? $comment;
                continue;
            }
            if ($token->text === ';') {
                $comment = null;
                continue;
            }
            $name = $token->is(T_NAME_FULLY_QUALIFIED) ? ltrim($token->text, '\\') : ($token->is(T_STRING) ? $token->text : null);
            if ($name === null || !isset(self::FUNCTIONS[$name]) || ($tokens[$index + 1]->text ?? null) !== '(') {
                continue;
            }
            if ($index > 0 && $tokens[$index - 1]->is(self::NO_CALL)) {
                continue;
            }
            $arguments = self::arguments($tokens, $index + 1);
            $values = [];
            foreach (self::FUNCTIONS[$name] as $position => $role) {
                if ($role === null) {
                    continue;
                }
                $argument = $arguments[$position] ?? [];
                $values[$role] = count($argument) === 1 && $argument[0]->is(T_CONSTANT_ENCAPSED_STRING) ? self::string($argument[0]->text) : null;
            }
            if ($values['domain'] !== $domain || $values['text'] === null) {
                continue;
            }
            $pot->add($values['text'], $values['context'] ?? null, $values['plural'] ?? null, $relative . ':' . $token->line, $comment);
            $comment = null;
        }
    }

    /**
     * The arguments of the call whose opening parenthesis is at $open: each a list of tokens.
     *
     * @param list<PhpToken> $tokens
     * @return list<list<PhpToken>>
     */
    private static function arguments(array $tokens, int $open): array
    {
        $arguments = [[]];
        $depth = 0;
        for ($index = $open; $index < count($tokens); $index++) {
            $text = $tokens[$index]->text;
            if (in_array($text, ['(', '[', '{'], true)) {
                $depth++;
                if ($depth === 1) {
                    continue;
                }
            } elseif (in_array($text, [')', ']', '}'], true)) {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            } elseif ($text === ',' && $depth === 1) {
                $arguments[] = [];
                continue;
            }
            if (!$tokens[$index]->is([T_COMMENT, T_DOC_COMMENT])) {
                $arguments[count($arguments) - 1][] = $tokens[$index];
            }
        }

        return $arguments;
    }

    /**
     * The value of a string literal in single or double quotes.
     */
    private static function string(string $literal): string
    {
        $inner = substr($literal, 1, -1);

        return $literal[0] === "'" ? strtr($inner, ['\\\\' => '\\', "\\'" => "'"]) : stripcslashes($inner);
    }

    /**
     * The text of a comment for translators, without comment markers; null for any other comment.
     */
    private static function translatorsComment(string $comment): ?string
    {
        $text = trim((string) preg_replace(['#^/\*+|\*+/$#', '#^//#', '#^\s*\*\s?#m'], '', $comment));

        return stripos($text, 'translators:') === 0 ? $text : null;
    }
}
