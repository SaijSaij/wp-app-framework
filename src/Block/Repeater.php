<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Block;

use Closure;
use InvalidArgumentException;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Editor\RenderContext;
use WebCodingCat\WpAppFramework\Post\AbstractPost;
use WebCodingCat\WpAppFramework\Table\AbstractRow;
use WebCodingCat\WpAppFramework\Table\TableRegistry;
use WP_Block;
use WP_Comment;
use WP_Post;
use WP_Term;
use WP_User;

/**
 * Renders the inner blocks of a block once per item: a post, a term, a comment, a user or a row of a table, like
 * `core/post-template` does for posts. A block receives it as parameter of `render()` instead of `string $content`;
 * which items there are, the block decides, e.g. with a query.
 *
 * ```php
 * public function render(EventListAttributes $attrs, Repeater $repeater): string
 * {
 *     $items = array_map($repeater->post(...), Event::query()->limit($attrs->count)->get());
 *
 *     return $this->renderer->render('Block/EventList/EventList.twig', ['items' => $items]);
 * }
 * ```
 *
 * Each item gives the inner blocks the block context of the WordPress loop blocks – `postId` and `postType`,
 * `termId` and `taxonomy`, `commentId` –, or `userId` for a user and `{namespace}/{table}` for a row; blocks of the
 * theme or plugin read the item with `#[Item]`, core blocks such as `core/post-title` with their own context. The
 * context of the ancestors stays available. For a post, the global post is set while its item renders.
 *
 * In renders for the block editor the items are empty: the editor shows the inner blocks of the first item, or the
 * one clicked, to edit, and the others as previews.
 *
 * @api Values and exceptions
 */
#[NoService]
final readonly class Repeater
{
    /**
     * @internal Created for each render of a block whose render() asks for it.
     */
    public function __construct(
        private WP_Block $block,
        private RenderContext $context,
    ) {}

    /**
     * The inner blocks for a post, with the context `postId` and `postType` and the post as global post.
     *
     * @param int|WP_Post|AbstractPost $post The post, its ID or its post object.
     * @throws InvalidArgumentException If the post does not exist.
     */
    public function post(int|WP_Post|AbstractPost $post): RepeaterItem
    {
        $id = $post instanceof AbstractPost ? $post->getId() : $post;
        $wpPost = $id === null ? null : get_post($id);
        if (!$wpPost instanceof WP_Post) {
            throw new InvalidArgumentException(sprintf('Repeater::post(): there is no post %s.', $id === null ? 'object without ID (not saved yet)' : 'with the ID ' . (is_int($id) ? $id : $id->ID)));
        }

        return $this->item(['postId' => $wpPost->ID, 'postType' => $wpPost->post_type], $wpPost);
    }

    /**
     * The inner blocks for a term, with the context `termId` and `taxonomy`.
     *
     * @param int|WP_Term $term The term or its ID.
     * @throws InvalidArgumentException If the term does not exist.
     */
    public function term(int|WP_Term $term): RepeaterItem
    {
        if (is_int($term)) {
            $found = get_term($term);
            $term = $found instanceof WP_Term ? $found : throw new InvalidArgumentException(sprintf('Repeater::term(): there is no term with the ID %d.', $term));
        }
        $wpTerm = $term;

        return $this->item(['termId' => $wpTerm->term_id, 'taxonomy' => $wpTerm->taxonomy]);
    }

    /**
     * The inner blocks for a comment, with the context `commentId`.
     *
     * @param int|WP_Comment $comment The comment or its ID.
     * @throws InvalidArgumentException If the comment does not exist.
     */
    public function comment(int|WP_Comment $comment): RepeaterItem
    {
        if (is_int($comment)) {
            $found = get_comment($comment);
            $comment = $found instanceof WP_Comment ? $found : throw new InvalidArgumentException(sprintf('Repeater::comment(): there is no comment with the ID %d.', $comment));
        }
        $wpComment = $comment;

        $id = $wpComment->comment_ID;

        return $this->item(['commentId' => is_numeric($id) ? (int) $id : 0]);
    }

    /**
     * The inner blocks for a user, with the context `userId`.
     *
     * @param int|WP_User $user The user or their ID.
     * @throws InvalidArgumentException If the user does not exist.
     */
    public function user(int|WP_User $user): RepeaterItem
    {
        if (is_int($user)) {
            $found = get_userdata($user);
            $user = $found instanceof WP_User ? $found : throw new InvalidArgumentException(sprintf('Repeater::user(): there is no user with the ID %d.', $user));
        }
        $wpUser = $user;

        return $this->item(['userId' => $wpUser->ID]);
    }

    /**
     * The inner blocks for a row of a table of a theme or plugin, with the context `{namespace}/{table}` (the
     * primary key).
     *
     * @param AbstractRow $row A saved row object.
     * @throws InvalidArgumentException If the row has not been saved yet.
     */
    public function row(AbstractRow $row): RepeaterItem
    {
        $store = TableRegistry::store($row::class);
        $id = $store->primaryKey($row);
        if ($id === null) {
            throw new InvalidArgumentException(sprintf('Repeater::row(): the %s has no primary key; save it first.', $row::class));
        }

        return $this->item([$store->contextName($row::class) => $id]);
    }

    /**
     * Renders the inner blocks with the context of the ancestors, the context the block provides and the item's.
     *
     * @param array<string, int|string> $item
     */
    private function item(array $item, ?WP_Post $post = null): RepeaterItem
    {
        if ($this->context->isEditor()) {
            return new RepeaterItem($item, '');
        }

        /** @var array{blockName?: string|null, attrs?: array<string, mixed>, innerBlocks?: list<array<string, mixed>>, innerHTML?: string, innerContent?: list<string|null>} $parsed */
        $parsed = $this->block->parsed_block;
        // A name without block type, like core/post-template: only the inner blocks render, no wrapper, no supports.
        $parsed['blockName'] = 'core/null';
        $context = [...$this->childContext(), ...$item];

        $previous = $GLOBALS['post'] ?? null;
        if ($post !== null) {
            $GLOBALS['post'] = $post;
            setup_postdata($post);
        }
        try {
            $content = new WP_Block($parsed, $context)->render(['dynamic' => false]);
        } finally {
            if ($post !== null) {
                $GLOBALS['post'] = $previous;
                if ($previous instanceof WP_Post) {
                    setup_postdata($previous);
                }
            }
        }

        return new RepeaterItem($item, $content);
    }

    /**
     * The context the inner blocks of the block get, as WP_Block::refresh_parsed_block_dependents() computes it: the
     * available context of the block (protected in WP_Block, class-wp-block.php) and what the block provides.
     *
     * @return array<string, mixed>
     */
    private function childContext(): array
    {
        /** @var array<string, mixed> $available */
        $available = Closure::bind(static fn(WP_Block $block): array => $block->available_context, null, WP_Block::class)($this->block);
        /** @var array<string, string> $provides */
        $provides = $this->block->block_type->provides_context ?? [];
        foreach ($provides as $name => $attribute) {
            if (array_key_exists($attribute, $this->block->attributes)) {
                $available[$name] = $this->block->attributes[$attribute];
            }
        }

        return $available;
    }
}
