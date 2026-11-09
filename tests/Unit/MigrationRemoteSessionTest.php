<?php

namespace Tests\Unit;

use App\Services\MigrationRemoteSession;
use LogicException;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use PHPUnit\Framework\TestCase;

class MigrationRemoteSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Database\Eloquent\Model::encryptUsing(
            new \Illuminate\Encryption\Encrypter(
                str_repeat('a', 32),
                'AES-256-CBC'
            )
        );
    }

    public function test_session_keeps_connection_until_closed(): void
    {
        $connection = \Mockery::mock(SSH2::class);
        $connection->shouldReceive('disconnect')->once();

        $session = new MigrationRemoteSession($connection);

        $this->assertSame($connection, $session->connection());
        $this->assertFalse($session->isClosed());

        $session->close();

        $this->assertTrue($session->isClosed());
    }

    public function test_close_can_be_called_more_than_once(): void
    {
        $connection = \Mockery::mock(SSH2::class);
        $connection->shouldReceive('disconnect')->once();

        $session = new MigrationRemoteSession($connection);

        $session->close();
        $session->close();

        $this->assertTrue($session->isClosed());
    }

    public function test_closed_session_cannot_be_reused(): void
    {
        $connection = \Mockery::mock(SSH2::class);
        $connection->shouldReceive('disconnect')->once();

        $session = new MigrationRemoteSession($connection);
        $session->close();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Sesi koneksi sudah ditutup.');

        $session->connection();
    }

    public function test_sftp_accessor_returns_sftp_connection(): void
    {
        $connection = \Mockery::mock(SFTP::class);
        $connection->shouldReceive('disconnect')->once();

        $session = new MigrationRemoteSession($connection);

        $this->assertSame($connection, $session->sftp());

        $session->close();
    }

    public function test_ssh_session_rejects_sftp_accessor(): void
    {
        $connection = \Mockery::mock(SSH2::class);
        $connection->shouldReceive('disconnect')->once();

        $session = new MigrationRemoteSession($connection);

        try {
            $session->sftp();
            $this->fail('Seharusnya sesi SSH tidak menyediakan akses SFTP.');
        } catch (LogicException $exception) {
            $this->assertSame(
                'Sesi ini bukan koneksi SFTP.',
                $exception->getMessage()
            );
        } finally {
            $session->close();
        }
    }

    public function test_open_session_authenticates_before_returning_session(): void
    {
        $keyBlob = 'open-session-valid-key';
        $fingerprint = 'SHA256:'.rtrim(
            base64_encode(hash('sha256', $keyBlob, true)),
            '='
        );

        $endpoint = new \App\Models\MigrationEndpoint([
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
            ->andReturn('ssh-ed25519 '.base64_encode($keyBlob));
        $connection->shouldReceive('login')
            ->once()
            ->with('test-user', 'secret-password')
            ->andReturn(true);
        $connection->shouldReceive('disconnect')->once();

        $factory = \Mockery::mock(\App\Services\MigrationSshConnectionFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->with($endpoint, '8.8.8.8')
            ->andReturn($connection);

        $session = (new \App\Services\MigrationConnectionService($factory))
            ->openSession($endpoint);

        $this->assertInstanceOf(MigrationRemoteSession::class, $session);
        $this->assertFalse($session->isClosed());

        $session->close();
    }

    public function test_open_session_rejects_wrong_fingerprint_before_login(): void
    {
        $endpoint = new \App\Models\MigrationEndpoint([
            'host' => '8.8.8.8',
            'port' => 22,
            'connection_type' => 'ssh',
            'username' => 'test-user',
            'credential' => 'secret-password',
            'host_key_fingerprint' => 'SHA256:'.str_repeat('A', 43),
        ]);

        $connection = \Mockery::mock(SSH2::class);
        $connection->shouldReceive('getServerPublicHostKey')
            ->once()
            ->andReturn('ssh-ed25519 '.base64_encode('different-key'));
        $connection->shouldReceive('login')->never();
        $connection->shouldReceive('disconnect')->once();

        $factory = \Mockery::mock(\App\Services\MigrationSshConnectionFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->andReturn($connection);

        try {
            (new \App\Services\MigrationConnectionService($factory))
                ->openSession($endpoint);
            $this->fail('Fingerprint yang tidak cocok seharusnya ditolak.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString(
                'Fingerprint host tidak cocok',
                $exception->getMessage()
            );
        }
    }

    public function test_open_session_disconnects_when_login_fails(): void
    {
        $keyBlob = 'open-session-login-failure-key';
        $fingerprint = 'SHA256:'.rtrim(
            base64_encode(hash('sha256', $keyBlob, true)),
            '='
        );

        $endpoint = new \App\Models\MigrationEndpoint([
            'host' => '8.8.8.8',
            'port' => 22,
            'connection_type' => 'ssh',
            'username' => 'test-user',
            'credential' => 'wrong-password',
            'host_key_fingerprint' => $fingerprint,
        ]);

        $connection = \Mockery::mock(SSH2::class);
        $connection->shouldReceive('getServerPublicHostKey')
            ->once()
            ->andReturn('ssh-ed25519 '.base64_encode($keyBlob));
        $connection->shouldReceive('login')
            ->once()
            ->andReturn(false);
        $connection->shouldReceive('disconnect')->once();

        $factory = \Mockery::mock(\App\Services\MigrationSshConnectionFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->andReturn($connection);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Autentikasi SSH/SFTP gagal.');

        (new \App\Services\MigrationConnectionService($factory))
            ->openSession($endpoint);
    }

}
