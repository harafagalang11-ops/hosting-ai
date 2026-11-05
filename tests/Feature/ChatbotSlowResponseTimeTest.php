<?php

namespace Tests\Feature;

use App\Models\MonitoringLog;
use App\Models\User;
use App\Services\HermesRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ChatbotSlowResponseTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_slow_response_time_with_normal_status_is_analyzed_safely(): void
    {
        config([
            'hosting.websites' => [
                'Google' => [
                    'url' => 'https://example.test',
                    'file' => 'google.status',
                ],
            ],
            'hosting.status_path' => storage_path('framework/testing/no-real-status'),
            'hosting.report_directory' => storage_path('framework/testing/no-real-reports'),
        ]);

        MonitoringLog::create([
            'website_name' => 'Google',
            'website_url' => 'https://example.test',
            'status' => 'NORMAL',
            'http_code' => 200,
            'response_time' => 4.500,
            'detail' => 'Simulasi response time lambat',
            'checked_at' => now('UTC'),
        ]);

        $fakeHermes = new class extends HermesRunner
        {
            public function run(string $prompt): Process
            {
                $requiredTexts = [
                    '4.5',
                    'NORMAL',
                    'response time di atas 3 detik',
                    'performa aplikasi',
                    'database',
                    'jaringan',
                    'penggunaan resource',
                    'Jangan menyimpulkan website GANGGUAN hanya karena response time lambat',
                ];

                foreach ($requiredTexts as $text) {
                    if (! str_contains($prompt, $text)) {
                        throw new \RuntimeException(
                            "Prompt tidak memuat aturan atau data yang diharapkan: {$text}"
                        );
                    }
                }

                $process = new Process([
                    PHP_BINARY,
                    '-r',
                    'echo '.escapeshellarg(
                        'FAKTA MONITORING: Google berstatus NORMAL dengan response time 4.500 detik. '
                        .'HIPOTESIS PENYEBAB: Performa aplikasi, database, atau jaringan mungkin memengaruhi waktu respons. '
                        .'REKOMENDASI PEMERIKSAAN: Periksa performa aplikasi, database, jaringan, dan penggunaan resource secara read-only. '
                        .'Respons lambat tidak otomatis berarti website mengalami gangguan.'
                    ).';',
                ]);

                $process->run();

                return $process;
            }
        };

        $this->app->instance(HermesRunner::class, $fakeHermes);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/chatbot/send', [
            'message' => 'Analisis response time Google.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'reply',
                'FAKTA MONITORING: Google berstatus NORMAL dengan response time 4.500 detik. '
                .'HIPOTESIS PENYEBAB: Performa aplikasi, database, atau jaringan mungkin memengaruhi waktu respons. '
                .'REKOMENDASI PEMERIKSAAN: Periksa performa aplikasi, database, jaringan, dan penggunaan resource secara read-only. '
                .'Respons lambat tidak otomatis berarti website mengalami gangguan.'
            );

        $this->assertDatabaseHas('chat_messages', [
            'user_id' => $user->id,
            'role' => 'user',
            'message' => 'Analisis response time Google.',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'user_id' => $user->id,
            'role' => 'assistant',
            'message' => $response->json('reply'),
        ]);
    }
}
