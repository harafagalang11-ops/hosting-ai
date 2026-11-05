<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HermesRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ChatbotNoMonitoringDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_chatbot_does_not_claim_server_status_without_monitoring_data(): void
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

        $this->app->instance(
            HermesRunner::class,
            new class extends HermesRunner {
                public function run(string $prompt): Process
                {
                    if (! str_contains($prompt, 'DATA BELUM CUKUP')) {
                        throw new \RuntimeException(
                            'Prompt tidak memberikan informasi keterbatasan data.'
                        );
                    }

                    $process = new Process([
                        PHP_BINARY,
                        '-r',
                        'echo ' . escapeshellarg(
                            'Belum tersedia data monitoring yang cukup untuk memastikan kondisi server. '
                            . 'Lakukan pemeriksaan monitoring terlebih dahulu.'
                        ) . ';',
                    ]);

                    $process->run();

                    return $process;
                }
            }
        );

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/chatbot/send', [
            'message' => 'Bagaimana kondisi server saat ini?',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'reply' => 'Belum tersedia data monitoring yang cukup untuk memastikan kondisi server. '
                    . 'Lakukan pemeriksaan monitoring terlebih dahulu.',
            ]);
    }
}
