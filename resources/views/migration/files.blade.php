<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Hosting | Hosting AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app-layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    <style>
        .page-heading { margin-bottom: 24px; }
        .page-heading h1 { font-size: 28px; font-weight: 750; margin-bottom: 7px; }
        .page-heading p, .hosting-muted { color: var(--muted, #9ca3af); }
        .hosting-card {
            padding: 22px;
            border: 1px solid var(--card-border, #374151);
            border-radius: 15px;
            background: var(--card-bg, #1f2937);
        }
        .hosting-table { color: var(--text, #e5e7eb); vertical-align: middle; }
        .hosting-table th {
            color: var(--muted, #9ca3af);
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .hosting-table td { font-size: 13px; }
        .file-name { overflow-wrap: anywhere; }
        .path-display {
            overflow-wrap: anywhere;
            padding: 10px 13px;
            border-radius: 8px;
            background: var(--input-bg, #111827);
            color: var(--text, #e5e7eb);
        }
        @media(max-width: 700px) {
            .page-heading h1 { font-size: 24px; }
            .hosting-card { padding: 16px; }
        }
    </style>
</head>
<body class="{{ auth()->user()->theme === 'dark' ? 'theme-dark' : '' }}">
<div class="d-flex">
    @include('partials.sidebar')

    <main class="content">
        <div class="page-heading">
            <div class="text-primary small fw-bold mb-2">
                <i class="bi bi-folder2-open me-1"></i>
                HOSTING MANAGEMENT
            </div>
            <h1>Penjelajah File Hosting</h1>
            <p>Melihat daftar direktori dan file melalui koneksi SFTP.</p>
        </div>

        <div class="hosting-card">
@if ($isDemo ?? false)
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Mode Simulasi SFTP.</strong>
        Data direktori ini merupakan data contoh lokal,
        bukan isi server hosting sungguhan.
        Tidak ada koneksi jaringan yang dilakukan.
    </div>
@endif

            @if ($isDemo ?? false)
                <div class="mb-3">
                    <a href="{{ route('migration-endpoints.inventory', $endpoint->id) }}"
                       class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-diagram-3 me-1"></i>
                        Lihat Inventaris Bertingkat
                    </a>
                </div>
            @endif

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h5 class="mb-1">{{ $endpoint->name }}</h5>
                    <div class="hosting-muted small">
                        {{ $endpoint->username }}@{{ $endpoint->host }}:{{ $endpoint->port }}
                    </div>
                </div>
                <a href="{{ route('migration-endpoints.index') }}"
                   class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Kembali ke Kelola Hosting
                </a>
            </div>

            <div class="mb-3">
                <div class="small hosting-muted mb-1">Direktori saat ini</div>
                <div class="path-display">
                    <i class="bi bi-folder2-open me-2"></i>{{ $path }}
                </div>
            </div>

            @if ($error)
                <div class="alert alert-danger mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    {{ $error }}
                </div>
            @else
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="small hosting-muted">
                        {{ number_format($total) }} item ditemukan
                        @if ($truncated)
                            — hanya 500 item pertama ditampilkan
                        @endif
                    </div>

                    @if ($path !== '.' && $path !== '/')
                        @php
                            $parentPath = dirname($path);
                            if ($parentPath === '.') {
                                $parentPath = str_starts_with($path, '/') ? '/' : '.';
                            }
                        @endphp
                        <a href="{{ route('migration-endpoints.files', ['id' => $endpoint->id, 'path' => $parentPath]) }}"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-arrow-up me-1"></i>
                            Direktori Induk
                        </a>
                    @endif
                </div>

                <div class="table-responsive">
                    <table class="table hosting-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Tipe</th>
                                <th>Ukuran</th>
                                <th>Terakhir Diubah</th>
                                <th>Permission</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($entries as $entry)
                                <tr>
                                    <td class="file-name">
                                        @if ($entry['type'] === 'directory')
                                            @php
                                                $childPath = rtrim($path, '/');
                                                $childPath = ($childPath === '' || $childPath === '.')
                                                    ? $entry['name']
                                                    : $childPath . '/' . $entry['name'];
                                                if (str_starts_with($path, '/')) {
                                                    $childPath = '/' . ltrim($childPath, '/');
                                                }
                                            @endphp
                                            <a class="text-decoration-none"
                                               href="{{ route('migration-endpoints.files', ['id' => $endpoint->id, 'path' => $childPath]) }}">
                                                <i class="bi bi-folder-fill text-warning me-2"></i>{{ $entry['name'] }}
                                            </a>
                                        @elseif ($entry['type'] === 'symlink')
                                            <span>
                                                <i class="bi bi-link-45deg me-2"></i>{{ $entry['name'] }}
                                            </span>
                                        @else
                                            <span>
                                                <i class="bi bi-file-earmark me-2"></i>{{ $entry['name'] }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ ucfirst($entry['type']) }}</td>
                                    <td>
                                        @if ($entry['type'] === 'directory')
                                            —
                                        @else
                                            {{ number_format($entry['size']) }} byte
                                        @endif
                                    </td>
                                    <td>
                                        {{ $entry['modified_at'] ? date('d-m-Y H:i:s', $entry['modified_at']) : '—' }}
                                    </td>
                                    <td>
                                        <code>{{ $entry['permissions'] ?? '—' }}</code>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center hosting-muted py-4">
                                        <i class="bi bi-folder2-open fs-4 d-block mb-2"></i>
                                        Direktori ini kosong.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="alert alert-info mt-3 small">
            <i class="bi bi-shield-check me-1"></i>
            Mode hanya-baca. Halaman ini hanya menampilkan daftar direktori dan metadata file.
            Tidak tersedia operasi upload, download, hapus, atau perubahan file.
        </div>
    </main>
</div>
</body>
</html>
