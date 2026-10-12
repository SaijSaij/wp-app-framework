<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block\Markup;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Parses block markup into blocks with line numbers, for checks in the build (WordPress is not loaded there).
 *
 * Block comments are recognized exactly like WordPress does: the expression is the one of
 * `WP_Block_Parser::next_token()` (WordPress 7.1.3, `class-wp-block-parser.php:247`). Where WordPress silently
 * repairs markup – a closer without opener, a closer for another block, a block without closer, a comment that
 * looks like a block comment but is none – the parser reports an error and continues like WordPress.
 *
 * @phpstan-type Frame array{name: string, attributes: array<array-key, mixed>|null, line: int, php: bool, html: string, blocks: list<ParsedBlock>}
 */
#[NoService]
final class BlockMarkupParser
{
    /**
     * Stands for PHP code in patterns (PatternSource); found in a block comment, the comment contains PHP.
     */
    public const string PHP_MARKER = '__wp_app_framework_php__';

    private const string TOKEN = '/<!--\s+(?P<closer>\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\/)?(?P<name>[a-z][a-z0-9_-]*)\s+(?P<attrs>{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?}\s+)?(?P<void>\/)?-->/s';

    /** Something WordPress would take for a block comment, if it were well-formed. */
    private const string LOOSE_TOKEN = '/<!--\s*\/?wp:/';

    /** @var list<ParsedBlock> */
    private array $blocks = [];

    /** @var list<array{int, string}> */
    private array $errors = [];

    /** @var list<Frame> */
    private array $stack = [];

    private bool $freeformHtml = false;

    public function __construct(private readonly string $document)
    {
        $offset = 0;
        while (preg_match(self::TOKEN, $document, $match, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL, $offset) === 1) {
            [$token, $start] = $match[0];
            $this->text($offset, $start);
            $offset = $start + strlen((string) $token);

            $line = $this->line($start);
            $name = ($match['namespace'][0] ?? 'core/') . $match['name'][0];

            if ($match['closer'][0] !== null) {
                $this->close($name, $line);
                continue;
            }

            $attributes = [];
            if ($match['attrs'][0] !== null) {
                $decoded = json_decode($match['attrs'][0], true);
                $attributes = is_array($decoded) ? $decoded : null;
            }
            $frame = [
                'name' => $name,
                'attributes' => $attributes,
                'line' => $line,
                'php' => str_contains((string) $token, self::PHP_MARKER),
                'html' => '',
                'blocks' => [],
            ];

            if ($match['void'][0] !== null) {
                $this->add(self::block($frame));
            } else {
                $this->stack[] = $frame;
            }
        }
        $this->text($offset, strlen($document));

        // WordPress closes open blocks at the end of the document.
        while ($this->stack !== []) {
            $frame = array_pop($this->stack);
            $this->errors[] = [$frame['line'], sprintf('block %s is not closed.', $frame['name'])];
            $this->add(self::block($frame));
        }
    }

    /**
     * @return list<ParsedBlock> Top-level blocks; HTML outside of blocks is not kept.
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * Whether there is HTML outside of blocks (PHP code of patterns aside); WordPress parses it as freeform blocks.
     */
    public function hasFreeformHtml(): bool
    {
        return $this->freeformHtml;
    }

    /**
     * @return list<array{int, string}> Line and message of each error.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    private function text(int $start, int $end): void
    {
        if ($end <= $start) {
            return;
        }

        $text = substr($this->document, $start, $end - $start);
        if (preg_match(self::LOOSE_TOKEN, $text, $match, PREG_OFFSET_CAPTURE) === 1) {
            $this->errors[] = [$this->line($start + $match[0][1]), 'malformed block comment; WordPress treats it as HTML.'];
        }

        $last = array_key_last($this->stack);
        if ($last !== null) {
            $this->stack[$last]['html'] .= $text;
        } elseif (trim(str_replace(self::PHP_MARKER, '', $text)) !== '') {
            $this->freeformHtml = true;
        }
    }

    private function close(string $name, int $line): void
    {
        $frame = array_pop($this->stack);
        if ($frame === null) {
            $this->errors[] = [$line, sprintf('closing comment of block %s without an opening comment.', $name)];

            return;
        }

        if ($frame['name'] !== $name) {
            $this->errors[] = [$line, sprintf('closing comment of block %s, but block %s (line %d) is open.', $name, $frame['name'], $frame['line'])];
        }
        $this->add(self::block($frame));
    }

    private function add(ParsedBlock $block): void
    {
        $last = array_key_last($this->stack);
        if ($last === null) {
            $this->blocks[] = $block;
        } else {
            $this->stack[$last]['blocks'][] = $block;
        }
    }

    private function line(int $offset): int
    {
        return substr_count($this->document, "\n", 0, $offset) + 1;
    }

    /**
     * @param Frame $frame
     */
    private static function block(array $frame): ParsedBlock
    {
        return new ParsedBlock($frame['name'], $frame['attributes'], $frame['line'], $frame['html'], $frame['blocks'], $frame['php']);
    }
}
