<?php

namespace App\Services;

use App\Models\MigrationEndpoint;
use RuntimeException;

class MigrationRemoteFileBrowser
{
    public function __construct(
        private MigrationConnectionService $connectionService
    ) {
    }

    public function listDirectory(
        MigrationEndpoint $endpoint,
        string $path = '.'
    ): array {
        if ($endpoint->connection_type !== 'sftp') {
            throw new RuntimeException(
                'Penjelajahan direktori hanya tersedia untuk koneksi SFTP.'
            );
        }

        $path = $this->normalizePath($path);
        $session = $this->connectionService->openSession($endpoint);

        try {
            $files = $session->sftp()->rawlist($path, false);

            if (! is_array($files)) {
                throw new RuntimeException(
                    'Direktori tidak dapat dibaca. Periksa path dan izin akses akun SFTP.'
                );
            }

            $entries = [];

            foreach ($files as $name => $attributes) {
                if (
                    ! is_string($name) ||
                    $name === '.' ||
                    $name === '..' ||
                    ! is_array($attributes)
                ) {
                    continue;
                }

                $type = (int) ($attributes['type'] ?? 5);

                $entries[] = [
                    'name' => $name,
                    'type' => match ($type) {
                        1 => 'file',
                        2 => 'directory',
                        3 => 'symlink',
                        default => 'unknown',
                    },
                    'size' => max(0, (int) ($attributes['size'] ?? 0)),
                    'modified_at' => isset($attributes['mtime'])
                        ? (int) $attributes['mtime']
                        : null,
                    'permissions' => isset($attributes['permissions'])
                        ? sprintf('%04o', $attributes['permissions'] & 07777)
                        : null,
                ];
            }

            usort($entries, function (array $a, array $b): int {
                $aDirectory = $a['type'] === 'directory';
                $bDirectory = $b['type'] === 'directory';

                if ($aDirectory !== $bDirectory) {
                    return $bDirectory <=> $aDirectory;
                }

                return strcasecmp($a['name'], $b['name']);
            });

            return [
                'path' => $path,
                'entries' => array_slice($entries, 0, 500),
                'total' => count($entries),
                'truncated' => count($entries) > 500,
            ];
        } finally {
            $session->close();
        }
    }

    private function normalizePath(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new RuntimeException('Path direktori tidak valid.');
        }

        $path = trim($path);

        if (
            $path === '' ||
            strlen($path) > 1024 ||
            str_contains($path, '\\')
        ) {
            throw new RuntimeException('Path direktori tidak valid.');
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '..') {
                throw new RuntimeException(
                    'Akses ke direktori induk menggunakan ".." tidak diizinkan.'
                );
            }
        }

        $normalized = implode(
            '/',
            array_filter(
                $segments,
                fn (string $segment): bool => $segment !== '' && $segment !== '.'
            )
        );

        if (str_starts_with($path, '/')) {
            return $normalized === '' ? '/' : '/'.$normalized;
        }

        return $normalized === '' ? '.' : $normalized;
    }
}
