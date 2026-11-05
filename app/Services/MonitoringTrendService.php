<?php

namespace App\Services;

use Illuminate\Support\Collection;

class MonitoringTrendService
{
    public function analyze(Collection $records, string $website): array
    {
        $records = $records
            ->filter(fn ($record) => $record->response_time !== null)
            ->sortBy('checked_at')
            ->take(-10)
            ->values();

        $previous = $records->take(5);
        $recent = $records->slice(5, 5);

        $previousAverage = $previous->isNotEmpty()
            ? round($previous->avg('response_time'), 3)
            : null;

        $recentAverage = $recent->isNotEmpty()
            ? round($recent->avg('response_time'), 3)
            : null;

        $trend = 'DATA BELUM CUKUP';

        if ($previous->count() === 5 && $recent->count() === 5) {
            if ($recentAverage < $previousAverage) {
                $trend = 'MEMBAIK';
            } elseif ($recentAverage > $previousAverage) {
                $trend = 'MELAMBAT';
            } else {
                $trend = 'STABIL';
            }
        }

        return [
            'website' => $website,
            'jumlah_sampel' => $records->count(),
            'rata_rata_sebelumnya' => $previousAverage,
            'rata_rata_terbaru' => $recentAverage,
            'tren' => $trend,
        ];
    }
}
