<?php

declare(strict_types=1);

namespace App\Services\Planning;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class PlanningPeriodService
{
    public const WEEKLY = 'weekly';

    public const MONTHLY = 'monthly';

    public const QUARTERLY = 'quarterly';

    /**
     * @return array<int, string>
     */
    public function types(): array
    {
        return [self::WEEKLY, self::MONTHLY, self::QUARTERLY];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{period_type: string, period_start: string, period_end: string}
     */
    public function normalize(array $attributes): array
    {
        $periodType = (string) ($attributes['period_type'] ?? '');
        $periodStart = $this->parseDate($attributes['period_start'] ?? null, 'period_start');
        $periodEnd = $this->parseDate($attributes['period_end'] ?? null, 'period_end');
        $period = $this->periodForDate($periodStart, $periodType);

        if ($period['period_start'] !== $periodStart->toDateString() || $period['period_end'] !== $periodEnd->toDateString()) {
            throw ValidationException::withMessages([
                'period_start' => "The {$periodType} period must start on {$period['period_start']} and end on {$period['period_end']}.",
            ]);
        }

        return [
            'period_type' => $periodType,
            'period_start' => $period['period_start'],
            'period_end' => $period['period_end'],
        ];
    }

    /**
     * @return array{period_type: string, period_start: string, period_end: string}
     */
    public function periodForDate(CarbonImmutable|string $date, string $periodType): array
    {
        $date = $date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date);

        $start = match ($periodType) {
            self::WEEKLY => $date->startOfWeek(CarbonImmutable::MONDAY),
            self::MONTHLY => $date->startOfMonth(),
            self::QUARTERLY => $date->setMonth(1 + (int) (floor(($date->month - 1) / 3) * 3))->startOfMonth(),
            default => throw ValidationException::withMessages([
                'period_type' => 'Period type must be weekly, monthly, or quarterly.',
            ]),
        };

        $end = match ($periodType) {
            self::WEEKLY => $start->addDays(6),
            self::MONTHLY => $start->endOfMonth(),
            self::QUARTERLY => $start->addMonths(2)->endOfMonth(),
        };

        return [
            'period_type' => $periodType,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
        ];
    }

    /**
     * @return array<int, array{period_type: string, period_start: string, period_end: string}>
     */
    public function previousPeriods(string $periodType, string $periodStart, int $count): array
    {
        if ($count < 1) {
            throw ValidationException::withMessages(['lookback_periods' => 'Lookback periods must be at least one.']);
        }

        $current = CarbonImmutable::parse($periodStart);
        $periods = [];

        for ($index = 1; $index <= $count; $index++) {
            $anchor = match ($periodType) {
                self::WEEKLY => $current->subWeeks($index),
                self::MONTHLY => $current->subMonthsNoOverflow($index),
                self::QUARTERLY => $current->subMonthsNoOverflow($index * 3),
                default => throw ValidationException::withMessages([
                    'period_type' => 'Period type must be weekly, monthly, or quarterly.',
                ]),
            };
            $periods[] = $this->periodForDate($anchor, $periodType);
        }

        return $periods;
    }

    private function parseDate(mixed $value, string $field): CarbonImmutable
    {
        if ($value === null || $value === '') {
            throw ValidationException::withMessages([$field => 'A valid date is required.']);
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'A valid date is required.']);
        }
    }
}
