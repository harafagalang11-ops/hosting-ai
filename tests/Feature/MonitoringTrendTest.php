<?php

namespace Tests\Feature;

use App\Services\MonitoringTrendService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonitoringTrendTest extends TestCase
{
    #[DataProvider('trendProvider')]
    public function test_trend_is_calculated_correctly(
        array $responseTimes,
        string $expectedTrend,
        ?float $expectedPrevious,
        ?float $expectedRecent
    ): void {
        $records = collect($responseTimes)->values()->map(
            fn ($time, $index) => (object) [
                'response_time' => $time,
                'checked_at' => now()->addSeconds($index),
            ]
        );

        $result = app(MonitoringTrendService::class)
            ->analyze($records, 'Website Simulasi');

        $this->assertSame($expectedTrend, $result['tren']);
        $this->assertSame(count($responseTimes), $result['jumlah_sampel']);
        $this->assertSame($expectedPrevious, $result['rata_rata_sebelumnya']);
        $this->assertSame($expectedRecent, $result['rata_rata_terbaru']);
    }

    public function test_only_ten_latest_records_are_used(): void
    {
        $responseTimes = [
            100.000, 100.000,
            1.000, 1.000, 1.000, 1.000, 1.000,
            0.500, 0.500, 0.500, 0.500, 0.500,
        ];

        $records = collect($responseTimes)->values()->map(
            fn ($time, $index) => (object) [
                'response_time' => $time,
                'checked_at' => now()->addSeconds($index),
            ]
        );

        $result = app(MonitoringTrendService::class)
            ->analyze($records, 'Website Simulasi');

        $this->assertSame(10, $result['jumlah_sampel']);
        $this->assertSame(1.000, $result['rata_rata_sebelumnya']);
        $this->assertSame(0.500, $result['rata_rata_terbaru']);
        $this->assertSame('MEMBAIK', $result['tren']);
    }

    public static function trendProvider(): array
    {
        return [
            'membaik' => [
                [1.000, 1.000, 1.000, 1.000, 1.000, 0.500, 0.500, 0.500, 0.500, 0.500],
                'MEMBAIK', 1.000, 0.500,
            ],
            'melambat' => [
                [0.500, 0.500, 0.500, 0.500, 0.500, 1.000, 1.000, 1.000, 1.000, 1.000],
                'MELAMBAT', 0.500, 1.000,
            ],
            'stabil' => [
                [0.750, 0.750, 0.750, 0.750, 0.750, 0.750, 0.750, 0.750, 0.750, 0.750],
                'STABIL', 0.750, 0.750,
            ],
            'data belum cukup' => [
                [1.000, 1.000, 1.000, 1.000, 1.000, 0.500, 0.500, 0.500, 0.500],
                'DATA BELUM CUKUP', 1.000, 0.500,
            ],
        ];
    }
}
