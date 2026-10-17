<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CronValid;

enum ReminderKind: string
{
    case Mail = 'mail';
    case Sms = 'sms';
}
