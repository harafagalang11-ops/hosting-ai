<?php

namespace Tests\Unit;

use App\Services\LocalhostMigrationService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use ZipArchive;

class LocalhostMigrationServiceRestoreArchiveTest extends TestCase
{
    private string $directory;
    private object $service;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/restore-archive-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0750, true);

        $this->service = (new \ReflectionClass(
            LocalhostMigrationService::class
        ))->newInstanceWithoutConstructor();

        $this->method = new ReflectionMethod(
            LocalhostMigrationService::class,
            'validateRestoreArchive'
        );
    }

    private function createZip(
        array $files,
        ?callable $configure = null
    ): ZipArchive
    {
        $path = $this->directory . '/' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat ZIP pengujian.');
        }

        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }

        if ($configure !== null) {
            $configure($zip);
        }

        if (! $zip->close()) {
            throw new RuntimeException('Gagal menyimpan ZIP pengujian.');
        }

        $opened = new ZipArchive();

        if ($opened->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Gagal membuka ZIP pengujian.');
        }

        return $opened;
    }

    private function requiredFiles(): array
    {
        return [
            'target-before-migration/index.php' => '<?php',
            'target-before-migration/database.sqlite' => 'SQLite',
        ];
    }

    private function assertArchiveRejected(
        ZipArchive $zip,
        array $limits,
        string $message
    ): void {
        try {
            $this->method->invokeArgs($this->service, [$zip, ...$limits]);
            $this->fail('Arsip seharusnya ditolak.');
        } catch (\ReflectionException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        } finally {
            $zip->close();
        }
    }

    public function test_it_accepts_a_valid_archive(): void
    {
        $zip = $this->createZip($this->requiredFiles());

        $result = $this->method->invoke($this->service, $zip);

        $this->assertSame(
            5,
            $result['entry_sizes']['target-before-migration/index.php']
        );

        $this->assertSame(6, $result['entry_sizes']['target-before-migration/database.sqlite']);

        $zip->close();
    }

    public function test_it_rejects_unsupported_compression_methods(): void
    {
        $zip = $this->createZip(
            $this->requiredFiles(),
            function (ZipArchive $zip): void {
                $this->assertTrue($zip->setCompressionName(
                    'target-before-migration/index.php',
                    ZipArchive::CM_BZIP2
                ));
            }
        );

        $this->assertArchiveRejected(
            $zip,
            [1000, 100, 200],
            'Metode kompresi ZIP'
        );
    }

    public function test_it_rejects_encrypted_entries(): void
    {
        $zip = $this->createZip(
            $this->requiredFiles(),
            function (ZipArchive $zip): void {
                $this->assertTrue($zip->setPassword('test-password'));
                $this->assertTrue($zip->setEncryptionName(
                    'target-before-migration/index.php',
                    ZipArchive::EM_AES_256
                ));
            }
        );

        $this->assertArchiveRejected(
            $zip,
            [1000, 100, 200],
            'ZIP terenkripsi'
        );
    }

    public function test_it_rejects_too_many_entries(): void
    {
        $files = $this->requiredFiles();
        $files['target-before-migration/extra.txt'] = 'x';
        $zip = $this->createZip($files);

        $this->assertArchiveRejected(
            $zip,
            [2, 100, 200],
            'Jumlah entri ZIP'
        );
    }

    public function test_it_rejects_a_file_over_the_size_limit(): void
    {
        $files = $this->requiredFiles();
        $files['target-before-migration/large.txt'] = '12345';
        $zip = $this->createZip($files);

        $this->assertArchiveRejected(
            $zip,
            [1000, 4, 200],
            'Ukuran satu file ZIP'
        );
    }

    public function test_it_rejects_total_extraction_over_the_limit(): void
    {
        $files = $this->requiredFiles();
        $files['target-before-migration/extra.txt'] = '123';
        $zip = $this->createZip($files);

        $this->assertArchiveRejected(
            $zip,
            [1000, 100, 10],
            'Total ukuran ekstraksi ZIP'
        );
    }

    public function test_it_rejects_file_directory_conflicts_in_both_orders(): void
    {
        $conflictingEntries = [
            [
                'target-before-migration/assets' => 'file',
                'target-before-migration/assets/logo.png' => 'image',
            ],
            [
                'target-before-migration/assets/logo.png' => 'image',
                'target-before-migration/assets' => 'file',
            ],
        ];

        foreach ($conflictingEntries as $entries) {
            $zip = $this->createZip(array_merge(
                $this->requiredFiles(),
                $entries
            ));

            $this->assertArchiveRejected(
                $zip,
                [1000, 100, 200],
                'Konflik struktur'
            );
        }
    }

    public function test_it_rejects_duplicate_entries(): void
    {
        $path = $this->directory . '/duplicate.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('target-before-migration/index.php', '<?php');
        $zip->addFromString('target-before-migration/database.sqlite', 'SQLite');
        $zip->addFromString('target-before-migration/Duplicate.txt', 'one');
        $zip->addFromString('target-before-migration/duplicate.txt', 'two');
        $zip->close();

        $opened = new ZipArchive();
        $opened->open($path, ZipArchive::RDONLY);

        $this->assertArchiveRejected(
            $opened,
            [1000, 100, 200],
            'Entri duplikat'
        );
    }

    public function test_it_rejects_unix_symbolic_link_entries(): void
    {
        $path = $this->directory . '/symlink.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('target-before-migration/index.php', '<?php');
        $zip->addFromString('target-before-migration/database.sqlite', 'SQLite');
        $zip->addFromString('target-before-migration/link', 'target');

        $zip->setExternalAttributesName(
            'target-before-migration/link',
            ZipArchive::OPSYS_UNIX,
            (0120777 << 16)
        );

        $zip->close();

        $opened = new ZipArchive();
        $opened->open($path, ZipArchive::RDONLY);

        $this->assertArchiveRejected(
            $opened,
            [1000, 100, 200],
            'Tipe file khusus'
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
        parent::tearDown();
    }
}
