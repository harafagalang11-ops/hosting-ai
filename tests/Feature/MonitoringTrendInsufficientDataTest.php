<?php

namespace Tests\Feature;

use App\Models\MonitoringLog;
use App\Services\MonitoringTrendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringTrendInsufficientDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_trend_is_not_concluded_when_fewer_than_ten_samples_exist(): void
    {
        $baseTime = now()->subDays(1);

        foreach ([1.000, 0.900, 0.800, 0.700, 0.600, 0.500, 0.400, 0.300, 0.200] as $index => $time) {
            MonitoringLog::create([
                'website_name' => 'Google',
                'website_url' => 'https://google.com',
                'status' => 'UP',
                'http_code' => '200',
                'response_time' => $time,
                'checked_at' => $baseTime->copy()->addSeconds($index),
            ]);
        }

        $records = MonitoringLog::where('website_name', 'Google')
            ->whereNotNull('response_time')
            ->orderByDesc('checked_at')
            ->take(10)
            ->get();

        $result = app(MonitoringTrendService::class)
            ->analyze($records, 'Google');

        $this->assertSame(9, $result['jumlah_sampel']);
        $this->assertSame('DATA BELUM CUKUP', $result['tren']);
    }
}
