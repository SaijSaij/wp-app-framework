<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Block;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * One item of a repeater (`Repeater`): the inner blocks rendered for a post, term, comment, user or row. Print the
 * content in the element marked with `repeater_item()`:
 *
 * ```twig
 * {% for item in items %}<li{{ repeater_item(item) }}>{{ item.content|raw }}</li>{% endfor %}
 * ```
 *
 * In renders for the block editor the content is empty: the editor shows the inner blocks itself.
 *
 * @api Values and exceptions
 */
#[NoService]
final readonly class RepeaterItem
{
    /**
     * @param array<string, int|string> $context The block context of the item.
     * @internal Created by Repeater.
     */
    public function __construct(
        private array $context,
        private string $content,
    ) {}

    /**
     * The inner blocks rendered for the item, as HTML.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * The block context of the item, e.g. `['postId' => 12, 'postType' => 'post']`.
     *
     * @return array<string, int|string>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
