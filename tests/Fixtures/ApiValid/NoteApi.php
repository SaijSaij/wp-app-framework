<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ApiValid;

use WebCodingCat\WpAppFramework\Api\PublicApi;

#[PublicApi]
final class NoteApi
{
    public function __construct(private readonly NoteStore $store) {}

    /**
     * @return list<Note>
     */
    public function getNotes(): array
    {
        return $this->store->all();
    }

    public function first(): ?Note
    {
        return $this->store->all()[0] ?? null;
    }
}
