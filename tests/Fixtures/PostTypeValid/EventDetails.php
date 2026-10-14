<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeValid;

use Saij\WpAppFramework\Block\Image;
use Saij\WpAppFramework\Block\Label;
use Saij\WpAppFramework\Block\RichText;
use Saij\WpAppFramework\Post\PostMeta;
use Saij\WpAppFramework\Value\Link;
use Saij\WpAppFramework\Value\ListOf;
use Saij\WpAppFramework\Value\PostReference;

#[PostMeta('details', title: 'Event details', postTypes: [Event::class])]
final class EventDetails
{
    /**
     * @param list<Ticket> $tickets
     * @param list<Venue>  $alternatives
     */
    public function __construct(
        private int $capacity = 0,
        #[Label('Start')]
        private string $startDate = '',
        private ?Venue $venue = null,
        #[ListOf(Ticket::class)]
        private array $tickets = [],
        private Density $density = Density::Normal,
        #[RichText(allowedFormats: ['core/bold'])]
        private string $note = '',
        #[Image]
        private ?int $photo = null,
        #[ListOf(Venue::class)]
        private array $alternatives = [],
        #[PostReference(postTypes: [Event::class, 'page'])]
        protected ?int $related = null,
        private Link $registration = new Link(),
    ) {}

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): void
    {
        $this->capacity = $capacity;
    }

    public function getStartDate(): string
    {
        return $this->startDate;
    }

    public function getVenue(): ?Venue
    {
        return $this->venue;
    }

    public function setVenue(?Venue $venue): void
    {
        $this->venue = $venue;
    }

    /**
     * @return list<Ticket>
     */
    public function getTickets(): array
    {
        return $this->tickets;
    }

    /**
     * @param list<Ticket> $tickets
     */
    public function setTickets(array $tickets): void
    {
        $this->tickets = $tickets;
    }

    public function getDensity(): Density
    {
        return $this->density;
    }

    public function setDensity(Density $density): void
    {
        $this->density = $density;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $note): void
    {
        $this->note = $note;
    }

    public function getPhoto(): ?int
    {
        return $this->photo;
    }

    /**
     * @return list<Venue>
     */
    public function getAlternatives(): array
    {
        return $this->alternatives;
    }

    public function getRelated(): ?int
    {
        return $this->related;
    }

    public function getRegistration(): Link
    {
        return $this->registration;
    }

    public function setRegistration(Link $registration): void
    {
        $this->registration = $registration;
    }
}
