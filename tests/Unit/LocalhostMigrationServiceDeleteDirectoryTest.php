<?php

namespace Tests\Unit;

use App\Services\LocalhostMigrationService;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use RuntimeException;
use ReflectionMethod;

class LocalhostMigrationServiceDeleteDirectoryTest extends TestCase
{
    private string $baseDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDirectory = sys_get_temp_dir()
            . '/hosting-ai-delete-' . bin2hex(random_bytes(6));

        mkdir($this->baseDirectory, 0700, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->baseDirectory)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $this->baseDirectory,
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

            rmdir($this->baseDirectory);
        }

        parent::tearDown();
    }

    private function invokeDeleteDirectory(string $directory): void
    {
        $service = new LocalhostMigrationService();

        $method = new ReflectionMethod(
            LocalhostMigrationService::class,
            'deleteDirectory'
        );

        $method->invoke($service, $directory, $this->baseDirectory);
    }


    public function test_it_rejects_a_directory_outside_allowed_root(): void
    {
        $outside = sys_get_temp_dir()
            . '/hosting-ai-outside-' . bin2hex(random_bytes(6));

        mkdir($outside);
        file_put_contents($outside . '/important.txt', 'keep');

        try {
            $this->invokeDeleteDirectory($outside);
            $this->fail('Direktori di luar batas seharusnya ditolak.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Direktori penghapusan berada di luar batas yang diizinkan.',
                $exception->getMessage()
            );
        }

        $this->assertFileExists($outside . '/important.txt');
        unlink($outside . '/important.txt');
        rmdir($outside);
    }

    public function test_it_deletes_directory_and_nested_contents(): void
    {
        $directory = $this->baseDirectory . '/.migration_test-root';
        mkdir($directory . '/nested/deep', 0700, true);

        file_put_contents($directory . '/first.txt', 'first');
        file_put_contents($directory . '/nested/deep/second.txt', 'second');

        $this->invokeDeleteDirectory($directory);

        $this->assertFalse(file_exists($directory));
        $this->assertFalse(is_link($directory));
    }

    public function test_it_deletes_symlink_without_deleting_target(): void
    {
        $target = $this->baseDirectory . '/target';
        $directory = $this->baseDirectory . '/.migration_test-root';

        mkdir($target);
        mkdir($directory);

        file_put_contents($target . '/important.txt', 'keep');

        if (! symlink($target, $directory . '/target-link')) {
            $this->markTestSkipped('Symbolic link tidak dapat dibuat.');
        }

        $this->invokeDeleteDirectory($directory);

        $this->assertFalse(file_exists($directory));
        $this->assertFileExists($target . '/important.txt');
        $this->assertSame('keep', file_get_contents($target . '/important.txt'));
    }

    public function test_it_rejects_a_root_symbolic_link(): void
    {
        $target = $this->baseDirectory . '/target';
        $link = $this->baseDirectory . '/.migration_test-link';

        mkdir($target);
        file_put_contents($target . '/important.txt', 'keep');

        if (! symlink($target, $link)) {
            $this->markTestSkipped('Symbolic link tidak dapat dibuat.');
        }

        try {
            $this->invokeDeleteDirectory($link);
            $this->fail('Direktori symbolic link seharusnya ditolak.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Direktori tidak boleh berupa symbolic link.',
                $exception->getMessage()
            );
        }

        $this->assertTrue(is_link($link));
        $this->assertFileExists($target . '/important.txt');
    }

    public function test_it_ignores_a_nonexistent_directory(): void
    {
        $directory = $this->baseDirectory . '/.migration_test-missing';

        $this->invokeDeleteDirectory($directory);

        $this->assertFalse(file_exists($directory));
    }

    public function test_it_rejects_fifo_inside_directory(): void
    {
        if (! function_exists('posix_mkfifo')) {
            $this->markTestSkipped('Fungsi posix_mkfifo tidak tersedia.');
        }

        $directory = $this->baseDirectory . '/.migration_test-fifo-root';
        mkdir($directory);

        $fifo = $directory . '/unsupported-pipe';

        if (! posix_mkfifo($fifo, 0600)) {
            $this->markTestSkipped('FIFO tidak dapat dibuat.');
        }

        try {
            $this->invokeDeleteDirectory($directory);
            $this->fail('FIFO seharusnya ditolak.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Tipe file tidak didukung saat menghapus direktori: ' . $fifo,
                $exception->getMessage()
            );
        }

        $this->assertTrue(file_exists($fifo));
    }

}
