<?php

namespace App\Http\Controllers;

use App\Models\HostingMigration;
use App\Models\MigrationEndpoint;
use App\Models\Website;
use App\Services\LocalhostMigrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class MigrationController extends Controller
{
    public function index(): View
    {
        $sourceStatus = $this->checkServer('http://' . '127.0.0.1:8081/');
        $targetStatus = $this->checkServer('http://' . '127.0.0.1:8082/');

        $migrationHistories = HostingMigration::where('user_id', auth()->id())
            ->latest()
            ->take(10)
            ->get();

        $hostingEndpoints = MigrationEndpoint::where('user_id', auth()->id())
            ->orderBy('name')
            ->get(['id', 'name', 'host', 'port', 'connection_type']);

        $websites = Website::where('user_id', auth()->id())
            ->where('is_active', true)
            ->whereNotNull('migration_endpoint_id')
            ->whereIn('migration_endpoint_id', $hostingEndpoints->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name', 'url', 'migration_endpoint_id', 'document_root']);

        return view('migration.index', compact(
            'sourceStatus',
            'targetStatus',
            'migrationHistories',
            'hostingEndpoints',
            'websites'
        ));
    }

    private function checkServer(string $url): bool
    {
        try {
            return Http::timeout(3)->get($url)->successful();
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function run(
        Request $request,
        LocalhostMigrationService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'source_endpoint_id' => ['required', 'integer'],
            'target_endpoint_id' => ['required', 'integer', 'different:source_endpoint_id'],
            'website_id' => ['required', 'integer'],
        ]);

        $userId = auth()->id();

        $source = MigrationEndpoint::where('user_id', $userId)
            ->findOrFail($validated['source_endpoint_id']);

        $target = MigrationEndpoint::where('user_id', $userId)
            ->findOrFail($validated['target_endpoint_id']);

        $website = Website::where('user_id', $userId)
            ->where('is_active', true)
            ->where('migration_endpoint_id', $source->id)
            ->findOrFail($validated['website_id']);

        $history = HostingMigration::create([
            'user_id' => $userId,
            'source_endpoint_id' => $source->id,
            'target_endpoint_id' => $target->id,
            'website_id' => $website->id,
            'website_name' => $website->name,
            'source' => $source->name . ' (' . $source->host . ':' . $source->port . ')',
            'target' => $target->name . ' (' . $target->host . ':' . $target->port . ')',
            'status' => 'running',
        ]);

        try {
            $result = $service->migrate([
                'source_endpoint_id' => $source->id,
                'target_endpoint_id' => $target->id,
                'website_id' => $website->id,
                'website_name' => $website->name,
            ]);

            $history->update([
                'status' => 'success',
                'file_count' => $result['files'],
                'database_integrity' => $result['database'],
                'http_status' => $result['http_status'],
                'backup_file' => $result['backup'],
            ]);

            return redirect()
                ->route('migration.index')
                ->with('migration_result', $result)
                ->with('success', 'Migrasi localhost berhasil diselesaikan.');
        } catch (Throwable $exception) {
            report($exception);

            $history->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
            ]);

            return redirect()
                ->route('migration.index')
                ->with('error', 'Migrasi gagal: ' . $exception->getMessage());
        }
    }
}
