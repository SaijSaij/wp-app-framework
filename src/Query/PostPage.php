<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Post\AbstractPost;

/**
 * One page of the posts of a query (PostQuery::paginate()) with the number of all matching posts.
 *
 * @template T of AbstractPost
 *
 * @api Posts and queries
 */
#[NoService]
final readonly class PostPage
{
    /**
     * @param list<T> $posts
     *
     * @internal Created by PostQuery::paginate().
     */
    public function __construct(
        private array $posts,
        private int $total,
        private int $page,
        private int $perPage,
    ) {}

    /**
     * The posts of the page, in the order of the query; an empty list after the last page.
     *
     * @return list<T>
     */
    public function getPosts(): array
    {
        return $this->posts;
    }

    /**
     * The number of matching posts on all pages.
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * The page, starting with 1.
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * The number of posts per page, as passed to PostQuery::paginate().
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * The number of pages; 0 without matching posts.
     */
    public function getPageCount(): int
    {
        return (int) ceil($this->total / $this->perPage);
    }
}
