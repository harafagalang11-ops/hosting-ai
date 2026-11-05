<?php

namespace Tests\Feature;

use App\Services\MonitoringTrendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringTrendEmptyDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_trend_is_not_concluded_when_no_samples_exist(): void
    {
        $result = app(MonitoringTrendService::class)
            ->analyze(collect(), 'Google');

        $this->assertSame(0, $result['jumlah_sampel']);
        $this->assertNull($result['rata_rata_sebelumnya']);
        $this->assertNull($result['rata_rata_terbaru']);
        $this->assertSame('DATA BELUM CUKUP', $result['tren']);
    }
}
