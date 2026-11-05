<?php

namespace Tests\Feature;

use App\Models\MonitoringLog;
use App\Models\User;
use App\Services\HermesRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ChatbotDailyReportAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_report_is_included_in_hermes_prompt_with_date_rules(): void
    {
        $reportDirectory = storage_path('framework/testing/daily-reports');

        if (! is_dir($reportDirectory)) {
            mkdir($reportDirectory, 0777, true);
        }

        $reportFile = $reportDirectory . '/2026-10-20.md';

        file_put_contents(
            $reportFile,
            'LAPORAN TEST 2026-10-20. HTTP 200. Response time 1.520 detik.'
        );

        try {
            config([
                'hosting.websites' => [
                    'Google' => [
                        'url' => 'https://example.test',
                        'file' => 'google.status',
                    ],
                ],
                'hosting.status_path' => storage_path('framework/testing/no-real-status'),
                'hosting.report_directory' => $reportDirectory,
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
                    $requiredContent = [
                        'Analisis laporan tanggal 2026-10-20',
                        '2026-10-20.md',
                        'LAPORAN TEST 2026-10-20',
                        'HTTP 200',
                        '1.520 detik',
                        'gunakan hanya isi laporan harian yang tanggalnya diminta',
                        'Jangan mencampurkan data laporan historis dengan STATUS TERKINI',
                        'Sebutkan nama file sebagai sumber',
                    ];

                    foreach ($requiredContent as $content) {
                        if (! str_contains($prompt, $content)) {
                            throw new \RuntimeException(
                                'Prompt tidak memuat informasi wajib: ' . $content
                            );
                        }
                    }

                    $reply = 'Laporan 2026-10-20.md mencatat HTTP 200 '
                        . 'dengan response time 1.520 detik.';

                    $process = new Process([
                        PHP_BINARY,
                        '-r',
                        'echo ' . escapeshellarg($reply) . ';',
                    ]);

                    $process->run();

                    return $process;
                }
            };

            $this->app->instance(HermesRunner::class, $fakeHermes);

            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/chatbot/send', [
                'message' => 'Analisis laporan tanggal 2026-10-20',
            ]);

            $response->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath(
                    'reply',
                    'Laporan 2026-10-20.md mencatat HTTP 200 '
                    . 'dengan response time 1.520 detik.'
                );

            $this->assertDatabaseHas('chat_messages', [
                'user_id' => $user->id,
                'role' => 'user',
                'message' => 'Analisis laporan tanggal 2026-10-20',
            ]);

            $this->assertDatabaseHas('chat_messages', [
                'user_id' => $user->id,
                'role' => 'assistant',
                'message' => $response->json('reply'),
            ]);
        } finally {
            if (file_exists($reportFile)) {
                unlink($reportFile);
            }

            if (is_dir($reportDirectory)) {
                rmdir($reportDirectory);
            }
        }
    }

    public function test_requested_date_excludes_reports_from_other_dates(): void
    {
        $reportDirectory = storage_path('framework/testing/daily-reports');
        if (! is_dir($reportDirectory)) {
            mkdir($reportDirectory, 0777, true);
        }

        $requestedFile = $reportDirectory . '/2026-10-20.md';
        $otherFile = $reportDirectory . '/2026-10-21.md';

        file_put_contents($requestedFile, 'ISI KHUSUS LAPORAN 20 OKTOBER');
        file_put_contents($otherFile, 'ISI RAHASIA LAPORAN 21 OKTOBER');

        try {
            config([
                'hosting.websites' => [
                    'Google' => [
                        'url' => 'https://example.test',
                        'file' => 'google.status',
                    ],
                ],
                'hosting.status_path' => storage_path('framework/testing/no-real-status'),
                'hosting.report_directory' => $reportDirectory,
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
                    if (! str_contains($prompt, 'ISI KHUSUS LAPORAN 20 OKTOBER')) {
                        throw new \RuntimeException('Laporan tanggal yang diminta tidak ditemukan dalam prompt.');
                    }

                    if (str_contains($prompt, 'ISI RAHASIA LAPORAN 21 OKTOBER')) {
                        throw new \RuntimeException('Laporan tanggal lain ikut masuk ke prompt.');
                    }

                    $process = new Process([
                        PHP_BINARY,
                        '-r',
                        'echo ' . escapeshellarg('Filter tanggal berhasil.') . ';',
                    ]);
                    $process->run();

                    return $process;
                }
            };

            $this->app->instance(HermesRunner::class, $fakeHermes);
            $user = User::factory()->create();

            $this->actingAs($user)->postJson('/chatbot/send', [
                'message' => 'Analisis laporan tanggal 2026-10-20',
            ])->assertOk()->assertJsonPath('success', true);
        } finally {
            if (file_exists($requestedFile)) {
                unlink($requestedFile);
            }
            if (file_exists($otherFile)) {
                unlink($otherFile);
            }
            if (is_dir($reportDirectory)) {
                rmdir($reportDirectory);
            }
        }
    }

    public function test_missing_requested_date_does_not_use_another_date_report(): void
    {
        $reportDirectory = storage_path('framework/testing/daily-reports');
        if (! is_dir($reportDirectory)) {
            mkdir($reportDirectory, 0777, true);
        }

        $otherFile = $reportDirectory . '/2026-10-20.md';
        file_put_contents($otherFile, 'ISI LAPORAN TANGGAL LAIN');

        try {
            config([
                'hosting.websites' => [
                    'Google' => [
                        'url' => 'https://example.test',
                        'file' => 'google.status',
                    ],
                ],
                'hosting.status_path' => storage_path('framework/testing/no-real-status'),
                'hosting.report_directory' => $reportDirectory,
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
                    if (! str_contains($prompt, 'Laporan untuk tanggal yang diminta (2026-10-21) tidak ditemukan')) {
                        throw new \RuntimeException('Pesan laporan tidak ditemukan tidak sesuai.');
                    }

                    if (str_contains($prompt, 'ISI LAPORAN TANGGAL LAIN')) {
                        throw new \RuntimeException('Laporan tanggal lain digunakan sebagai pengganti.');
                    }

                    $process = new Process([
                        PHP_BINARY,
                        '-r',
                        'echo ' . escapeshellarg('Laporan 2026-10-21 tidak ditemukan.') . ';',
                    ]);
                    $process->run();

                    return $process;
                }
            };

            $this->app->instance(HermesRunner::class, $fakeHermes);
            $user = User::factory()->create();

            $this->actingAs($user)->postJson('/chatbot/send', [
                'message' => 'Analisis laporan tanggal 2026-10-21',
            ])->assertOk()->assertJsonPath('success', true);
        } finally {
            if (file_exists($otherFile)) {
                unlink($otherFile);
            }
            if (is_dir($reportDirectory)) {
                rmdir($reportDirectory);
            }
        }
    }

    public function test_requested_date_matches_timestamp_report_filename(): void
    {
        $reportDirectory = storage_path('framework/testing/daily-reports');
        if (! is_dir($reportDirectory)) {
            mkdir($reportDirectory, 0777, true);
        }

        $reportFile = $reportDirectory . '/2026-10-20_19-12-55.md';
        file_put_contents($reportFile, 'LAPORAN TIMESTAMP KHUSUS 20 OKTOBER');

        try {
            config([
                'hosting.websites' => [
                    'Google' => [
                        'url' => 'https://example.test',
                        'file' => 'google.status',
                    ],
                ],
                'hosting.status_path' => storage_path('framework/testing/no-real-status'),
                'hosting.report_directory' => $reportDirectory,
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
                    if (! str_contains($prompt, '2026-10-20_19-12-55.md')) {
                        throw new \RuntimeException('File laporan timestamp tidak ditemukan dalam prompt.');
                    }

                    if (! str_contains($prompt, 'LAPORAN TIMESTAMP KHUSUS 20 OKTOBER')) {
                        throw new \RuntimeException('Isi laporan timestamp tidak ditemukan.');
                    }

                    $process = new Process([
                        PHP_BINARY,
                        '-r',
                        'echo ' . escapeshellarg('Laporan timestamp berhasil dibaca.') . ';',
                    ]);
                    $process->run();

                    return $process;
                }
            };

            $this->app->instance(HermesRunner::class, $fakeHermes);
            $user = User::factory()->create();

            $this->actingAs($user)->postJson('/chatbot/send', [
                'message' => 'Analisis laporan tanggal 2026-10-20',
            ])->assertOk()->assertJsonPath('success', true);
        } finally {
            if (file_exists($reportFile)) {
                unlink($reportFile);
            }
            if (is_dir($reportDirectory)) {
                rmdir($reportDirectory);
            }
        }
    }


    public function test_requested_date_reads_timestamp_report_from_riwayat_monitoring_directory(): void
    {
        $reportDirectory = storage_path('framework/testing/daily-reports');
        $historyDirectory = dirname($reportDirectory) . '/Riwayat Monitoring';

        if (! is_dir($reportDirectory)) {
            mkdir($reportDirectory, 0777, true);
        }

        if (! is_dir($historyDirectory)) {
            mkdir($historyDirectory, 0777, true);
        }

        $reportFile = $historyDirectory . '/2026-10-20_19-12-55.md';

        file_put_contents(
            $reportFile,
            'LAPORAN RIWAYAT TEST: Seven Inc HTTP 200 response time 1.52s.'
        );

        try {
            config([
                'hosting.websites' => [
                    'Google' => [
                        'url' => 'https://example.test',
                        'file' => 'google.status',
                    ],
                ],
                'hosting.status_path' => storage_path('framework/testing/no-real-status'),
                'hosting.report_directory' => $reportDirectory,
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
                    foreach ([
                        '2026-10-20_19-12-55.md',
                        'LAPORAN RIWAYAT TEST',
                        'Seven Inc HTTP 200',
                        'response time 1.52s',
                    ] as $required) {
                        if (! str_contains($prompt, $required)) {
                            throw new \RuntimeException(
                                'Prompt tidak memuat informasi laporan Riwayat Monitoring: ' . $required
                            );
                        }
                    }

                    $reply = 'Laporan Riwayat Monitoring berhasil dibaca.';

                    $process = new Process([
                        PHP_BINARY,
                        '-r',
                        'echo ' . escapeshellarg($reply) . ';',
                    ]);

                    $process->run();

                    return $process;
                }
            };

            $this->app->instance(HermesRunner::class, $fakeHermes);

            $user = User::factory()->create();

            $this->actingAs($user)->postJson('/chatbot/send', [
                'message' => 'Analisis laporan tanggal 2026-10-20',
            ])->assertOk()
                ->assertJsonPath(
                    'reply',
                    'Laporan Riwayat Monitoring berhasil dibaca.'
                );
        } finally {
            if (file_exists($reportFile)) {
                unlink($reportFile);
            }

            if (is_dir($historyDirectory)) {
                rmdir($historyDirectory);
            }

            if (is_dir($reportDirectory)) {
                rmdir($reportDirectory);
            }
        }
    }


    public function test_comparison_between_two_dates_uses_only_requested_reports(): void
    {
        $reportDirectory = storage_path('framework/testing/comparison-reports');
        $historyDirectory = dirname($reportDirectory) . '/Riwayat Monitoring';

        mkdir($reportDirectory, 0777, true);
        mkdir($historyDirectory, 0777, true);

        $report20 = $reportDirectory . '/2026-10-20_19-12-55.md';
        $report21 = $historyDirectory . '/2026-10-21_19-12-55.md';
        $report22 = $reportDirectory . '/2026-10-22_19-12-55.md';

        file_put_contents(
            $report20,
            'LAPORAN 20 OKTOBER: Seven Inc HTTP 200 response time 1.52s.'
        );

        file_put_contents(
            $report21,
            'LAPORAN 21 OKTOBER: Seven Inc HTTP 502 response time 4.30s.'
        );

        file_put_contents(
            $report22,
            'LAPORAN 22 OKTOBER TIDAK DIMINTA.'
        );

        try {
            config([
                'hosting.websites' => [
                    'Google' => [
                        'url' => 'https://example.test',
                        'file' => 'google.status',
                    ],
                ],
                'hosting.status_path' => storage_path('framework/testing/no-real-status'),
                'hosting.report_directory' => $reportDirectory,
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
                    $requiredContent = [
                        '2026-10-20',
                        '2026-10-21',
                        '2026-10-20_19-12-55.md',
                        '2026-10-21_19-12-55.md',
                        'LAPORAN 20 OKTOBER',
                        'Seven Inc HTTP 200',
                        '1.52s',
                        'LAPORAN 21 OKTOBER',
                        'Seven Inc HTTP 502',
                        '4.30s',
                    ];

                    foreach ($requiredContent as $required) {
                        if (! str_contains($prompt, $required)) {
                            throw new \RuntimeException(
                                'Prompt tidak memuat informasi perbandingan: ' . $required
                            );
                        }
                    }

                    if (str_contains($prompt, 'LAPORAN 22 OKTOBER TIDAK DIMINTA')) {
                        throw new \RuntimeException(
                            'Laporan tanggal 22 Oktober ikut masuk ke prompt.'
                        );
                    }

                    if (! str_contains(
                        $prompt,
                        'Jangan menyebut gangguan sebagai intermiten hanya berdasarkan satu kejadian timeout.'
                    )) {
                        throw new \RuntimeException(
                            'Aturan validasi gangguan berulang tidak ditemukan.'
                        );
                    }

                    $reply = 'Perbandingan dua laporan berhasil diproses.';

                    $process = new Process([
                        PHP_BINARY,
                        '-r',
                        'echo ' . escapeshellarg($reply) . ';',
                    ]);

                    $process->run();

                    return $process;
                }
            };

            $this->app->instance(HermesRunner::class, $fakeHermes);

            $user = User::factory()->create();

            $this->actingAs($user)->postJson('/chatbot/send', [
                'message' => 'Bandingkan laporan monitoring tanggal 2026-10-20 dan 2026-10-21.',
            ])->assertOk()
                ->assertJsonPath(
                    'reply',
                    'Perbandingan dua laporan berhasil diproses.'
                );
        } finally {
            foreach ([$report20, $report21, $report22] as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }

            if (is_dir($historyDirectory)) {
                rmdir($historyDirectory);
            }

            if (is_dir($reportDirectory)) {
                rmdir($reportDirectory);
            }
        }
    }

}
