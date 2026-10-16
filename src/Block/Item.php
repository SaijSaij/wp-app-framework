<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Attribute;

/**
 * Marks a parameter of render() that receives the current item of a repeater (`Repeater`) the block is in: a post
 * (a post class of the theme or plugin, or `WP_Post`), a term (`WP_Term`), a comment (`WP_Comment`), a user
 * (`WP_User`) or a row of a table of the theme or plugin (its row class). The type of the parameter says which, and
 * it is nullable: outside an item of that kind, the value is null.
 *
 * The value comes from the block context the repeater sets – `postId`, `termId`, `commentId`, `userId` or
 * `{namespace}/{table}` –, which the block uses without `usesContext`. WordPress also sets `postId` for the post being
 * shown, so outside a repeater a post parameter is that post, as for `core/post-title`; a post class gets only posts
 * of its own type.
 *
 * ```php
 * public function render(CardAttributes $attrs, #[Item] ?Event $event): string
 * ```
 *
 * @api Attributes/Blocks
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Item {}
