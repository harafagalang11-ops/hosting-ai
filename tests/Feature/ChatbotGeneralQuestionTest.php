<?php

namespace Tests\Feature;

use App\Models\MonitoringLog;
use App\Models\User;
use App\Services\HermesRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ChatbotGeneralQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_hosting_question_is_sent_to_hermes_runner(): void
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
            'response_time' => 0.500,
            'checked_at' => now('UTC'),
        ]);

        $fakeHermes = new class extends HermesRunner {
            public function run(string $prompt): Process
            {
                if (! str_contains($prompt, 'Apa perbedaan hosting dan domain?')) {
                    throw new \RuntimeException('Pertanyaan tidak diteruskan ke HermesRunner.');
                }

                $process = new Process([
                    PHP_BINARY,
                    '-r',
                    'echo ' . escapeshellarg(
                        'Hosting adalah layanan penyimpanan dan penyajian website. '
                        . 'Domain adalah alamat yang digunakan untuk mengakses website.'
                    ) . ';',
                ]);

                $process->run();

                return $process;
            }
        };

        $this->app->instance(HermesRunner::class, $fakeHermes);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/chatbot/send', [
            'message' => 'Apa perbedaan hosting dan domain?',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'reply',
                'Hosting adalah layanan penyimpanan dan penyajian website. '
                . 'Domain adalah alamat yang digunakan untuk mengakses website.'
            );

        $this->assertDatabaseHas('chat_messages', [
            'user_id' => $user->id,
            'role' => 'user',
            'message' => 'Apa perbedaan hosting dan domain?',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'user_id' => $user->id,
            'role' => 'assistant',
            'message' => $response->json('reply'),
        ]);
    }
}
