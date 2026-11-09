<?php

namespace App\Services;

use LogicException;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

class MigrationRemoteSession
{
    private bool $closed = false;

    public function __construct(
        private SSH2 $connection
    ) {
    }

    public function connection(): SSH2
    {
        if ($this->closed) {
            throw new LogicException('Sesi koneksi sudah ditutup.');
        }

        return $this->connection;
    }

    public function sftp(): SFTP
    {
        $connection = $this->connection();

        if (! $connection instanceof SFTP) {
            throw new LogicException('Sesi ini bukan koneksi SFTP.');
        }

        return $connection;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->connection->disconnect();
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function __destruct()
    {
        $this->close();
    }
}
