<?php

declare(strict_types=1);

namespace app\components\values;

use DateTimeImmutable;

/**
 * Календарный месяц - период, за который считаются дашборды и заводятся планы.
 *
 * Один объект вместо date('Y-m-01', strtotime($date)) и date('Y-m-t', ...),
 * разложенных по каждому запросу: границы месяца считаются здесь один раз.
 */
final readonly class Period
{
    private function __construct(public DateTimeImmutable $begin, public DateTimeImmutable $end)
    {
    }

    public static function ofMonth(DateTimeImmutable $day): self
    {
        return new self(
            $day->modify('first day of this month')->setTime(0, 0),
            $day->modify('last day of this month')->setTime(23, 59, 59),
        );
    }

    public static function currentMonth(): self
    {
        return self::ofMonth(new DateTimeImmutable('now'));
    }

    public function next(): self
    {
        return self::ofMonth($this->begin->modify('first day of next month'));
    }

    public function previous(): self
    {
        return self::ofMonth($this->begin->modify('first day of previous month'));
    }

    public function month(): int
    {
        return (int) $this->begin->format('n');
    }

    public function year(): int
    {
        return (int) $this->begin->format('Y');
    }

    /**
     * Условие для ActiveQuery: значение колонки попадает в период.
     *
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    public function condition(string $column): array
    {
        return ['between', $column, $this->begin->format('Y-m-d H:i:s'), $this->end->format('Y-m-d H:i:s')];
    }

    public function title(): string
    {
        return $this->begin->format('m.Y');
    }
}
