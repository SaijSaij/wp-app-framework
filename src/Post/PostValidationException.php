<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Post;

use InvalidArgumentException;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Thrown by AbstractPost::save() when a meta value does not match its schema;
 * nothing is written then.
 *
 * @api Posts and queries
 */
#[NoService]
final class PostValidationException extends InvalidArgumentException
{
    /**
     * @param list<string> $errors One message per invalid value, with the meta class and the path.
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('The post was not saved: ' . implode(' ', $errors));
    }

    /**
     * The messages of the invalid values, one per value, with the meta class and the path of the value.
     *
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
