<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CronValid;

use WebCodingCat\WpAppFramework\Value\ListOf;

final readonly class SendReminder
{
    /**
     * @param list<Recipient> $copies
     */
    public function __construct(
        public int $postId,
        public ReminderKind $kind = ReminderKind::Mail,
        public float $delay = 1.0,
        public ?Recipient $recipient = null,
        #[ListOf(Recipient::class)]
        public array $copies = [],
    ) {}
}
