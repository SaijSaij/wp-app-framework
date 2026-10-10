<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Valid;

use Saij\WpAppFramework\Hook\Hook;

final class Hooks
{
    /** @var list<string> */
    private array $calls = [];

    #[Hook('fixture_filter')]
    public function filter(string $value, Greeter $greeter, ?Context $context = null): string
    {
        return $value . $greeter->greet($context?->getLabel() ?? 'nobody');
    }

    #[Hook('fixture_action', 5)]
    public function action(): void
    {
        $this->calls[] = 'action';
    }

    #[Hook('fixture_first')]
    #[Hook('fixture_second', 20)]
    public function repeated(): bool
    {
        return true;
    }

    public function notAHook(): void {}

    /**
     * @return list<string>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }
}
