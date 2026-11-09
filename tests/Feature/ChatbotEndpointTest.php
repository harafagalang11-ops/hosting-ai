<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\User;
use App\Models\Website;
use App\Services\HermesRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ChatbotEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_chatbot_returns_laravel_trend_data_without_real_hermes(): void
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

        $user = User::factory()->create();

        $website = $user->websites()->create([
            'name' => 'Google',
            'url' => 'https://example.test',
            'is_active' => true,
        ]);

        $baseTime = now('UTC')->subMinutes(10);

        foreach ([
            1.000, 1.000, 1.000, 1.000, 1.000,
            0.500, 0.500, 0.500, 0.500, 0.500,
        ] as $index => $responseTime) {
            $website->monitoringLogs()->create([
                'website_name' => 'Google',
                'website_url' => 'https://example.test',
                'status' => 'NORMAL',
                'http_code' => '200',
                'response_time' => $responseTime,
                'checked_at' => $baseTime->copy()->addSeconds($index),
            ]);
        }

        $this->app->instance(
            HermesRunner::class,
            new class extends HermesRunner
            {
                public function run(string $prompt): Process
                {
                    $process = new Process([
                        PHP_BINARY,
                        '-r',
                        'echo "FAKE HERMES RESPONSE";',
                    ]);

                    $process->run();

                    return $process;
                }
            }
        );

        $response = $this->actingAs($user)->postJson('/chatbot/send', [
            'message' => 'Tolong analisis tren response time.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'reply' => "Analisis tren response time berdasarkan data monitoring Laravel:\n"
                    .'Google: rata-rata 5 pemeriksaan sebelumnya 1.000 detik'
                    .', rata-rata 5 pemeriksaan terbaru 0.500 detik'
                    .', jumlah sampel 10, tren MEMBAIK.',
            ]);

        $this->assertDatabaseHas('chat_messages', [
            'user_id' => $user->id,
            'role' => 'user',
            'message' => 'Tolong analisis tren response time.',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'user_id' => $user->id,
            'role' => 'assistant',
            'message' => $response->json('reply'),
        ]);

        $this->assertSame(2, ChatMessage::where('user_id', $user->id)->count());
    }
}
