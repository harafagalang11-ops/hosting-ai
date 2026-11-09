<?php

namespace Tests\Unit;

use App\Models\MigrationEndpoint;
use App\Services\MigrationConnectionService;
use App\Services\MigrationSshConnectionFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MigrationConnectionServiceCpanelApiTest extends TestCase
{
    private function makeEndpoint(array $attributes = []): MigrationEndpoint
    {
        return new MigrationEndpoint(array_merge([
            'host' => '8.8.8.8',
            'port' => 2083,
            'connection_type' => 'cpanel_api',
            'username' => 'test-user',
            'credential' => 'test-api-token',
        ], $attributes));
    }

    public function test_cpanel_api_connection_succeeds_with_valid_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 1,
                'data' => [
                    'version' => '128.0.5',
                ],
            ], 200),
        ]);

        $result = (new MigrationConnectionService(
            new MigrationSshConnectionFactory()
        ))->test($this->makeEndpoint());

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('128.0.5', $result['message']);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://8.8.8.8:2083/execute/Version/get_version'
                && $request->hasHeader(
                    'Authorization',
                    'cpanel test-user:test-api-token'
                );
        });
    }

    public function test_cpanel_api_rejects_invalid_token_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 0,
                'errors' => ['Invalid API token'],
            ], 200),
        ]);

        $result = (new MigrationConnectionService(
            new MigrationSshConnectionFactory()
        ))->test($this->makeEndpoint());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Autentikasi', $result['message']);
        $this->assertStringNotContainsString('test-api-token', $result['message']);
    }

    public function test_cpanel_api_rejects_private_ip_without_sending_request(): void
    {
        Http::fake();

        $result = (new MigrationConnectionService(
            new MigrationSshConnectionFactory()
        ))->test($this->makeEndpoint([
            'host' => '127.0.0.1',
        ]));

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('IP privat', $result['message']);

        Http::assertNothingSent();
    }

    public function test_cpanel_api_requires_username_and_token(): void
    {
        Http::fake();

        $result = (new MigrationConnectionService(
            new MigrationSshConnectionFactory()
        ))->test($this->makeEndpoint([
            'credential' => '',
        ]));

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('API token wajib diisi', $result['message']);

        Http::assertNothingSent();
    }
}
