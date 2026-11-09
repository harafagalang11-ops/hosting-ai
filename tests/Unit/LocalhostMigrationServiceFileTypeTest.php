<?php

namespace Tests\Unit;

use App\Services\LocalhostMigrationService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use RuntimeException;
use ZipArchive;

class LocalhostMigrationServiceFileTypeTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir()
            . '/hosting-ai-filetype-' . bin2hex(random_bytes(6));

        mkdir($this->directory, 0750, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory) && ! is_link($this->directory)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $this->directory,
                    FilesystemIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                $path = $item->getPathname();

                if ($item->isLink() || ! $item->isDir()) {
                    @unlink($path);
                } else {
                    @rmdir($path);
                }
            }

            @rmdir($this->directory);
        }

        parent::tearDown();
    }

    public function test_copy_directory_rejects_fifo(): void
    {
        $source = $this->directory . '/source';
        $destination = $this->directory . '/destination';

        mkdir($source, 0750, true);
        $this->assertTrue(posix_mkfifo($source . '/unsupported-pipe', 0600));

        $service = (new ReflectionClass(LocalhostMigrationService::class))
            ->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(
            LocalhostMigrationService::class,
            'copyDirectory'
        );


        try {
            $method->invoke($service, $source, $destination);
            $this->fail('Seharusnya FIFO ditolak oleh copyDirectory.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'Tipe file tidak didukung dalam staging:',
                $exception->getMessage()
            );
        }
    }

    public function test_add_directory_to_zip_includes_regular_file_contents(): void
    {
        $source = $this->directory . '/source';
        $zipPath = $this->directory . '/regular-file.zip';
        mkdir($source, 0750, true);
        file_put_contents($source . '/example.txt', 'backup-content');

        $zip = new ZipArchive();
        $this->assertTrue(
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)
        );

        $service = (new ReflectionClass(LocalhostMigrationService::class))
            ->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(
            LocalhostMigrationService::class,
            'addDirectoryToZip'
        );

        try {
            $method->invoke($service, $zip, $source, 'source');
            $this->assertTrue($zip->close());

            $opened = new ZipArchive();
            $this->assertTrue($opened->open($zipPath, ZipArchive::RDONLY));

            try {
                $this->assertSame(
                    'backup-content',
                    $opened->getFromName('source/example.txt')
                );
            } finally {
                $opened->close();
            }
        } finally {
            if ($zip->status !== ZipArchive::ER_OK) {
                $zip->close();
            }
        }
    }

    public function test_add_directory_to_zip_rejects_fifo(): void
    {
        $source = $this->directory . '/source';
        $zipPath = $this->directory . '/test.zip';

        mkdir($source, 0750, true);
        $this->assertTrue(posix_mkfifo($source . '/unsupported-pipe', 0600));

        $zip = new ZipArchive();

        $this->assertTrue(
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)
        );

        $service = (new ReflectionClass(LocalhostMigrationService::class))
            ->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(
            LocalhostMigrationService::class,
            'addDirectoryToZip'
        );


        try {
            $method->invoke($service, $zip, $source, 'source');
            $this->fail('Seharusnya FIFO ditolak oleh addDirectoryToZip.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'Tipe file tidak didukung dalam backup:',
                $exception->getMessage()
            );
        } finally {
            $zip->close();
        }
    }
}
