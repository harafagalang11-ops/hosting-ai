<?php

namespace App\Console\Commands;

use App\Services\LocalhostMigrationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('hosting:migration-recover')]
#[Description('Memeriksa kondisi direktori migrasi dan kemungkinan pemulihan tanpa mengubah data')]
class RecoverLocalhostMigration extends Command
{
    public function handle(LocalhostMigrationService $service): int
    {
        try {
            $result = $service->inspectRecoveryState();
        } catch (Throwable $exception) {
            $this->error('Pemeriksaan gagal: ' . $exception->getMessage());

            return self::FAILURE;
        }

        if (empty($result['entries'])) {
            $this->warn('Tidak ditemukan direktori migrasi yang dikenali.');
        } else {
            $this->table(
                ['Nama', 'Tipe', 'Status', 'index.php', 'database.sqlite', 'Integritas SQLite'],
                array_map(
                    fn (array $entry): array => [
                        $entry['name'],
                        $entry['type'],
                        $entry['status'],
                        $entry['index'],
                        $entry['database'],
                        $entry['integrity'],
                    ],
                    $result['entries']
                )
            );
        }

        $this->newLine();
        $this->info($result['recommendation']);
        $this->comment('Mode pemeriksaan: READ-ONLY. Tidak ada folder atau backup yang dipindahkan atau dihapus.');

        return self::SUCCESS;
    }
}
