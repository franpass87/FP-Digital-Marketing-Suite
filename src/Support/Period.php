<?php

declare(strict_types=1);

namespace FP\DMS\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use RuntimeException;

class Period
{
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {
    }

    /**
     * Create a Period from string dates.
     *
     * @param string $start Start date string
     * @param string $end End date string
     * @param string|null $timezone Timezone identifier
     * @return self
     * @throws RuntimeException If dates or timezone are invalid
     */
    public static function fromStrings(string $start, string $end, ?string $timezone = null): self
    {
        try {
            $tz = null;
            if ($timezone !== null) {
                $tz = new DateTimeZone($timezone);
            }
            
            $startDate = new DateTimeImmutable($start, $tz);
            $endDate = new DateTimeImmutable($end, $tz);

            return new self($startDate, $endDate);
        } catch (Exception $e) {
            throw new RuntimeException(
                sprintf('Invalid period dates or timezone: start=%s, end=%s, tz=%s. Error: %s', 
                    $start, 
                    $end, 
                    $timezone ?? 'null',
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
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
