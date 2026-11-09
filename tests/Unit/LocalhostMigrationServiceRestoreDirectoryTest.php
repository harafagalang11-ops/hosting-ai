<?php

namespace Tests\Unit;

use App\Services\LocalhostMigrationService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

class LocalhostMigrationServiceRestoreDirectoryTest extends TestCase
{
    private string $root;
    private string $stage;
    private object $service;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/restore-directory-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0750);
        $this->stage = $this->root . '/stage';
        mkdir($this->stage, 0750);

        $this->service = (new \ReflectionClass(
            LocalhostMigrationService::class
        ))->newInstanceWithoutConstructor();

        $this->method = new ReflectionMethod(
            LocalhostMigrationService::class,
            'ensureSafeRestoreDirectory'
        );
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->root,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isLink() || $item->isFile()) {
                unlink($item->getPathname());
            } elseif ($item->isDir()) {
                rmdir($item->getPathname());
            }
        }

        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function test_it_creates_safe_nested_restore_directories(): void
    {
        $result = $this->method->invoke(
            $this->service,
            $this->stage,
            'assets/images'
        );

        $this->assertSame($this->stage . '/assets/images', $result);
        $this->assertDirectoryExists($result);
        $this->assertFalse(is_link($this->stage . '/assets'));
    }

    public function test_it_rejects_a_symbolic_link_in_restore_directory_tree(): void
    {
        $outside = $this->root . '/outside';
        mkdir($outside, 0750);

        if (! symlink($outside, $this->stage . '/assets')) {
            $this->markTestSkipped('Symlink tidak dapat dibuat pada lingkungan ini.');
        }

        try {
            $this->method->invoke(
                $this->service,
                $this->stage,
                'assets/images'
            );

            $this->fail('Symbolic link seharusnya ditolak.');
        } catch (\ReflectionException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Symbolic link tidak diizinkan dalam direktori restore.',
                $exception->getMessage()
            );
        }

        $this->assertDirectoryDoesNotExist($outside . '/images');
    }

    public function test_it_rejects_a_file_instead_of_a_directory(): void
    {
        file_put_contents($this->stage . '/assets', 'test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Path direktori restore bertabrakan dengan file.'
        );

        $this->method->invoke(
            $this->service,
            $this->stage,
            'assets/images'
        );
    }
}
