<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableValid;

use DateTimeImmutable;
use Saij\WpAppFramework\Table\AbstractRow;
use Saij\WpAppFramework\Table\Indexed;
use Saij\WpAppFramework\Table\Length;
use Saij\WpAppFramework\Table\PrimaryKey;
use Saij\WpAppFramework\Table\RenamedFrom;
use Saij\WpAppFramework\Table\Table;
use Saij\WpAppFramework\Table\Text;

/**
 * Every column type, an index of its own, a group and a unique index.
 */
#[Table('log', renamedFrom: ['journal'])]
final class LogEntry extends AbstractRow
{
    public function __construct(
        #[PrimaryKey]
        private ?int $id = null,
        #[Indexed(group: 'level_created', position: 1)]
        private Level $level = Level::Info,
        #[Length(500)]
        #[RenamedFrom('text')]
        #[RenamedFrom('body')]
        private string $message = '',
        #[Text]
        private string $context = '',
        #[Indexed(unique: true)]
        private ?string $reference = null,
        private int $attempts = 0,
        private float $ratio = 0.5,
        private bool $flagged = false,
        #[Indexed]
        private ?int $postId = null,
        private ?Priority $priority = null,
        private ?DateTimeImmutable $handled = null,
        #[Indexed(group: 'level_created', position: 2)]
        private DateTimeImmutable $created = new DateTimeImmutable(),
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLevel(): Level
    {
        return $this->level;
    }

    public function setLevel(Level $level): void
    {
        $this->level = $level;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getRatio(): float
    {
        return $this->ratio;
    }

    public function isFlagged(): bool
    {
        return $this->flagged;
    }

    public function getPostId(): ?int
    {
        return $this->postId;
    }

    public function getPriority(): ?Priority
    {
        return $this->priority;
    }

    public function getHandled(): ?DateTimeImmutable
    {
        return $this->handled;
    }

    public function getCreated(): DateTimeImmutable
    {
        return $this->created;
    }
}
