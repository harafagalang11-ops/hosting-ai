<?php

namespace Tests\Unit;

use App\Models\MigrationEndpoint;
use App\Services\MigrationConnectionService;
use App\Services\MigrationRemoteFileBrowser;
use App\Services\MigrationRemoteSession;
use LogicException;
use Mockery;
use phpseclib3\Net\SFTP;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class MigrationRemoteFileBrowserTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    public function test_lists_remote_directory_and_sorts_directories_first(): void
    {
        $sftp = Mockery::mock(SFTP::class);

        $sftp->shouldReceive('rawlist')
            ->once()
            ->with('.', false)
            ->andReturn([
                'z-file.txt' => [
                    'type' => 1,
                    'size' => 120,
                    'mtime' => 1700000000,
                    'permissions' => 0644,
                ],
                'folder' => [
                    'type' => 2,
                    'size' => 0,
                    'mtime' => 1700000100,
                    'permissions' => 0755,
                ],
                'a-file.txt' => [
                    'type' => 1,
                    'size' => 50,
                ],
                'link' => [
                    'type' => 3,
                    'size' => 0,
                ],
                '.' => ['type' => 2],
                '..' => ['type' => 2],
            ]);

        $sftp->shouldReceive('disconnect')->once();

        $session = new MigrationRemoteSession($sftp);

        $connectionService = Mockery::mock(MigrationConnectionService::class);
        $connectionService->shouldReceive('openSession')
            ->once()
            ->andReturn($session);

        $endpoint = new MigrationEndpoint([
            'connection_type' => 'sftp',
        ]);

        $result = (new MigrationRemoteFileBrowser($connectionService))
            ->listDirectory($endpoint);

        $this->assertSame('.', $result['path']);
        $this->assertSame(4, $result['total']);
        $this->assertFalse($result['truncated']);

        $this->assertSame(
            ['folder', 'a-file.txt', 'link', 'z-file.txt'],
            array_column($result['entries'], 'name')
        );

        $this->assertSame('directory', $result['entries'][0]['type']);
        $this->assertSame('0755', $result['entries'][0]['permissions']);
        $this->assertSame(120, $result['entries'][3]['size']);
    }

    public function test_normalizes_directory_path_before_listing(): void
    {
        $sftp = Mockery::mock(SFTP::class);

        $sftp->shouldReceive('rawlist')
            ->once()
            ->with('public_html/assets', false)
            ->andReturn([]);

        $sftp->shouldReceive('disconnect')->once();

        $session = new MigrationRemoteSession($sftp);

        $connectionService = Mockery::mock(MigrationConnectionService::class);
        $connectionService->shouldReceive('openSession')
            ->once()
            ->andReturn($session);

        $endpoint = new MigrationEndpoint([
            'connection_type' => 'sftp',
        ]);

        $result = (new MigrationRemoteFileBrowser($connectionService))
            ->listDirectory($endpoint, './public_html//assets/');

        $this->assertSame('public_html/assets', $result['path']);
        $this->assertSame([], $result['entries']);
    }

    public function test_rejects_parent_directory_traversal(): void
    {
        $connectionService = Mockery::mock(MigrationConnectionService::class);
        $connectionService->shouldNotReceive('openSession');

        $endpoint = new MigrationEndpoint([
            'connection_type' => 'sftp',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Akses ke direktori induk');

        (new MigrationRemoteFileBrowser($connectionService))
            ->listDirectory($endpoint, 'public_html/../private');
    }

    public function test_rejects_invalid_directory_path(): void
    {
        $connectionService = Mockery::mock(MigrationConnectionService::class);
        $connectionService->shouldNotReceive('openSession');

        $endpoint = new MigrationEndpoint([
            'connection_type' => 'sftp',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Path direktori tidak valid.');

        (new MigrationRemoteFileBrowser($connectionService))
            ->listDirectory($endpoint, "public_html\0");
    }

    public function test_rejects_non_sftp_endpoint(): void
    {
        $connectionService = Mockery::mock(MigrationConnectionService::class);
        $connectionService->shouldNotReceive('openSession');

        $endpoint = new MigrationEndpoint([
            'connection_type' => 'ssh',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Penjelajahan direktori hanya tersedia untuk koneksi SFTP.'
        );

        (new MigrationRemoteFileBrowser($connectionService))
            ->listDirectory($endpoint);
    }

    public function test_closes_session_when_directory_listing_fails(): void
    {
        $sftp = Mockery::mock(SFTP::class);

        $sftp->shouldReceive('rawlist')
            ->once()
            ->with('.', false)
            ->andReturn(false);

        $sftp->shouldReceive('disconnect')->once();

        $session = new MigrationRemoteSession($sftp);

        $connectionService = Mockery::mock(MigrationConnectionService::class);
        $connectionService->shouldReceive('openSession')
            ->once()
            ->andReturn($session);

        $endpoint = new MigrationEndpoint([
            'connection_type' => 'sftp',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Direktori tidak dapat dibaca.');

        (new MigrationRemoteFileBrowser($connectionService))
            ->listDirectory($endpoint);
    }

    public function test_limits_directory_listing_to_500_entries(): void
    {
        $files = [];

        for ($i = 1; $i <= 501; $i++) {
            $files["file-{$i}.txt"] = [
                'type' => 1,
                'size' => 10,
            ];
        }

        $sftp = Mockery::mock(SFTP::class);
        $sftp->shouldReceive('rawlist')
            ->once()
            ->with('.', false)
            ->andReturn($files);

        $sftp->shouldReceive('disconnect')->once();

        $session = new MigrationRemoteSession($sftp);

        $connectionService = Mockery::mock(MigrationConnectionService::class);
        $connectionService->shouldReceive('openSession')
            ->once()
            ->andReturn($session);

        $endpoint = new MigrationEndpoint([
            'connection_type' => 'sftp',
        ]);

        $result = (new MigrationRemoteFileBrowser($connectionService))
            ->listDirectory($endpoint);

        $this->assertSame(501, $result['total']);
        $this->assertCount(500, $result['entries']);
        $this->assertTrue($result['truncated']);
    }
}
