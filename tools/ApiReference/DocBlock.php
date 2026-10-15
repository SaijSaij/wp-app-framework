<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tools\ApiReference;

use Closure;
use PHPStan\PhpDocParser\Ast\Attribute;
use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\InvalidTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ThrowsTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

/**
 * The parts of a doc comment the API reference shows: the description, the
 * texts and types of `@param`, `@return` and `@throws`, and the tags `@api`,
 * `@internal` and `@see`.
 */
final readonly class DocBlock
{
    /**
     * @param array<string, array{type: string, description: string}> $params      By parameter name without `$`.
     * @param list<array{type: string, description: string}>          $throws
     * @param string|null                                             $api         Text of `@api` (the group), null without the tag.
     * @param list<string>                                            $see
     */
    private function __construct(
        private string $description,
        private array $params,
        private ?string $returnType,
        private string $returnDescription,
        private array $throws,
        private ?string $api,
        private bool $internal,
        private array $see,
        private ?string $varType,
    ) {}

    public static function parse(string|false $comment): self
    {
        if ($comment === false || trim($comment) === '') {
            return new self('', [], null, '', [], null, false, [], null);
        }

        // Indexes give each type node its tokens, so types are shown as written.
        $config = new ParserConfig(['indexes' => true]);
        $lexer = new Lexer($config);
        $constants = new ConstExprParser($config);
        $parser = new PhpDocParser($config, new TypeParser($config, $constants), $constants);

        $tokens = $lexer->tokenize($comment);
        $node = $parser->parse(new TokenIterator($tokens));
        $type = static function (?Node $type) use ($tokens): ?string {
            if ($type === null) {
                return null;
            }
            $start = $type->getAttribute(Attribute::START_INDEX);
            $end = $type->getAttribute(Attribute::END_INDEX);
            if (!is_int($start) || !is_int($end)) {
                return (string) $type;
            }

            return implode('', array_map(
                static fn(array $token): string => $token[Lexer::VALUE_OFFSET],
                array_slice($tokens, $start, $end - $start + 1),
            ));
        };
        $return = array_values($node->getReturnTagValues())[0] ?? null;
        $var = array_values($node->getVarTagValues())[0] ?? null;

        return new self(
            self::description($node),
            self::params($node, $type),
            $type($return?->type),
            self::text($return === null ? '' : $return->description),
            array_map(
                static fn(ThrowsTagValueNode $tag): array => ['type' => (string) $type($tag->type), 'description' => self::text($tag->description)],
                array_values($node->getThrowsTagValues()),
            ),
            self::tag($node, '@api'),
            $node->getTagsByName('@internal') !== [],
            array_values(array_filter(array_map(
                static fn(PhpDocTagNode $tag): string => self::text((string) $tag->value),
                $node->getTagsByName('@see'),
            ))),
            $type($var?->type),
        );
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return array{type: string, description: string}|null
     */
    public function getParam(string $name): ?array
    {
        return $this->params[$name] ?? null;
    }

    public function getReturnType(): ?string
    {
        return $this->returnType;
    }

    public function getReturnDescription(): string
    {
        return $this->returnDescription;
    }

    /**
     * @return list<array{type: string, description: string}>
     */
    public function getThrows(): array
    {
        return $this->throws;
    }

    /**
     * The group of the API reference from `@api <group>`, '' for a bare `@api`, null without the tag.
     */
    public function getApi(): ?string
    {
        return $this->api;
    }

    public function isInternal(): bool
    {
        return $this->internal;
    }

    /**
     * @return list<string>
     */
    public function getSee(): array
    {
        return $this->see;
    }

    public function getVarType(): ?string
    {
        return $this->varType;
    }

    private static function description(PhpDocNode $node): string
    {
        $lines = [];
        foreach ($node->children as $child) {
            if (!$child instanceof PhpDocTextNode) {
                break;
            }
            $lines[] = $child->text;
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @param Closure(?Node): ?string $type
     *
     * @return array<string, array{type: string, description: string}>
     */
    private static function params(PhpDocNode $node, Closure $type): array
    {
        $params = [];
        foreach ($node->getParamTagValues() as $tag) {
            $params[substr($tag->parameterName, 1)] = ['type' => (string) $type($tag->type), 'description' => self::text($tag->description)];
        }
        foreach ($node->getTypelessParamTagValues() as $tag) {
            $params[substr($tag->parameterName, 1)] = ['type' => '', 'description' => self::text($tag->description)];
        }

        return $params;
    }

    private static function tag(PhpDocNode $node, string $name): ?string
    {
        $tags = array_values($node->getTagsByName($name));
        if ($tags === []) {
            return null;
        }
        $value = $tags[0]->value;

        return trim($value instanceof GenericTagValueNode || $value instanceof InvalidTagValueNode ? (string) $value : '');
    }

    /**
     * Joins the lines of a tag description, keeping blank lines as paragraph breaks.
     */
    private static function text(string $text): string
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($text)) ?: [];

        return implode("\n\n", array_map(
            static fn(string $paragraph): string => (string) preg_replace('/\s*\n\s*/', ' ', trim($paragraph)),
            $paragraphs,
        ));
    }
}
