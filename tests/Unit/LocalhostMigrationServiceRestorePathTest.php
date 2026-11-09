<?php

namespace Tests\Unit;

use App\Services\LocalhostMigrationService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

class LocalhostMigrationServiceRestorePathTest extends TestCase
{
    private object $service;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        $this->service = (new \ReflectionClass(
            LocalhostMigrationService::class
        ))->newInstanceWithoutConstructor();

        $this->method = new ReflectionMethod(
            LocalhostMigrationService::class,
            'validateRestoreEntryPath'
        );
    }

    public function test_it_accepts_a_safe_nested_path(): void
    {
        $result = $this->method->invoke(
            $this->service,
            'target-before-migration/assets/images/logo.png',
            'target-before-migration/'
        );

        $this->assertSame('assets/images/logo.png', $result);
    }

    public function test_it_rejects_unsafe_archive_paths(): void
    {
        $unsafePaths = [
            'target-before-migration/',
            'target-before-migration/../escape.php',
            'target-before-migration/folder/../escape.php',
            'target-before-migration/folder//escape.php',
            'target-before-migration/./index.php',
            'target-before-migration//absolute.php',
            'target-before-migration/C:/Windows/file.php',
            'target-before-migration/folder\\escape.php',
            "target-before-migration/file\0.php",
        ];

        foreach ($unsafePaths as $path) {
            try {
                $this->method->invoke(
                    $this->service,
                    $path,
                    'target-before-migration/'
                );

                $this->fail('Path berbahaya seharusnya ditolak: ' . $path);
            } catch (RuntimeException $exception) {
                $this->assertSame(
                    'Path dalam backup tidak aman.',
                    $exception->getMessage()
                );
            }
        }
    }

    public function test_it_rejects_an_entry_outside_the_expected_prefix(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Path backup tidak sesuai direktori tujuan.'
        );

        $this->method->invoke(
            $this->service,
            'other-folder/index.php',
            'target-before-migration/'
        );
    }
}
