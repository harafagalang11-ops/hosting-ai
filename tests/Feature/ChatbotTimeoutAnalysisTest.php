<?php

namespace Tests\Feature;

use App\Models\MonitoringLog;
use App\Models\User;
use App\Services\HermesRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ChatbotTimeoutAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_chatbot_analyzes_timeout_without_claiming_unproven_cause(): void
    {
        $website = [
            'Test Timeout' => [
                'url' => 'https://example.test/timeout',
                'file' => 'timeout.status',
            ],
        ];

        config([
            'hosting.websites' => $website,
            'hosting.status_path' => storage_path('framework/testing/no-real-status'),
            'hosting.report_directory' => storage_path('framework/testing/no-real-reports'),
        ]);

        MonitoringLog::create([
            'website_name' => 'Test Timeout',
            'website_url' => $website['Test Timeout']['url'],
            'status' => 'GANGGUAN',
            'http_code' => null,
            'response_time' => null,
            'detail' => 'cURL error 28: Operation timed out after 10000 milliseconds',
            'checked_at' => now('UTC'),
        ]);

        $this->app->instance(
            HermesRunner::class,
            new class extends HermesRunner
            {
                public function run(string $prompt): Process
                {
                    if (! str_contains($prompt, 'cURL error 28')) {
                        throw new \RuntimeException(
                            'Bukti cURL error 28 tidak ditemukan dalam prompt.'
                        );
                    }

                    foreach ([
                        'connection timeout',
                        'DNS',
                        'firewall',
                        'tidak membuktikan penyebabnya',
                    ] as $instruction) {
                        if (! str_contains($prompt, $instruction)) {
                            throw new \RuntimeException(
                                "Instruksi {$instruction} tidak ditemukan."
                            );
                        }
                    }

                    $reply = 'FAKTA MONITORING: Permintaan mengalami timeout '
                        .'berdasarkan catatan cURL error 28. '
                        .'HIPOTESIS PENYEBAB: Penyebab timeout belum dapat dipastikan. '
                        .'REKOMENDASI PEMERIKSAAN: Periksa konektivitas jaringan, '
                        .'DNS, firewall, dan log terkait.';

                    $process = new Process([
                        PHP_BINARY,
                        '-r',
                        'echo '.escapeshellarg($reply).';',
                    ]);

                    $process->run();

                    return $process;
                }
            }
        );

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/chatbot/send', [
            'message' => 'Analisis gangguan connection timeout cURL error 28.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('reply', $reply = 'FAKTA MONITORING: Permintaan mengalami timeout '
                .'berdasarkan catatan cURL error 28. '
                .'HIPOTESIS PENYEBAB: Penyebab timeout belum dapat dipastikan. '
                .'REKOMENDASI PEMERIKSAAN: Periksa konektivitas jaringan, '
                .'DNS, firewall, dan log terkait.');
    }
}
