<?php

namespace Tests\Feature;

use App\Models\MonitoringLog;
use App\Services\MonitoringTrendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringTrendDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_trend_uses_ten_latest_records_for_the_requested_website(): void
    {
        $baseTime = now()->subDays(2);

        // Data lama yang seharusnya tidak masuk perhitungan.
        foreach ([100.000, 100.000] as $index => $time) {
            MonitoringLog::create([
                'website_name' => 'Google',
                'website_url' => 'https://google.com',
                'status' => 'UP',
                'http_code' => '200',
                'response_time' => $time,
                'checked_at' => $baseTime->copy()->addSeconds($index),
            ]);
        }

        // Sepuluh data terbaru: lima sebelumnya dan lima terbaru.
        $responseTimes = [
            1.000, 1.000, 1.000, 1.000, 1.000,
            0.500, 0.500, 0.500, 0.500, 0.500,
        ];

        foreach ($responseTimes as $index => $time) {
            MonitoringLog::create([
                'website_name' => 'Google',
                'website_url' => 'https://google.com',
                'status' => 'UP',
                'http_code' => '200',
                'response_time' => $time,
                'checked_at' => $baseTime->copy()->addSeconds($index + 2),
            ]);
        }

        // Data website lain tidak boleh tercampur.
        MonitoringLog::create([
            'website_name' => 'GitHub',
            'website_url' => 'https://github.com',
            'status' => 'UP',
            'http_code' => '200',
            'response_time' => 5.000,
            'checked_at' => $baseTime->copy()->addSeconds(20),
        ]);

        // Data tanpa response_time tidak boleh dihitung.
        MonitoringLog::create([
            'website_name' => 'Google',
            'website_url' => 'https://google.com',
            'status' => 'DOWN',
            'http_code' => '500',
            'response_time' => null,
            'checked_at' => $baseTime->copy()->addSeconds(21),
        ]);

        $records = MonitoringLog::where('website_name', 'Google')
            ->whereNotNull('response_time')
            ->orderByDesc('checked_at')
            ->take(10)
            ->get();

        $result = app(MonitoringTrendService::class)
            ->analyze($records, 'Google');

        $this->assertSame(10, $result['jumlah_sampel']);
        $this->assertSame(1.000, $result['rata_rata_sebelumnya']);
        $this->assertSame(0.500, $result['rata_rata_terbaru']);
        $this->assertSame('MEMBAIK', $result['tren']);
    }
}
