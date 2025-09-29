<?php

declare(strict_types=1);

namespace FP\DMS\Support;

use DateTimeImmutable;
use DateTimeInterface;

class Period
{
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {
    }

    public static function fromStrings(string $start, string $end, ?string $timezone = null): self
    {
        $tz = $timezone ? new \DateTimeZone($timezone) : null;
        $startDate = new DateTimeImmutable($start, $tz);
        $endDate = new DateTimeImmutable($end, $tz);

        return new self($startDate, $endDate);
    }

    public function format(string $format): string
    {
        return $this->start->format($format) . ' - ' . $this->end->format($format);
    }

    public function toArray(): array
    {
        return [
            'start' => $this->start->format(DateTimeInterface::ATOM),
            'end' => $this->end->format(DateTimeInterface::ATOM),
        ];
    }
}
