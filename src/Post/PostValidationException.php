<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Post;

use InvalidArgumentException;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Thrown by AbstractPost::save() when a meta value does not match its schema;
 * nothing is written then.
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
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
