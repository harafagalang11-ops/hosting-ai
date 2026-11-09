<?php

namespace Tests\Unit;

use App\Services\LocalhostMigrationService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

class LocalhostMigrationServiceDirectorySafetyTest extends TestCase
{
    private string $root;
    private object $service;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/migration-directory-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0750, true);
        $this->service = (new \ReflectionClass(LocalhostMigrationService::class))
            ->newInstanceWithoutConstructor();
        $this->method = new ReflectionMethod(
            LocalhostMigrationService::class,
            'assertSafeDirectoryPath'
        );
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    private function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }
        if (! is_dir($path)) {
            return;
        }
        foreach (new \FilesystemIterator($path) as $item) {
            $this->removeTree($item->getPathname());
        }
        rmdir($path);
    }

    public function test_it_accepts_an_existing_normal_directory(): void
    {
        $directory = $this->root . '/normal';
        mkdir($directory);
        $this->method->invoke($this->service, $directory);
        $this->assertDirectoryExists($directory);
    }

    public function test_it_rejects_a_symbolic_link_directory(): void
    {
        $real = $this->root . '/real';
        $link = $this->root . '/linked';
        mkdir($real);
        if (! symlink($real, $link)) {
            $this->markTestSkipped('Symbolic link tidak didukung.');
        }
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Direktori migrasi tidak boleh berupa symbolic link.');
        $this->method->invoke($this->service, $link);
    }

    public function test_it_rejects_a_missing_required_directory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Direktori migrasi tidak ditemukan atau tidak valid.');
        $this->method->invoke($this->service, $this->root . '/missing');
    }

    public function test_it_allows_a_missing_optional_directory(): void
    {
        $missing = $this->root . '/not-created-yet';
        $this->method->invoke($this->service, $missing, false);
        $this->assertFalse(is_dir($missing));
    }

    public function test_it_rejects_a_file_where_an_optional_directory_is_expected(): void
    {
        $file = $this->root . '/not-a-directory';
        file_put_contents($file, 'test');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Path direktori migrasi bukan direktori yang valid.');
        $this->method->invoke($this->service, $file, false);
    }
}
