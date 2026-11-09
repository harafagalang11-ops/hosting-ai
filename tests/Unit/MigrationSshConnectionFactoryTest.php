<?php

namespace Tests\Unit;

use App\Models\MigrationEndpoint;
use App\Services\MigrationSshConnectionFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MigrationSshConnectionFactoryTest extends TestCase
{
    public function test_factory_rejects_private_ip_before_opening_socket(): void
    {
        $endpoint = new MigrationEndpoint([
            'port' => 22,
            'connection_type' => 'ssh',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Alamat koneksi tidak valid.');

        (new MigrationSshConnectionFactory())->create(
            $endpoint,
            '127.0.0.1'
        );
    }

    public function test_factory_rejects_invalid_port_before_opening_socket(): void
    {
        $endpoint = new MigrationEndpoint([
            'port' => 70000,
            'connection_type' => 'ssh',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Alamat koneksi tidak valid.');

        (new MigrationSshConnectionFactory())->create(
            $endpoint,
            '8.8.8.8'
        );
    }

    public function test_factory_rejects_invalid_ip_before_opening_socket(): void
    {
        $endpoint = new MigrationEndpoint([
            'port' => 22,
            'connection_type' => 'ssh',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Alamat koneksi tidak valid.');

        (new MigrationSshConnectionFactory())->create(
            $endpoint,
            'not-an-ip-address'
        );
    }
}
