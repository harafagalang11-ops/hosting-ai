<?php

namespace App\Http\Controllers;

use App\Services\MigrationConnectionService;
use App\Services\MigrationRemoteFileBrowser;
use RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MigrationEndpointController extends Controller
{
    public function index(): View
    {
        $endpoints = auth()->user()
            ->migrationEndpoints()
            ->latest()
            ->get();

        return view('migration.endpoints', [
            'endpoints' => $endpoints,
            'endpoint' => null,
        ]);
    }

    public function edit(int $id): View
    {
        $endpoint = auth()->user()
            ->migrationEndpoints()
            ->findOrFail($id);

        $endpoints = auth()->user()
            ->migrationEndpoints()
            ->latest()
            ->get();

        return view('migration.endpoints', compact('endpoints', 'endpoint'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request, true);

        auth()->user()->migrationEndpoints()->create($data);

        return redirect()
            ->route('migration-endpoints.index')
            ->with('success', 'Konfigurasi hosting berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $endpoint = auth()->user()
            ->migrationEndpoints()
            ->findOrFail($id);

        $data = $this->validatedData($request, false);

        if (blank($data['credential'] ?? null)) {
            unset($data['credential']);
        }

        if (blank($data['host_key_fingerprint'] ?? null)) {
            unset($data['host_key_fingerprint']);
        }

        $endpoint->update($data);

        return redirect()
            ->route('migration-endpoints.index')
            ->with('success', 'Konfigurasi hosting berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $endpoint = auth()->user()
            ->migrationEndpoints()
            ->findOrFail($id);

        $endpoint->delete();

        return redirect()
            ->route('migration-endpoints.index')
            ->with('success', 'Konfigurasi hosting berhasil dihapus.');
    }

    public function testConnection(
        int $id,
        MigrationConnectionService $service
    ): RedirectResponse {
        $endpoint = auth()->user()
            ->migrationEndpoints()
            ->findOrFail($id);

        $result = $service->test($endpoint);

        return redirect()
            ->route('migration-endpoints.index')
            ->with(
                $result['success'] ? 'success' : 'connection_error',
                $result['message']
            );
    }

    public function browseFiles(
        Request $request,
        int $id,
        MigrationRemoteFileBrowser $browser
    ): View {
        $endpoint = auth()->user()
            ->migrationEndpoints()
            ->findOrFail($id);

        abort_unless($endpoint->connection_type === 'sftp', 403);

        $path = $request->query('path', '.');
        abort_unless(is_string($path), 422, 'Path direktori tidak valid.');

        $isDemo = app()->environment('local')
            && $endpoint->name === 'SFTP Demo Lokal'
            && $endpoint->host === 'sftp-demo.invalid';

        $result = [
            'path' => $path,
            'entries' => [],
            'total' => 0,
            'truncated' => false,
        ];

        $error = null;

        try {
            if ($isDemo) {
                $result = $this->demoDirectory($path);

                if ($result === null) {
                    $error = 'Direktori tidak ditemukan atau path tidak valid.';
                    $result = [
                        'path' => $path,
                        'entries' => [],
                        'total' => 0,
                        'truncated' => false,
                    ];
                }
            } else {
                $result = $browser->listDirectory($endpoint, $path);
            }
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        return view('migration.files', [
            'endpoint' => $endpoint,
            'path' => $result['path'],
            'entries' => $result['entries'],
            'total' => $result['total'],
            'truncated' => $result['truncated'],
            'error' => $error,
            'isDemo' => $isDemo,
        ]);
    }

    public function inventory(int $id): View
    {
        $endpoint = auth()->user()
            ->migrationEndpoints()
            ->findOrFail($id);

        abort_unless(
            app()->environment('local')
            && $endpoint->connection_type === 'sftp'
            && $endpoint->name === 'SFTP Demo Lokal'
            && $endpoint->host === 'sftp-demo.invalid',
            404
        );

        $queue = [['path' => '.', 'depth' => 0]];
        $items = [];
        $directories = 0;
        $files = 0;
        $totalSize = 0;
        $truncated = false;
        $maxDepth = 5;
        $maxEntries = 500;

        while ($queue !== []) {
            $current = array_shift($queue);
            $listing = $this->demoDirectory($current['path']);

            if ($listing === null) {
                continue;
            }

            foreach ($listing['entries'] as $entry) {
                if (count($items) >= $maxEntries) {
                    $truncated = true;
                    break 2;
                }

                $path = $current['path'] === '.'
                    ? $entry['name']
                    : $current['path'] . '/' . $entry['name'];

                $depth = $current['depth'] + 1;

                $items[] = [
                    'path' => $path,
                    'name' => $entry['name'],
                    'type' => $entry['type'],
                    'size' => (int) $entry['size'],
                    'depth' => $depth,
                ];

                if ($entry['type'] === 'directory') {
                    $directories++;

                    if ($depth < $maxDepth) {
                        $queue[] = ['path' => $path, 'depth' => $depth];
                    } else {
                        $truncated = true;
                    }
                } else {
                    $files++;
                    $totalSize += (int) $entry['size'];
                }
            }
        }

        return view('migration.inventory', compact(
            'endpoint',
            'items',
            'directories',
            'files',
            'totalSize',
            'truncated',
            'maxDepth',
            'maxEntries'
        ));
    }

    private function demoDirectory(string $path): ?array
    {
        if (
            $path === ''
            || strlen($path) > 1024
            || str_contains($path, "\0")
            || str_contains($path, '\\')
        ) {
            return null;
        }

        $normalized = trim($path, '/');
        $normalized = $normalized === '' ? '.' : $normalized;

        $segments = explode('/', $normalized);

        if (in_array('..', $segments, true)) {
            return null;
        }

        $directories = [
            '.' => [
                ['name' => 'public_html', 'type' => 'directory', 'size' => 0],
                ['name' => 'storage', 'type' => 'directory', 'size' => 0],
                ['name' => 'backup.zip', 'type' => 'file', 'size' => 204800],
            ],
            'public_html' => [
                ['name' => 'assets', 'type' => 'directory', 'size' => 0],
                ['name' => 'index.php', 'type' => 'file', 'size' => 4096],
                ['name' => 'robots.txt', 'type' => 'file', 'size' => 128],
            ],
            'public_html/assets' => [
                ['name' => 'css', 'type' => 'directory', 'size' => 0],
                ['name' => 'js', 'type' => 'directory', 'size' => 0],
                ['name' => 'logo.png', 'type' => 'file', 'size' => 8192],
            ],
            'public_html/assets/css' => [
                ['name' => 'app.css', 'type' => 'file', 'size' => 2048],
            ],
            'public_html/assets/js' => [
                ['name' => 'app.js', 'type' => 'file', 'size' => 3072],
            ],
            'storage' => [
                ['name' => 'logs', 'type' => 'directory', 'size' => 0],
                ['name' => 'uploads', 'type' => 'directory', 'size' => 0],
            ],
            'storage/logs' => [
                ['name' => 'application.log', 'type' => 'file', 'size' => 16384],
            ],
            'storage/uploads' => [],
        ];

        // Samakan struktur metadata simulasi dengan hasil browser SFTP.
        foreach ($directories as &$entries) {
            foreach ($entries as &$entry) {
                $entry['modified_at'] = strtotime('2026-11-07 12:00:00');
                $entry['permissions'] = $entry['type'] === 'directory'
                    ? '0755'
                    : '0644';
            }
            unset($entry);
        }
        unset($entries);

        if (!array_key_exists($normalized, $directories)) {
            return null;
        }

        return [
            'path' => $normalized,
            'entries' => $directories[$normalized],
            'total' => count($directories[$normalized]),
            'truncated' => false,
        ];
    }

    private function validatedData(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'host' => [
                'required',
                'string',
                'max:253',
                function ($attribute, $value, $fail) {
                    if (
                        filter_var($value, FILTER_VALIDATE_IP) ||
                        ! filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
                    ) {
                        $fail('Masukkan domain atau hostname yang valid tanpa URL atau IP.');
                    }
                },
            ],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'connection_type' => ['required', 'in:ssh,sftp,cpanel_api'],
            'username' => ['required', 'string', 'max:150'],
            'host_key_fingerprint' => [
                'nullable',
                'string',
                'regex:/^SHA256:[A-Za-z0-9+\\/]{43}$/',
            ],
            'password' => $creating
                ? ['required', 'string', 'max:10000']
                : ['nullable', 'string', 'max:10000'],
        ]);

        $data['credential'] = $data['password'];
        unset($data['password']);

        $data['port'] = filled($data['port'] ?? null)
            ? (int) $data['port']
            : match ($data['connection_type']) {
                'cpanel_api' => 2083,
                'ssh', 'sftp' => 22,
            };

        $data['host_key_fingerprint'] = blank($data['host_key_fingerprint'] ?? null)
            ? null
            : $data['host_key_fingerprint'];

        return $data;
    }
}
