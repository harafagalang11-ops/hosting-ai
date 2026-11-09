<?php

namespace Tests\Unit;

use App\Models\MigrationEndpoint;
use App\Services\MigrationConnectionService;
use App\Services\MigrationSshConnectionFactory;
use Tests\TestCase;
use ReflectionMethod;
use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;

class MigrationConnectionServiceFingerprintTest extends TestCase
{
    public function test_connection_is_rejected_when_host_fingerprint_is_empty(): void
    {
        $endpoint = new MigrationEndpoint([
            'host' => 'hosting.example.test',
            'port' => 22,
            'connection_type' => 'sftp',
            'username' => 'test-user',
            'credential' => 'secret-password',
            'host_key_fingerprint' => null,
        ]);

        $result = (new MigrationConnectionService(new MigrationSshConnectionFactory()))->test($endpoint);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString(
            'Fingerprint host belum diatur',
            $result['message']
        );
    }

    public function test_fingerprint_calculation_uses_sha256_of_public_key_blob(): void
    {
        $service = new MigrationConnectionService(new MigrationSshConnectionFactory());

        $method = new ReflectionMethod(
            MigrationConnectionService::class,
            'calculateFingerprint'
        );

        $keyBlob = 'sample-ssh-public-key-blob';
        $publicHostKey = 'ssh-ed25519 '.base64_encode($keyBlob);

        $expected = 'SHA256:'.rtrim(
            base64_encode(hash('sha256', $keyBlob, true)),
            '='
        );

        $actual = $method->invoke($service, $publicHostKey);

        $this->assertSame($expected, $actual);
        $this->assertMatchesRegularExpression(
            '/^SHA256:[A-Za-z0-9+\/]{43}$/',
            $actual
        );
    }

    public function test_login_is_never_called_when_host_fingerprint_does_not_match(): void
    {
        $endpoint = new MigrationEndpoint([
            'host' => '8.8.8.8',
            'port' => 22,
            'connection_type' => 'ssh',
            'username' => 'test-user',
            'credential' => 'secret-password',
            'host_key_fingerprint' => 'SHA256:'.str_repeat('A', 43),
        ]);

        $publicHostKey = 'ssh-ed25519 '.base64_encode('fake-public-key-blob');

        $connection = \Mockery::mock(SSH2::class);

        $connection->shouldReceive('getServerPublicHostKey')
            ->once()
            ->andReturn($publicHostKey);

        $connection->shouldReceive('login')
            ->never();

        $connection->shouldReceive('disconnect')
            ->once();

        $factory = \Mockery::mock(MigrationSshConnectionFactory::class);

        $factory->shouldReceive('create')
            ->once()
            ->with($endpoint, '8.8.8.8')
            ->andReturn($connection);

        $result = (new MigrationConnectionService($factory))->test($endpoint);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString(
            'Fingerprint host tidak cocok',
            $result['message']
        );
    }


    public function test_login_is_called_only_after_host_fingerprint_matches(): void
    {
        $keyBlob = 'valid-fake-public-key-blob';

        $publicHostKey = 'ssh-ed25519 '.base64_encode($keyBlob);

        $fingerprint = 'SHA256:'.rtrim(
            base64_encode(hash('sha256', $keyBlob, true)),
            '='
        );

        $endpoint = new MigrationEndpoint([
            'host' => '8.8.8.8',
            'port' => 22,
            'connection_type' => 'ssh',
            'username' => 'test-user',
            'credential' => 'secret-password',
            'host_key_fingerprint' => $fingerprint,
        ]);

        $connection = \Mockery::mock(SSH2::class);

        $connection->shouldReceive('getServerPublicHostKey')
            ->once()
            ->andReturn($publicHostKey);

        $connection->shouldReceive('login')
            ->once()
            ->with('test-user', 'secret-password')
            ->andReturn(true);

        $connection->shouldReceive('disconnect')
            ->once();

        $factory = \Mockery::mock(MigrationSshConnectionFactory::class);

        $factory->shouldReceive('create')
            ->once()
            ->with($endpoint, '8.8.8.8')
            ->andReturn($connection);

        $result = (new MigrationConnectionService($factory))->test($endpoint);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString(
            'Fingerprint host dan autentikasi terverifikasi',
            $result['message']
        );
    }



    public function test_sftp_connection_fails_when_sftp_subsystem_is_unavailable(): void
    {
        $keyBlob = 'valid-sftp-public-key-blob';
        $publicHostKey = 'ssh-ed25519 '.base64_encode($keyBlob);
        $fingerprint = 'SHA256:'.rtrim(
            base64_encode(hash('sha256', $keyBlob, true)),
            '='
        );

        $endpoint = new MigrationEndpoint([
            'host' => '8.8.8.8',
            'port' => 22,
            'connection_type' => 'sftp',
            'username' => 'test-user',
            'credential' => 'secret-password',
            'host_key_fingerprint' => $fingerprint,
        ]);

        $connection = \Mockery::mock(SFTP::class);
        $connection->shouldReceive('getServerPublicHostKey')
            ->once()
            ->andReturn($publicHostKey);
        $connection->shouldReceive('login')
            ->once()
            ->with('test-user', 'secret-password')
            ->andReturn(true);
        $connection->shouldReceive('pwd')
            ->once()
            ->andReturn(false);
        $connection->shouldReceive('disconnect')
            ->once();

        $factory = \Mockery::mock(MigrationSshConnectionFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->with($endpoint, '8.8.8.8')
            ->andReturn($connection);

        $result = (new MigrationConnectionService($factory))->test($endpoint);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString(
            'subsistem SFTP tidak dapat digunakan',
            $result['message']
        );
    }

    public function test_sftp_connection_succeeds_when_sftp_subsystem_is_available(): void
    {
        $keyBlob = 'valid-sftp-public-key-blob';
        $publicHostKey = 'ssh-ed25519 '.base64_encode($keyBlob);
        $fingerprint = 'SHA256:'.rtrim(
            base64_encode(hash('sha256', $keyBlob, true)),
            '='
        );

        $endpoint = new MigrationEndpoint([
            'host' => '8.8.8.8',
            'port' => 22,
            'connection_type' => 'sftp',
            'username' => 'test-user',
            'credential' => 'secret-password',
            'host_key_fingerprint' => $fingerprint,
        ]);

        $connection = \Mockery::mock(SFTP::class);
        $connection->shouldReceive('getServerPublicHostKey')
            ->once()
            ->andReturn($publicHostKey);
        $connection->shouldReceive('login')
            ->once()
            ->with('test-user', 'secret-password')
            ->andReturn(true);
        $connection->shouldReceive('pwd')
            ->once()
            ->andReturn('/home/test-user');
        $connection->shouldReceive('disconnect')
            ->once();

        $factory = \Mockery::mock(MigrationSshConnectionFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->with($endpoint, '8.8.8.8')
            ->andReturn($connection);

        $result = (new MigrationConnectionService($factory))->test($endpoint);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString(
            'Koneksi SFTP berhasil',
            $result['message']
        );
    }

}
