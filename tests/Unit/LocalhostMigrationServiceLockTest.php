<?php

namespace Tests\Unit;

use App\Services\LocalhostMigrationService;
use RuntimeException;
use Tests\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use ReflectionMethod;

class LocalhostMigrationServiceLockTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = sys_get_temp_dir()
            . '/hosting-ai-lock-' . bin2hex(random_bytes(6));

        $private = $this->temporaryRoot . '/storage/app/private';

        mkdir($private, 0700, true);
        $this->app->useStoragePath($this->temporaryRoot . '/storage');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->temporaryRoot)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $this->temporaryRoot,
                    FilesystemIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                $path = $item->getPathname();

                if ($item->isDir() && ! $item->isLink()) {
                    rmdir($path);
                } else {
                    unlink($path);
                }
            }

            rmdir($this->temporaryRoot);
        }

        parent::tearDown();
    }

    private function invokeLock(callable $callback): array
    {
        $service = new LocalhostMigrationService();

        $method = new ReflectionMethod(
            LocalhostMigrationService::class,
            'withMigrationLock'
        );

        return $method->invoke($service, $callback);
    }

    private function assertLockCanBeAcquired(): void
    {
        $path = storage_path('app/private/migration.lock');
        $handle = fopen($path, 'c+');

        $this->assertNotFalse($handle);

        try {
            $this->assertTrue(
                flock($handle, LOCK_EX | LOCK_NB),
                'Lock seharusnya sudah dilepas.'
            );

            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    public function test_it_rejects_a_second_operation_while_lock_is_held(): void
    {
        $path = storage_path('app/private/migration.lock');
        $handle = fopen($path, 'c+');

        $this->assertNotFalse($handle);
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));

        $callbackExecuted = false;

        try {
            try {
                $this->invokeLock(function () use (&$callbackExecuted): array {
                    $callbackExecuted = true;

                    return ['unexpected' => true];
                });

                $this->fail('Operasi kedua seharusnya ditolak.');
            } catch (RuntimeException $exception) {
                $this->assertSame(
                    'Operasi migrasi atau restore lain sedang berjalan. Silakan coba lagi.',
                    $exception->getMessage()
                );
            }

            $this->assertFalse($callbackExecuted);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        $this->assertLockCanBeAcquired();
    }

    public function test_it_releases_lock_after_successful_operation(): void
    {
        $result = $this->invokeLock(
            fn (): array => ['status' => 'success']
        );

        $this->assertSame(['status' => 'success'], $result);
        $this->assertLockCanBeAcquired();
    }

    public function test_it_releases_lock_after_operation_throws_exception(): void
    {
        try {
            $this->invokeLock(function (): array {
                throw new RuntimeException('Simulasi kegagalan operasi.');
            });

            $this->fail('Exception seharusnya diteruskan.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Simulasi kegagalan operasi.',
                $exception->getMessage()
            );
        }

        $this->assertLockCanBeAcquired();
    }
}
