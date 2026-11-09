<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use ZipArchive;
use PDO;

class LocalhostMigrationService
{
    public function migrate(array $context = []): array
    {
        return $this->withMigrationLock(
            fn (): array => $this->migrateLocked($context)
        );
    }

    private function migrateLocked(array $context = []): array
    {
        $lab = storage_path('app/private/migration-lab');
        $this->assertSafeDirectoryPath($lab);
        $source = $lab . '/source';
        $target = $lab . '/target';

        if (is_link($source) || is_link($target)) {
            throw new RuntimeException('Symbolic link tidak diizinkan.');
        }

        if (! is_dir($source) || ! is_dir($target)) {
            throw new RuntimeException('Folder sumber atau tujuan tidak ditemukan.');
        }

        if (
            ! is_file($source . '/index.php') ||
            ! is_file($source . '/database.sqlite')
        ) {
            throw new RuntimeException('Website sumber tidak lengkap.');
        }

        $id = now()->format('Ymd_His') . '_' . Str::random(6);
        $backupDirectory = storage_path('app/private/migration-backups');
        $this->assertSafeDirectoryPath($backupDirectory, false);

        if (! is_dir($backupDirectory) &&
            ! mkdir($backupDirectory, 0750, true)) {
            throw new RuntimeException('Folder backup gagal dibuat.');
        }

        $backupPath = $backupDirectory . '/migration_' . $id . '.zip';
        $stage = $lab . '/.migration_' . Str::random(12);
        $previous = $lab . '/.migration_previous_' . Str::random(12);
        $failed = $lab . '/.migration_failed_' . $id;

        $zip = new ZipArchive();
        $zipClosed = false;
        $targetMoved = false;

        if ($zip->open($backupPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Arsip backup gagal dibuat.');
        }

        try {
            $this->addDirectoryToZip($zip, $source, 'source');
            $this->addDirectoryToZip($zip, $target, 'target-before-migration');

            if (! $zip->close()) {
                throw new RuntimeException('Backup gagal disimpan.');
            }

            $zipClosed = true;

            $this->copyDirectory($source, $stage);

            $database = new PDO('sqlite:' . $stage . '/database.sqlite');
            $integrity = $database->query('PRAGMA integrity_check')->fetchColumn();
            $database = null;

            if ($integrity !== 'ok') {
                throw new RuntimeException('Integritas database hasil salinan gagal.');
            }

            if (! is_file($stage . '/index.php')) {
                throw new RuntimeException('File website hasil salinan tidak ditemukan.');
            }

            if (! rename($target, $previous)) {
                throw new RuntimeException('Folder tujuan lama gagal diamankan.');
            }

            $targetMoved = true;

            if (! rename($stage, $target)) {
                throw new RuntimeException('Hasil migrasi gagal dipindahkan.');
            }

            // Verifikasi website tujuan setelah pemindahan.
            $response = Http::timeout(10)->get('http' . ':' . '/' . '/' . '127.0.0.1:8082/');

            if ($response->status() !== 200) {
                throw new RuntimeException(
                    'Verifikasi website tujuan gagal. HTTP ' . $response->status()
                );
            }

            if (! str_contains($response->body(), 'Website Localhost 1')) {
                throw new RuntimeException(
                    'Konten website tujuan tidak sesuai dengan website sumber.'
                );
            }

            $fileCount = 0;
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile()) {
                    $fileCount++;
                }
            }

            // Folder lama dibersihkan setelah verifikasi berhasil.
            // Kegagalan pembersihan tidak boleh memicu rollback migrasi.
            $cleanupWarning = null;

            try {
                $this->deleteDirectory($previous, $lab);
            } catch (\Throwable $cleanupException) {
                $cleanupWarning = $cleanupException->getMessage();
            }

            return [
                'backup' => basename($backupPath),
                'files' => $fileCount,
                'database' => $integrity,
                'http_status' => $response->status(),
                'verification' => 'Berhasil',
                'cleanup_warning' => $cleanupWarning,
                'source' => '127.0.0.1:8081',
                'target' => '127.0.0.1:8082',
                'selection' => $context,
            ];
        } catch (\Throwable $exception) {
            if (! $zipClosed) {
                try {
                    $zip->close();
                } catch (\Throwable $closeException) {
                    // Pertahankan exception utama.
                }
            }

            // Pembersihan staging tidak boleh menghalangi proses rollback.
            if (is_dir($stage) || is_link($stage)) {
                try {
                    $this->deleteDirectory($stage, $lab);
                } catch (\Throwable $cleanupException) {
                    // Folder staging yang gagal dibersihkan dibiarkan untuk pemeriksaan.
                }
            }

            // Pulihkan folder tujuan lama terlebih dahulu.
            if ($targetMoved && is_dir($previous) && ! is_link($previous)) {
                if (is_dir($target) || is_link($target)) {
                    try {
                        $targetSecured = rename($target, $failed);
                    } catch (\Throwable $renameException) {
                        throw new RuntimeException(
                            'Migrasi gagal dan folder tujuan baru tidak dapat diamankan. Folder lama masih tersedia di: ' .
                            $previous . '. Backup: ' . basename($backupPath),
                            0,
                            $exception
                        );
                    }

                    if (! $targetSecured) {
                        throw new RuntimeException(
                            'Migrasi gagal dan folder tujuan baru tidak dapat diamankan. Folder lama masih tersedia di: ' .
                            $previous . '. Backup: ' . basename($backupPath),
                            0,
                            $exception
                        );
                    }
                }

                try {
                    $previousRestored = rename($previous, $target);
                } catch (\Throwable $renameException) {
                    throw new RuntimeException(
                        'Migrasi gagal dan pemulihan folder tujuan juga gagal. Folder lama masih tersedia di: ' .
                        $previous . '. Folder hasil migrasi: ' . $failed .
                        '. Backup: ' . basename($backupPath),
                        0,
                        $exception
                    );
                }

                if (! $previousRestored) {
                    throw new RuntimeException(
                        'Migrasi gagal dan pemulihan folder tujuan juga gagal. Folder lama masih tersedia di: ' .
                        $previous . '. Folder hasil migrasi: ' . $failed .
                        '. Backup: ' . basename($backupPath),
                        0,
                        $exception
                    );
                }

                // Pembersihan folder gagal dilakukan setelah folder lama pulih.
                if (is_dir($failed) || is_link($failed)) {
                    try {
                        $this->deleteDirectory($failed, $lab);
                    } catch (\Throwable $cleanupException) {
                        // Folder gagal dibiarkan agar tidak mengganggu hasil rollback.
                    }
                }
            }

            // Backup tetap disimpan meskipun migrasi gagal.
            throw $exception;
        }
    }

    public function restore(string $backupFile): array
    {
        return $this->withMigrationLock(
            fn (): array => $this->restoreLocked($backupFile)
        );
    }

    private function restoreLocked(string $backupFile): array
    {
        if (
            ! preg_match('/\Amigration_[A-Za-z0-9_]+\.zip\z/', $backupFile)
        ) {
            throw new RuntimeException('Nama file backup tidak valid.');
        }

        $lab = storage_path('app/private/migration-lab');
        $target = $lab . '/target';
        $backupDirectory = storage_path('app/private/migration-backups');
        $this->assertSafeDirectoryPath($lab);
        $this->assertSafeDirectoryPath($backupDirectory);
        $backupPath = $backupDirectory . '/' . $backupFile;

        if (is_link($target) || ! is_dir($target)) {
            throw new RuntimeException('Folder tujuan tidak valid.');
        }

        if (! is_file($backupPath) || is_link($backupPath)) {
            throw new RuntimeException('File backup tidak ditemukan.');
        }

        $zip = new ZipArchive();

        if ($zip->open($backupPath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Arsip backup tidak dapat dibuka.');
        }

        $stage = $lab . '/.restore_' . Str::random(12);
        $previous = $lab . '/.restore_previous_' . Str::random(12);
        $failed = $lab . '/.restore_failed_' . Str::random(12);
        $emergencyBackup = $backupDirectory . '/before_restore_' .
            now()->format('Ymd_His') . '_' . Str::random(6) . '.zip';

        $targetMoved = false;
        $zipClosed = false;
        $emergencyZip = null;
        $emergencyZipOpen = false;
        $emergencyBackupComplete = false;

        try {
            $archiveInfo = $this->validateRestoreArchive($zip);
            $prefix = $archiveInfo['prefix'];
            $entrySizes = $archiveInfo['entry_sizes'];
            $maxEntryBytes = $archiveInfo['max_entry_bytes'];
            $maxTotalBytes = $archiveInfo['max_total_bytes'];

            if (! mkdir($stage, 0750, true)) {
                throw new RuntimeException('Folder staging restore gagal dibuat.');
            }

            $totalActualBytes = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);

                if ($entry === false || ! str_starts_with($entry, $prefix)) {
                    continue;
                }

                $relative = substr($entry, strlen($prefix));

                if (
                    $relative === '' ||
                    str_contains($relative, chr(92)) ||
                    str_contains($relative, chr(0)) ||
                    str_starts_with($relative, '/') ||
                    preg_match('/\A[A-Za-z]:/', $relative) ||
                    in_array('..', explode('/', $relative), true) ||
                    in_array('.', explode('/', $relative), true)
                ) {
                    throw new RuntimeException('Path dalam backup tidak aman.');
                }

                $destination = $stage . '/' . $relative;

                if (str_ends_with($entry, '/')) {
                    $this->ensureSafeRestoreDirectory($stage, $relative);
                    continue;
                }

                $stream = $zip->getStream($entry);

                if ($stream === false) {
                    throw new RuntimeException('File backup gagal dibaca.');
                }

                $parentRelative = dirname($relative);

                if ($parentRelative !== '.') {
                    $this->ensureSafeRestoreDirectory($stage, $parentRelative);
                }

                $output = fopen($destination, 'xb');

                if ($output === false) {
                    fclose($stream);
                    throw new RuntimeException('File restore gagal dibuat.');
                }

                try {
                    $fileBytes = 0;

                    while (! feof($stream)) {
                        $chunk = fread($stream, 8192);

                        if ($chunk === false) {
                            throw new RuntimeException('Pembacaan file restore gagal.');
                        }

                        if ($chunk === '') {
                            if (feof($stream)) {
                                break;
                            }

                            throw new RuntimeException('Stream restore berhenti sebelum waktunya.');
                        }

                        $chunkBytes = strlen($chunk);

                        if (
                            $chunkBytes > $maxEntryBytes - $fileBytes ||
                            $chunkBytes > $maxTotalBytes - $totalActualBytes
                        ) {
                            throw new RuntimeException('Ukuran aktual ekstraksi ZIP melebihi batas.');
                        }

                        $offset = 0;

                        while ($offset < $chunkBytes) {
                            $written = fwrite($output, substr($chunk, $offset));

                            if ($written === false || $written === 0) {
                                throw new RuntimeException('Penulisan file restore gagal.');
                            }

                            $offset += $written;
                        }

                        $fileBytes += $chunkBytes;
                        $totalActualBytes += $chunkBytes;
                    }

                    if (
                        ! array_key_exists($entry, $entrySizes) ||
                        $fileBytes !== $entrySizes[$entry]
                    ) {
                        throw new RuntimeException('Ukuran aktual file tidak sesuai metadata ZIP.');
                    }
                } finally {
                    fclose($stream);
                    fclose($output);
                }
            }

            if (! $zip->close()) {
                throw new RuntimeException('Arsip backup gagal ditutup.');
            }

            $zipClosed = true;
            $requiredFiles = [
                'target-before-migration/index.php',
                'target-before-migration/database.sqlite',
            ];


            foreach ($requiredFiles as $requiredFile) {
                $relative = substr($requiredFile, strlen($prefix));

                if (! is_file($stage . '/' . $relative)) {
                    throw new RuntimeException('File hasil ekstraksi tidak lengkap.');
                }
            }

            $database = new PDO('sqlite:' . $stage . '/database.sqlite');
            $integrity = $database->query('PRAGMA integrity_check')->fetchColumn();
            $database = null;

            if ($integrity !== 'ok') {
                throw new RuntimeException('Integritas database backup tidak valid.');
            }

            $emergencyZip = new ZipArchive();

            if (
                $emergencyZip->open(
                    $emergencyBackup,
                    ZipArchive::CREATE | ZipArchive::EXCL
                ) !== true
            ) {
                throw new RuntimeException('Backup darurat gagal dibuat.');
            }

            $emergencyZipOpen = true;

            $this->addDirectoryToZip(
                $emergencyZip,
                $target,
                'target-before-restore'
            );

            $emergencyZipClosed = $emergencyZip->close();

            if (! $emergencyZipClosed) {
                throw new RuntimeException('Backup darurat gagal disimpan.');
            }

            $emergencyZipOpen = false;

            $emergencyBackupComplete = true;

            if (! rename($target, $previous)) {
                throw new RuntimeException('Folder tujuan lama gagal diamankan.');
            }

            $targetMoved = true;

            if (! rename($stage, $target)) {
                throw new RuntimeException('Folder hasil restore gagal dipasang.');
            }

            $response = Http::timeout(10)->get('http' . ':' . '/' . '/' . '127.0.0.1:8082/');

            if (
                $response->status() !== 200 ||
                ! str_contains($response->body(), 'Website Localhost 1')
            ) {
                throw new RuntimeException('Verifikasi website setelah restore gagal.');
            }

            $cleanupWarning = null;

            try {
                $this->deleteDirectory($previous, $lab);
            } catch (\Throwable $cleanupException) {
                $cleanupWarning = $cleanupException->getMessage();
            }

            return [
                'backup' => $backupFile,
                'emergency_backup' => basename($emergencyBackup),
                'database' => $integrity,
                'http_status' => $response->status(),
                'verification' => 'Berhasil',
                'cleanup_warning' => $cleanupWarning,
            ];
                } catch (\Throwable $exception) {
                    if (! $zipClosed) {
                        try {
                            $zip->close();
                        } catch (\Throwable $closeException) {
                            // Pertahankan exception utama.
                        }
                    }
                    if ($emergencyZipOpen && $emergencyZip instanceof ZipArchive) {
                        try {
                            $emergencyZip->close();
                        } catch (\Throwable $closeException) {
                            // Pertahankan exception utama.
                        }
                        $emergencyZipOpen = false;
                    }
                    $emergencyBackupCleanupWarning = null;

                    if (! $emergencyBackupComplete && is_file($emergencyBackup)) {
                        try {
                            if (! unlink($emergencyBackup)) {
                                $emergencyBackupCleanupWarning =
                                    'File ZIP darurat parsial gagal dihapus: ' . $emergencyBackup;
                            }
                        } catch (\Throwable $cleanupException) {
                            $emergencyBackupCleanupWarning =
                                'File ZIP darurat parsial gagal dihapus: ' .
                                $emergencyBackup . '. ' . $cleanupException->getMessage();
                        }
                    }
                    if (is_dir($stage) || is_link($stage)) {
                        try {
                            $this->deleteDirectory($stage, $lab);
                        } catch (\Throwable $cleanupException) {
                            // Pembersihan staging tidak boleh menghalangi rollback.
                        }
                    }
                    if ($targetMoved) {
                        if (! is_dir($previous) || is_link($previous)) {
                            throw new RuntimeException(
                                'Restore gagal dan folder tujuan lama tidak tersedia untuk pemulihan. Backup darurat: ' . basename($emergencyBackup),
                                0,
                                $exception
                            );
                        }
                        if (is_dir($target) || is_link($target)) {
                            try {
                                $targetSecured = rename($target, $failed);
                            } catch (\Throwable $renameException) {
                                throw new RuntimeException(
                                    'Restore gagal dan folder tujuan baru tidak dapat diamankan. Folder lama masih tersedia di: ' . $previous,
                                    0,
                                    $exception
                                );
                            }

                            if (! $targetSecured) {
                                throw new RuntimeException(
                                    'Restore gagal dan folder tujuan baru tidak dapat diamankan. Folder lama masih tersedia di: ' . $previous,
                                    0,
                                    $exception
                                );
                            }
                        }

                        try {
                            $previousRestored = rename($previous, $target);
                        } catch (\Throwable $renameException) {
                            throw new RuntimeException(
                                'Restore gagal dan folder tujuan lama tidak berhasil dipulihkan. Folder lama masih tersedia di: ' . $previous . '. Folder hasil restore: ' . $failed . '. Backup darurat: ' . basename($emergencyBackup),
                                0,
                                $exception
                            );
                        }

                        if (! $previousRestored) {
                            throw new RuntimeException(
                                'Restore gagal dan folder tujuan lama tidak berhasil dipulihkan. Folder lama masih tersedia di: ' . $previous . '. Folder hasil restore: ' . $failed . '. Backup darurat: ' . basename($emergencyBackup),
                                0,
                                $exception
                            );
                        }
                        if (is_dir($failed) || is_link($failed)) {
                            try {
                                $this->deleteDirectory($failed, $lab);
                            } catch (\Throwable $cleanupException) {
                                // Folder gagal dibiarkan untuk pemeriksaan.
                            }
                        }
                    }
                    if ($emergencyBackupCleanupWarning !== null) {
                        throw new RuntimeException(
                            'Restore gagal. ' . $emergencyBackupCleanupWarning,
                            0,
                            $exception
                        );
                    }
                    throw $exception;
                }

    }


    /**
     * Memeriksa kondisi direktori migrasi tanpa mengubah isinya.
     */
    public function inspectRecoveryState(): array
    {
        return $this->withMigrationLock(function (): array {
            $lab = storage_path('app/private/migration-lab');
            $this->assertSafeDirectoryPath($lab);

            $entries = [];

            foreach (new \DirectoryIterator($lab) as $item) {
                if ($item->isDot()) {
                    continue;
                }

                $name = $item->getFilename();

                $recognized =
                    in_array($name, ['source', 'target'], true) ||
                    preg_match(
                        '/\A\.(?:migration|restore)_(?:previous_|failed_)?[A-Za-z0-9_]+\z/',
                        $name
                    );

                if (! $recognized) {
                    continue;
                }

                $path = $item->getPathname();
                clearstatcache(true, $path);

                $isLink = is_link($path);
                $isDirectory = ! $isLink && is_dir($path);
                $hasIndex = false;
                $hasDatabase = false;
                $integrity = 'N/A';
                $status = 'BUKAN DIREKTORI';

                if ($isLink) {
                    $status = 'TIDAK AMAN: SYMBOLIC LINK';
                } elseif ($isDirectory) {
                    $hasIndex = is_file($path . '/index.php')
                        && ! is_link($path . '/index.php');

                    $hasDatabase = is_file($path . '/database.sqlite')
                        && ! is_link($path . '/database.sqlite');

                    if ($hasDatabase) {
                        try {
                            $database = new \PDO('sqlite:' . $path . '/database.sqlite');
                            $integrity = (string) $database
                                ->query('PRAGMA integrity_check')
                                ->fetchColumn();
                            $database = null;
                        } catch (\Throwable $exception) {
                            $integrity = 'GAGAL';
                        }
                    }

                    $status = $hasIndex && $hasDatabase && $integrity === 'ok'
                        ? 'LENGKAP'
                        : 'TIDAK LENGKAP';
                }

                $entries[] = [
                    'name' => $name,
                    'type' => $isLink ? 'SYMLINK' : ($isDirectory ? 'DIREKTORI' : 'LAINNYA'),
                    'status' => $status,
                    'index' => $hasIndex ? 'ADA' : '-',
                    'database' => $hasDatabase ? 'ADA' : '-',
                    'integrity' => $integrity,
                ];
            }

            usort($entries, fn (array $a, array $b): int =>
                strcmp($a['name'], $b['name'])
            );

            $targetEntry = null;
            $previousCandidates = [];

            foreach ($entries as $entry) {
                if ($entry['name'] === 'target') {
                    $targetEntry = $entry;
                }

                if (
                    preg_match(
                        '/\\A\\.(?:migration|restore)_previous_[A-Za-z0-9_]+\\z/',
                        $entry['name']
                    ) &&
                    $entry['type'] === 'DIREKTORI' &&
                    $entry['status'] === 'LENGKAP'
                ) {
                    $previousCandidates[] = $entry['name'];
                }
            }

            $targetIsHealthy =
                $targetEntry !== null &&
                $targetEntry['type'] === 'DIREKTORI' &&
                $targetEntry['status'] === 'LENGKAP';

            $recoveryArtifacts = array_values(array_filter(
                $entries,
                fn (array $entry): bool => preg_match(
                    '/\\A\\.(?:migration|restore)_(?:previous|failed)_[A-Za-z0-9_]+\\z/',
                    $entry['name']
                ) === 1
            ));

            if ($targetIsHealthy && $recoveryArtifacts !== []) {
                $artifactNames = implode(
                    ', ',
                    array_column($recoveryArtifacts, 'name')
                );

                $recommendation =
                    'TARGET LENGKAP SECARA STRUKTURAL, TETAPI FOLDER SISA RECOVERY MASIH DITEMUKAN: ' .
                    $artifactNames .
                    '. Jangan anggap restore terverifikasi. Perlu pemeriksaan manual.';
            } elseif ($targetIsHealthy) {
                $recommendation =
                    'TARGET LENGKAP DAN VALID. Tidak diperlukan pemulihan.';
            } elseif (count($previousCandidates) === 1) {
                $targetDescription = $targetEntry === null
                    ? 'TARGET TIDAK DITEMUKAN'
                    : 'TARGET TIDAK VALID (' . $targetEntry['status'] . ')';

                $recommendation =
                    $targetDescription .
                    '. Satu folder previous lengkap ditemukan: ' .
                    $previousCandidates[0] .
                    '. Perlu verifikasi dan persetujuan sebelum pemulihan.';
            } elseif (count($previousCandidates) > 1) {
                $recommendation =
                    'KONDISI AMBIGU. Beberapa folder previous ditemukan. Jangan lakukan pemulihan otomatis.';
            } elseif ($targetEntry !== null) {
                $recommendation =
                    'TARGET TIDAK VALID (' . $targetEntry['status'] .
                    ') dan tidak ditemukan kandidat previous yang valid.';
            } else {
                $recommendation =
                    'TARGET TIDAK TERSEDIA dan tidak ditemukan satu kandidat previous yang valid.';
            }

            return [
                'entries' => $entries,
                'recommendation' => $recommendation,
            ];
        });
    }

    private function withMigrationLock(callable $callback): array
    {
        $privateDirectory = storage_path('app/private');
        $this->assertSafeDirectoryPath($privateDirectory);

        $lockPath = $privateDirectory . '/migration.lock';

        if (is_link($lockPath)) {
            throw new RuntimeException('File pengunci migrasi tidak aman.');
        }

        $handle = fopen($lockPath, 'c+');

        if ($handle === false) {
            throw new RuntimeException('File pengunci migrasi gagal dibuka.');
        }

        try {
            clearstatcache(true, $lockPath);

            $pathStat = lstat($lockPath);
            $fileStat = fstat($handle);

            if (
                $pathStat === false ||
                $fileStat === false ||
                (($pathStat['mode'] & 0170000) !== 0100000) ||
                (($fileStat['mode'] & 0170000) !== 0100000) ||
                $pathStat['dev'] !== $fileStat['dev'] ||
                $pathStat['ino'] !== $fileStat['ino'] ||
                is_link($lockPath)
            ) {
                throw new RuntimeException('File pengunci migrasi tidak valid.');
            }

            if (! chmod($lockPath, 0600)) {
                throw new RuntimeException('Permission file pengunci gagal diamankan.');
            }

            if (! flock($handle, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException(
                    'Operasi migrasi atau restore lain sedang berjalan. Silakan coba lagi.'
                );
            }

            return $callback();
        } finally {
            if (is_resource($handle)) {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
    }

    private function validateRestoreArchive(
        \ZipArchive $zip,
        int $maxZipEntries = 1000,
        int $maxEntryBytes = 52428800,
        int $maxTotalBytes = 209715200
    ): array {
        if ($zip->numFiles > $maxZipEntries) {
            throw new RuntimeException('Jumlah entri ZIP melebihi batas 1000.');
        }

        $prefix = 'target-before-migration/';
        $entrySizes = [];
        $seenEntries = [];
        $entryTypes = [];
        $totalDeclaredBytes = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);

            if (
                $stat === false ||
                ! isset($stat['name'], $stat['size']) ||
                ! is_string($stat['name']) ||
                ! is_numeric($stat['size'])
            ) {
                throw new RuntimeException('Metadata entri ZIP tidak valid.');
            }

            $entry = $stat['name'];

            if (! str_starts_with($entry, $prefix)) {
                continue;
            }

            if (
                ! isset(
                    $stat['comp_size'],
                    $stat['comp_method'],
                    $stat['encryption_method'],
                    $stat['crc']
                ) ||
                ! is_numeric($stat['comp_size']) ||
                ! is_numeric($stat['comp_method']) ||
                ! is_numeric($stat['encryption_method']) ||
                ! is_numeric($stat['crc'])
            ) {
                throw new RuntimeException('Metadata entri ZIP tidak valid.');
            }

            $relative = $this->validateRestoreEntryPath($entry, $prefix);
            $isDirectory = str_ends_with($entry, '/');

            $size = (int) $stat['size'];
            $compressedSize = (int) $stat['comp_size'];
            $compressionMethod = (int) $stat['comp_method'];
            $encryptionMethod = (int) $stat['encryption_method'];
            $crc = (int) $stat['crc'];

            if (
                $size < 0 ||
                $compressedSize < 0 ||
                $crc < 0 ||
                $crc > 0xFFFFFFFF
            ) {
                throw new RuntimeException('Metadata ukuran atau CRC ZIP tidak valid.');
            }

            if ($encryptionMethod !== ZipArchive::EM_NONE) {
                throw new RuntimeException('ZIP terenkripsi tidak diizinkan dalam backup.');
            }

            if (! in_array(
                $compressionMethod,
                [ZipArchive::CM_STORE, ZipArchive::CM_DEFLATE],
                true
            )) {
                throw new RuntimeException('Metode kompresi ZIP tidak didukung.');
            }

            if ($size < 0 || ($isDirectory && $size !== 0)) {
                throw new RuntimeException('Ukuran entri ZIP tidak valid.');
            }

            $normalizedEntry = strtolower(rtrim($entry, '/'));

            if (isset($seenEntries[$normalizedEntry])) {
                throw new RuntimeException('Entri duplikat ditemukan dalam backup.');
            }

            $seenEntries[$normalizedEntry] = true;

            $normalizedRelative = strtolower(rtrim($relative, '/'));
            $segments = explode('/', $normalizedRelative);
            $ancestor = '';

            foreach (array_slice($segments, 0, -1) as $segment) {
                $ancestor = $ancestor === ''
                    ? $segment
                    : $ancestor . '/' . $segment;

                if (($entryTypes[$ancestor] ?? null) === 'file') {
                    throw new RuntimeException(
                        'Konflik struktur file dan folder dalam backup.'
                    );
                }
            }

            $currentType = $isDirectory ? 'directory' : 'file';

            if ($currentType === 'file') {
                foreach ($entryTypes as $knownPath => $knownType) {
                    if (str_starts_with($knownPath, $normalizedRelative . '/')) {
                        throw new RuntimeException(
                            'Konflik struktur file dan folder dalam backup.'
                        );
                    }
                }
            }

            $entryTypes[$normalizedRelative] = $currentType;

            $opsys = 0;
            $attributes = 0;

            if ($zip->getExternalAttributesIndex($i, $opsys, $attributes) && $opsys === 3) {
                $mode = ($attributes >> 16) & 0xFFFF;
                $fileType = $mode & 0170000;

                if (
                    $fileType !== 0 &&
                    (
                        ($isDirectory && $fileType !== 0040000) ||
                        (! $isDirectory && $fileType !== 0100000)
                    )
                ) {
                    throw new RuntimeException('Tipe file khusus tidak diizinkan dalam backup.');
                }
            }

            if (! $isDirectory) {
                if ($size > $maxEntryBytes) {
                    throw new RuntimeException('Ukuran satu file ZIP melebihi batas 50 MB.');
                }

                if ($size > $maxTotalBytes - $totalDeclaredBytes) {
                    throw new RuntimeException('Total ukuran ekstraksi ZIP melebihi batas 200 MB.');
                }

                $totalDeclaredBytes += $size;
                $entrySizes[$entry] = $size;
            }
        }

        $requiredFiles = [
            'target-before-migration/index.php',
            'target-before-migration/database.sqlite',
        ];

        foreach ($requiredFiles as $requiredFile) {
            if ($zip->locateName($requiredFile) === false) {
                throw new RuntimeException('Backup tidak lengkap: ' . $requiredFile);
            }
        }

        return [
            'prefix' => $prefix,
            'entry_sizes' => $entrySizes,
            'max_entry_bytes' => $maxEntryBytes,
            'max_total_bytes' => $maxTotalBytes,
        ];
    }

    private function ensureSafeRestoreDirectory(
        string $stage,
        string $relative
    ): string {
        if (is_link($stage) || ! is_dir($stage)) {
            throw new RuntimeException('Direktori staging restore tidak aman.');
        }

        $segments = explode('/', rtrim($relative, '/'));

        if ($segments === [] || in_array('', $segments, true)) {
            throw new RuntimeException('Path direktori restore tidak aman.');
        }

        $current = $stage;

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..' ||
                str_contains($segment, chr(92)) ||
                str_contains($segment, chr(0))) {
                throw new RuntimeException('Path direktori restore tidak aman.');
            }

            $current .= '/' . $segment;

            clearstatcache(true, $current);

            if (is_link($current)) {
                throw new RuntimeException(
                    'Symbolic link tidak diizinkan dalam direktori restore.'
                );
            }

            if (file_exists($current)) {
                if (! is_dir($current)) {
                    throw new RuntimeException(
                        'Path direktori restore bertabrakan dengan file.'
                    );
                }

                continue;
            }

            if (! mkdir($current, 0750)) {
                clearstatcache(true, $current);

                if (is_link($current) || ! is_dir($current)) {
                    throw new RuntimeException(
                        'Folder restore gagal dibuat dengan aman.'
                    );
                }
            }

            clearstatcache(true, $current);

            if (is_link($current) || ! is_dir($current)) {
                throw new RuntimeException(
                    'Folder restore tidak aman.'
                );
            }
        }

        return $current;
    }

    private function validateRestoreEntryPath(
        string $entry,
        string $prefix
    ): string {
        if (! str_starts_with($entry, $prefix)) {
            throw new RuntimeException('Path backup tidak sesuai direktori tujuan.');
        }

        $relative = substr($entry, strlen($prefix));

        if (
            $relative === '' ||
            str_contains($relative, chr(92)) ||
            str_contains($relative, chr(0)) ||
            str_contains($relative, '//') ||
            str_starts_with($relative, '/') ||
            preg_match('/\A[A-Za-z]:/', $relative) ||
            in_array('..', explode('/', $relative), true) ||
            in_array('.', explode('/', $relative), true)
        ) {
            throw new RuntimeException('Path dalam backup tidak aman.');
        }

        return $relative;
    }

    private function assertSafeDirectoryPath(
        string $path,
        bool $mustExist = true
    ): void {
        if (is_link($path)) {
            throw new RuntimeException(
                'Direktori migrasi tidak boleh berupa symbolic link.'
            );
        }

        if ($mustExist && ! is_dir($path)) {
            throw new RuntimeException(
                'Direktori migrasi tidak ditemukan atau tidak valid.'
            );
        }

        if (! $mustExist && file_exists($path) && ! is_dir($path)) {
            throw new RuntimeException(
                'Path direktori migrasi bukan direktori yang valid.'
            );
        }
    }

    private function addDirectoryToZip(
        ZipArchive $zip,
        string $directory,
        string $prefix
    ): void {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();

            clearstatcache(true, $path);
            $before = lstat($path);

            if ($before === false || is_link($path)) {
                throw new RuntimeException(
                    'File backup tidak ditemukan atau berupa symbolic link.'
                );
            }

            if (($before['mode'] & 0170000) !== 0100000) {
                throw new RuntimeException(
                    'Tipe file tidak didukung dalam backup: ' . $path
                );
            }

            $relative = substr($path, strlen($directory) + 1);
            $beforeHash = hash_file('sha256', $path);

            if ($beforeHash === false) {
                throw new RuntimeException(
                    'File backup gagal dibaca: ' . $relative
                );
            }

            if (! $zip->addFile(
                $path,
                $prefix . '/' . $relative,
                0,
                0,
                ZipArchive::FL_OPEN_FILE_NOW
            )) {
                throw new RuntimeException('Gagal menambahkan file ke backup.');
            }

            clearstatcache(true, $path);
            $after = lstat($path);
            $afterHash = hash_file('sha256', $path);

            $stable = $after !== false && $afterHash !== false;

            foreach (['dev', 'ino', 'mode', 'size', 'mtime', 'ctime'] as $key) {
                if (
                    ! isset($before[$key], $after[$key]) ||
                    (int) $before[$key] !== (int) $after[$key]
                ) {
                    $stable = false;
                    break;
                }
            }

            if (! $stable || ! hash_equals($beforeHash, $afterHash)) {
                throw new RuntimeException(
                    'File berubah saat proses backup: ' . $relative
                );
            }
        }
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (! mkdir($destination, 0750, true)) {
            throw new RuntimeException('Folder staging gagal dibuat.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('Symbolic link tidak diizinkan.');
            }

            $relative = substr($item->getPathname(), strlen($source) + 1);
            $newPath = $destination . '/' . $relative;

            if ($item->isDir()) {
                if (! is_dir($newPath) && ! mkdir($newPath, 0750, true)) {
                    throw new RuntimeException('Subfolder staging gagal dibuat.');
                }
            } elseif ($item->isFile()) {
                if (! copy($item->getPathname(), $newPath)) {
                    throw new RuntimeException('Gagal menyalin file: ' . $relative);
                }
            } else {
                throw new RuntimeException(
                    'Tipe file tidak didukung dalam staging: ' . $item->getPathname()
                );
            }
        }
    }

    private function deleteDirectory(
        string $directory,
        string $allowedRoot
    ): void {
        if (is_link($allowedRoot) || ! is_dir($allowedRoot)) {
            throw new RuntimeException('Direktori induk penghapusan tidak aman.');
        }

        $realRoot = realpath($allowedRoot);
        $realParent = realpath(dirname($directory));
        $name = basename($directory);

        $allowedPrefixes = [
            '.migration_',
            '.migration_previous_',
            '.migration_failed_',
            '.restore_',
            '.restore_previous_',
            '.restore_failed_',
        ];

        $validName = false;

        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                $validName = true;
                break;
            }
        }

        if (
            $realRoot === false ||
            $realParent === false ||
            $realParent !== $realRoot ||
            ! $validName
        ) {
            throw new RuntimeException(
                'Direktori penghapusan berada di luar batas yang diizinkan.'
            );
        }

        if (is_link($directory)) {
            throw new RuntimeException('Direktori tidak boleh berupa symbolic link.');
        }

        if (! is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();

            if ($item->isLink()) {
                if (! unlink($path)) {
                    throw new RuntimeException('Gagal menghapus symbolic link: ' . $path);
                }
            } elseif ($item->isDir()) {
                if (! rmdir($path)) {
                    throw new RuntimeException('Gagal menghapus folder: ' . $path);
                }
            } elseif ($item->isFile()) {
                if (! unlink($path)) {
                    throw new RuntimeException('Gagal menghapus file: ' . $path);
                }
            } else {
                throw new RuntimeException(
                    'Tipe file tidak didukung saat menghapus direktori: ' . $path
                );
            }
        }

        if (! rmdir($directory)) {
            throw new RuntimeException('Gagal menghapus direktori: ' . $directory);
        }
    }
}
