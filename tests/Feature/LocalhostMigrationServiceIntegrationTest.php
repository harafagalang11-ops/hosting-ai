<?php

namespace Tests\Feature;

use App\Services\LocalhostMigrationService;
use Illuminate\Support\Facades\Http;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use RuntimeException;
use Tests\TestCase;

class LocalhostMigrationServiceIntegrationTest extends TestCase
{
    private string $temporaryRoot;
    private string $lab;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = sys_get_temp_dir()
            . '/hosting-ai-migration-'
            . bin2hex(random_bytes(6));

        mkdir($this->temporaryRoot, 0750, true);

        $this->app->useStoragePath($this->temporaryRoot . '/storage');

        $this->lab = storage_path('app/private/migration-lab');

        mkdir($this->lab . '/source', 0750, true);
        mkdir($this->lab . '/target', 0750, true);

        mkdir(storage_path('app/private/migration-backups'), 0750, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->temporaryRoot)) {
            $this->removeTemporaryDirectory($this->temporaryRoot);
        }

        parent::tearDown();
    }

    private function createWebsite(
        string $directory,
        string $title,
        string $product
    ): void {
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }

        file_put_contents(
            $directory . '/index.php',
            '<h1>' . htmlspecialchars($title) . '</h1>'
        );

        $database = new PDO('sqlite:' . $directory . '/database.sqlite');

        $database->exec(
            'CREATE TABLE products (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL
            )'
        );

        $statement = $database->prepare(
            'INSERT INTO products (name) VALUES (:name)'
        );

        $statement->execute(['name' => $product]);

        $database = null;
    }

    private function removeTemporaryDirectory(string $directory): void
    {
        if (is_link($directory)) {
            unlink($directory);
            return;
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
            if ($item->isLink() || $item->isFile()) {
                unlink($item->getPathname());
            } elseif ($item->isDir()) {
                rmdir($item->getPathname());
            }
        }

        rmdir($directory);
    }

    public function test_it_migrates_source_to_target_and_creates_backup(): void
    {
        $this->createWebsite(
            $this->lab . '/source',
            'Website Localhost 1',
            'Produk Sumber'
        );

        $this->createWebsite(
            $this->lab . '/target',
            'Website Tujuan Lama',
            'Produk Lama'
        );

        Http::fake([
            'http://127.0.0.1:8082/*' => Http::response(
                '<h1>Website Localhost 1</h1>',
                200
            ),
        ]);

        $result = app(LocalhostMigrationService::class)->migrate();

        $this->assertSame('Berhasil', $result['verification']);
        $this->assertSame(
            file_get_contents($this->lab . '/source/index.php'),
            file_get_contents($this->lab . '/target/index.php')
        );

        $this->assertFileExists(
            storage_path('app/private/migration-backups/' . $result['backup'])
        );
    }

    public function test_it_restores_target_before_migration_from_backup(): void
    {
        $this->createWebsite(
            $this->lab . '/source',
            'Website Localhost 1',
            'Produk Sumber'
        );

        $this->createWebsite(
            $this->lab . '/target',
            'Website Tujuan Lama',
            'Produk Lama'
        );

        $originalTarget = file_get_contents(
            $this->lab . '/target/index.php'
        );

        Http::fake([
            'http://127.0.0.1:8082/*' => Http::response(
                '<h1>Website Localhost 1</h1>',
                200
            ),
        ]);

        $service = app(LocalhostMigrationService::class);

        $migration = $service->migrate();
        $restore = $service->restore($migration['backup']);

        $this->assertSame('Berhasil', $restore['verification']);
        $this->assertSame(
            $originalTarget,
            file_get_contents($this->lab . '/target/index.php')
        );

        $this->assertFileExists(
            storage_path(
                'app/private/migration-backups/' .
                $restore['emergency_backup']
            )
        );
    }

    public function test_it_rolls_back_target_when_migration_verification_fails(): void
    {
        $this->createWebsite(
            $this->lab . '/source',
            'Website Localhost 1',
            'Produk Sumber'
        );

        $this->createWebsite(
            $this->lab . '/target',
            'Website Tujuan Lama',
            'Produk Lama'
        );

        $originalTarget = file_get_contents(
            $this->lab . '/target/index.php'
        );

        Http::fake([
            'http://127.0.0.1:8082/*' => Http::response(
                'Service Unavailable',
                503
            ),
        ]);

        try {
            app(LocalhostMigrationService::class)->migrate();

            $this->fail('Migrasi seharusnya gagal saat verifikasi HTTP.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'HTTP 503',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            $originalTarget,
            file_get_contents($this->lab . '/target/index.php')
        );

        $this->assertDirectoryDoesNotExist(
            $this->lab . '/.migration_previous'
        );
    }

    public function test_migrate_preserves_previous_when_failed_target_cannot_be_moved(): void
    {
        $this->createWebsite(
            $this->lab . '/source',
            'Website Localhost 1',
            'Produk Sumber'
        );

        $this->createWebsite(
            $this->lab . '/target',
            'Website Tujuan Lama',
            'Produk Lama'
        );

        $requestCount = 0;

        Http::fake(function ($request) use (&$requestCount) {
            $requestCount++;

            if (! chmod($this->lab, 0550)) {
                throw new RuntimeException(
                    'Gagal mengubah permission direktori pengujian.'
                );
            }

            return Http::response('Service Unavailable', 503);
        });

        try {
            try {
                app(LocalhostMigrationService::class)->migrate();
                $this->fail('Migrasi seharusnya gagal saat verifikasi HTTP.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString(
                    'folder tujuan baru tidak dapat diamankan',
                    $exception->getMessage()
                );
                $this->assertStringContainsString(
                    '.migration_previous_',
                    $exception->getMessage()
                );
            }
        } finally {
            chmod($this->lab, 0750);
        }

        $this->assertSame(1, $requestCount);

        $previousFolders = glob($this->lab . '/.migration_previous_*');
        $this->assertNotEmpty($previousFolders);
        $this->assertDirectoryExists($previousFolders[0]);

        $this->assertSame(
            '<h1>Website Tujuan Lama</h1>',
            file_get_contents($previousFolders[0] . '/index.php')
        );

        $this->assertDirectoryExists($this->lab . '/target');
        $this->assertFileExists($this->lab . '/target/index.php');
    }

    public function test_restore_preserves_previous_when_failed_target_cannot_be_moved(): void
    {
        $this->createWebsite(
            $this->lab . '/source',
            'Website Localhost 1',
            'Produk Sumber'
        );

        $this->createWebsite(
            $this->lab . '/target',
            'Website Tujuan Lama',
            'Produk Lama'
        );

        $requestCount = 0;

        Http::fake(function ($request) use (&$requestCount) {
            $requestCount++;

            if ($requestCount === 1) {
                return Http::response(
                    '<h1>Website Localhost 1</h1>',
                    200
                );
            }

            if (! chmod($this->lab, 0550)) {
                throw new RuntimeException(
                    'Gagal mengubah permission direktori pengujian.'
                );
            }

            return Http::response('Service Unavailable', 503);
        });

        $service = app(LocalhostMigrationService::class);
        $migration = $service->migrate();

        $targetBeforeRestore = file_get_contents(
            $this->lab . '/target/index.php'
        );

        try {
            try {
                $service->restore($migration['backup']);
                $this->fail('Restore seharusnya gagal saat rollback terhalang.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString(
                    'folder tujuan baru tidak dapat diamankan',
                    $exception->getMessage()
                );
            }
        } finally {
            chmod($this->lab, 0750);
        }

        $this->assertSame(2, $requestCount);

        $previousFolders = glob($this->lab . '/.restore_previous_*');

        $this->assertNotEmpty($previousFolders);
        $this->assertDirectoryExists($previousFolders[0]);

        $this->assertSame(
            $targetBeforeRestore,
            file_get_contents($previousFolders[0] . '/index.php')
        );

        $this->assertDirectoryExists($this->lab . '/target');
        $this->assertFileExists($this->lab . '/target/index.php');
    }

    public function test_migrate_rejects_operation_when_shared_lock_is_held(): void
    {
        $lockPath = storage_path('app/private/migration.lock');
        $handle = fopen($lockPath, 'c+');

        $this->assertNotFalse($handle);
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));

        try {
            try {
                app(LocalhostMigrationService::class)->migrate();

                $this->fail('Migrasi seharusnya ditolak saat lock dipegang.');
            } catch (RuntimeException $exception) {
                $this->assertSame(
                    'Operasi migrasi atau restore lain sedang berjalan. Silakan coba lagi.',
                    $exception->getMessage()
                );
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function test_restore_rejects_operation_when_shared_lock_is_held(): void
    {
        $lockPath = storage_path('app/private/migration.lock');
        $handle = fopen($lockPath, 'c+');

        $this->assertNotFalse($handle);
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));

        try {
            try {
                app(LocalhostMigrationService::class)->restore('invalid.zip');

                $this->fail('Restore seharusnya ditolak saat lock dipegang.');
            } catch (RuntimeException $exception) {
                $this->assertSame(
                    'Operasi migrasi atau restore lain sedang berjalan. Silakan coba lagi.',
                    $exception->getMessage()
                );
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

}
