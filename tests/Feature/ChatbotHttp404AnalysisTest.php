<?php

namespace Tests\Feature;

use App\Models\MonitoringLog;
use App\Models\User;
use App\Services\HermesRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ChatbotHttp404AnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_chatbot_receives_http_404_evidence_and_recommends_inspection(): void
    {
        $website = [
            'Test 404' => [
                'url' => 'https://example.test/not-found',
                'file' => '404.status',
            ],
        ];

        config([
            'hosting.websites' => $website,
            'hosting.status_path' => storage_path('framework/testing/no-real-status'),
            'hosting.report_directory' => storage_path('framework/testing/no-real-reports'),
        ]);

        MonitoringLog::create([
            'website_name' => 'Test 404',
            'website_url' => $website['Test 404']['url'],
            'status' => 'GANGGUAN',
            'http_code' => 404,
            'response_time' => 0.500,
            'detail' => 'Simulasi HTTP 404',
            'checked_at' => now('UTC'),
        ]);

        $this->app->instance(
            HermesRunner::class,
            new class extends HermesRunner {
                public function run(string $prompt): Process
                {
                    if (! str_contains($prompt, '404')) {
                        throw new \RuntimeException(
                            'Bukti HTTP 404 tidak ditemukan dalam prompt.'
                        );
                    }

                    foreach ([
                        'pemeriksaan URL',
                        'routing',
                        'keberadaan resource',
                    ] as $instruction) {
                        if (! str_contains($prompt, $instruction)) {
                            throw new \RuntimeException(
                                "Instruksi {$instruction} tidak ditemukan."
                            );
                        }
                    }

                    $reply = 'FAKTA MONITORING: Tercatat simulasi HTTP 404. '
                        . 'HIPOTESIS PENYEBAB: URL atau resource mungkin tidak ditemukan, '
                        . 'tetapi penyebab belum dapat dipastikan. '
                        . 'REKOMENDASI PEMERIKSAAN: Periksa URL, routing, '
                        . 'dan keberadaan resource.';

                    $process = new Process([
                        PHP_BINARY,
                        '-r',
                        'echo ' . escapeshellarg($reply) . ';',
                    ]);

                    $process->run();

                    return $process;
                }
            }
        );

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/chatbot/send', [
            'message' => 'Analisis error HTTP 404.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'reply',
                'FAKTA MONITORING: Tercatat simulasi HTTP 404. '
                . 'HIPOTESIS PENYEBAB: URL atau resource mungkin tidak ditemukan, '
                . 'tetapi penyebab belum dapat dipastikan. '
                . 'REKOMENDASI PEMERIKSAAN: Periksa URL, routing, '
                . 'dan keberadaan resource.'
            );
    }
}
