<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RestValid;

final class ItemRepository
{
    /** @var list<int> */
    private array $deleted = [];

    public function getTitle(int $id): string
    {
        return 'Item ' . $id;
    }

    public function isEditable(int $id): bool
    {
        return $id !== 13;
    }

    public function delete(int $id): void
    {
        $this->deleted[] = $id;
    }

    /**
     * @return list<int>
     */
    public function getDeleted(): array
    {
        return $this->deleted;
    }
}
