<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase;
use PDO;

class LocalhostMigrationRecoveryCommandTest extends TestCase
{
    private string $temporaryStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryStorage = sys_get_temp_dir()
            . '/hosting-recovery-test-' . bin2hex(random_bytes(6));

        mkdir($this->temporaryStorage . '/app/private/migration-lab', 0700, true);

        $this->app->useStoragePath($this->temporaryStorage);
    }

    protected function tearDown(): void
    {
        $this->removeTestDirectory($this->temporaryStorage);

        parent::tearDown();
    }

    public function test_command_reports_previous_candidate_without_modifying_it(): void
    {
        $lab = $this->temporaryStorage . '/app/private/migration-lab';
        $previous = $lab . '/.migration_previous_test123';

        mkdir($previous, 0700);
        file_put_contents($previous . '/index.php', '<?php echo "old";');

        $database = new PDO('sqlite:' . $previous . '/database.sqlite');
        $database->exec('CREATE TABLE recovery_test (id INTEGER)');
        $database = null;

        $originalContent = file_get_contents($previous . '/index.php');

        $this->artisan('hosting:migration-recover')
            ->assertExitCode(0);

        $this->assertDirectoryExists($previous);
        $this->assertFileDoesNotExist($lab . '/target');
        $this->assertSame(
            $originalContent,
            file_get_contents($previous . '/index.php')
        );
        $this->assertFileExists($previous . '/database.sqlite');
    }

    public function test_command_does_not_overwrite_existing_target(): void
    {
        $lab = $this->temporaryStorage . '/app/private/migration-lab';
        $target = $lab . '/target';

        mkdir($target, 0700);
        file_put_contents($target . '/index.php', '<?php echo "current";');

        $database = new PDO('sqlite:' . $target . '/database.sqlite');
        $database->exec('CREATE TABLE current_test (id INTEGER)');
        $database = null;

        $this->artisan('hosting:migration-recover')
            ->expectsOutputToContain('TARGET LENGKAP DAN VALID')
            ->assertExitCode(0);

        $this->assertSame(
            '<?php echo "current";',
            file_get_contents($target . '/index.php')
        );
        $this->assertDirectoryExists($target);
    }

    public function test_command_marks_invalid_target_and_reports_valid_previous_candidate(): void
    {
        $lab = $this->temporaryStorage . '/app/private/migration-lab';
        $target = $lab . '/target';
        $previous = $lab . '/.migration_previous_recovery123';

        mkdir($target, 0700);
        file_put_contents($target . '/index.php', '<?php echo "broken";');
        file_put_contents($target . '/database.sqlite', 'not a valid sqlite database');

        mkdir($previous, 0700);
        file_put_contents($previous . '/index.php', '<?php echo "old";');

        $database = new PDO('sqlite:' . $previous . '/database.sqlite');
        $database->exec('CREATE TABLE recovery_test (id INTEGER)');
        $database = null;

        $this->artisan('hosting:migration-recover')
            ->expectsOutputToContain('TARGET TIDAK VALID')
            ->expectsOutputToContain('.migration_previous_recovery123')
            ->assertExitCode(0);

        $this->assertDirectoryExists($target);
        $this->assertSame(
            '<?php echo "broken";',
            file_get_contents($target . '/index.php')
        );
        $this->assertDirectoryExists($previous);
        $this->assertFileExists($previous . '/database.sqlite');
    }

    public function test_command_does_not_call_invalid_target_available_without_previous(): void
    {
        $lab = $this->temporaryStorage . '/app/private/migration-lab';
        $target = $lab . '/target';

        mkdir($target, 0700);
        file_put_contents($target . '/index.php', '<?php echo "broken";');

        $this->artisan('hosting:migration-recover')
            ->expectsOutputToContain('TARGET TIDAK VALID')
            ->assertExitCode(0);

        $this->assertDirectoryExists($target);
        $this->assertFileExists($target . '/index.php');
    }

    public function test_command_requires_manual_inspection_when_recovery_artifact_remains_with_valid_target(): void
    {
        $lab = $this->temporaryStorage . '/app/private/migration-lab';
        $target = $lab . '/target';
        $failed = $lab . '/.restore_failed_test123';

        mkdir($target, 0700);
        file_put_contents($target . '/index.php', '<?php echo "current";');

        $database = new PDO('sqlite:' . $target . '/database.sqlite');
        $database->exec('CREATE TABLE current_test (id INTEGER)');
        $database = null;

        mkdir($failed, 0700);
        file_put_contents($failed . '/index.php', '<?php echo "failed";');

        $targetContent = file_get_contents($target . '/index.php');
        $failedContent = file_get_contents($failed . '/index.php');

        $inspection = app(
            \App\Services\LocalhostMigrationService::class
        )->inspectRecoveryState();

        $this->assertStringContainsString(
            'pemeriksaan manual',
            strtolower($inspection['recommendation'])
        );

        $this->artisan('hosting:migration-recover')
            ->expectsOutputToContain('TARGET LENGKAP SECARA STRUKTURAL')
            ->expectsOutputToContain('.restore_failed_test123')
            ->assertExitCode(0);

        $this->assertDirectoryExists($target);
        $this->assertDirectoryExists($failed);
        $this->assertSame(
            $targetContent,
            file_get_contents($target . '/index.php')
        );
        $this->assertSame(
            $failedContent,
            file_get_contents($failed . '/index.php')
        );
    }

    private function removeTestDirectory(string $directory): void
    {
        if (! is_dir($directory) || is_link($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $directory,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && ! $item->isLink()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }
}
