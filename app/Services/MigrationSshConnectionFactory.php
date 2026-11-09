<?php

namespace App\Services;

use App\Models\MigrationEndpoint;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use RuntimeException;

class MigrationSshConnectionFactory
{
    public function create(
        MigrationEndpoint $endpoint,
        string $address
    ): SSH2 {
        $port = (int) ($endpoint->port ?: 22);

        if (
            filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false ||
            $port < 1 ||
            $port > 65535
        ) {
            throw new RuntimeException('Alamat koneksi tidak valid.');
        }

        $socketAddress = str_contains($address, ':')
            ? '['.$address.']'
            : $address;

        $errno = 0;
        $error = '';

        $socket = @stream_socket_client(
            'tcp://'.$socketAddress.':'.$port,
            $errno,
            $error,
            10,
            STREAM_CLIENT_CONNECT
        );

        if (! is_resource($socket)) {
            throw new RuntimeException('Gagal membuka koneksi SSH/SFTP.');
        }

        stream_set_timeout($socket, 10);

        try {
            if ($endpoint->connection_type === 'sftp') {
                return new SFTP($socket, $port, 10);
            }

            return new SSH2($socket, $port, 10);
        } catch (\Throwable $exception) {
            fclose($socket);

            throw $exception;
        }
    }
}
