<?php

namespace Tests\Feature;

use App\Models\MonitoringLog;
use App\Models\User;
use App\Services\HermesRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ChatbotHttpErrorAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_chatbot_receives_http_5xx_evidence_and_recommends_inspection(): void
    {
        $websites = [
            'Test 500' => [
                'url' => 'https://example.test/500',
                'file' => '500.status',
            ],
            'Test 502' => [
                'url' => 'https://example.test/502',
                'file' => '502.status',
            ],
            'Test 503' => [
                'url' => 'https://example.test/503',
                'file' => '503.status',
            ],
        ];

        config([
            'hosting.websites' => $websites,
            'hosting.status_path' => storage_path('framework/testing/no-real-status'),
            'hosting.report_directory' => storage_path('framework/testing/no-real-reports'),
        ]);

        $errors = [
            ['Test 500', 500],
            ['Test 502', 502],
            ['Test 503', 503],
        ];

        foreach ($errors as [$name, $code]) {
            MonitoringLog::create([
                'website_name' => $name,
                'website_url' => $websites[$name]['url'],
                'status' => 'GANGGUAN',
                'http_code' => $code,
                'response_time' => 0.500,
                'detail' => "Simulasi HTTP {$code}",
                'checked_at' => now('UTC'),
            ]);
        }

        $this->app->instance(
            HermesRunner::class,
            new class extends HermesRunner {
                public function run(string $prompt): Process
                {
                    foreach ([500, 502, 503] as $code) {
                        if (! str_contains($prompt, (string) $code)) {
                            throw new \RuntimeException(
                                "Bukti HTTP {$code} tidak ditemukan dalam prompt."
                            );
                        }
                    }

                    if (! str_contains($prompt, 'pemeriksaan log aplikasi dan server')) {
                        throw new \RuntimeException(
                            'Instruksi pemeriksaan HTTP 5xx tidak ditemukan.'
                        );
                    }

                    $process = new Process([
                        PHP_BINARY,
                        '-r',
                        'echo ' . escapeshellarg(
                            'FAKTA MONITORING: Terdapat simulasi HTTP 500, 502, dan 503. '
                            . 'HIPOTESIS PENYEBAB: Belum dapat dipastikan. '
                            . 'REKOMENDASI PEMERIKSAAN: Periksa log aplikasi dan server '
                            . 'serta kondisi resource server.'
                        ) . ';',
                    ]);

                    $process->run();

                    return $process;
                }
            }
        );

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/chatbot/send', [
            'message' => 'Analisis error HTTP 500, 502, dan 503.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'reply',
                'FAKTA MONITORING: Terdapat simulasi HTTP 500, 502, dan 503. '
                . 'HIPOTESIS PENYEBAB: Belum dapat dipastikan. '
                . 'REKOMENDASI PEMERIKSAAN: Periksa log aplikasi dan server '
                . 'serta kondisi resource server.'
            );
    }
}
