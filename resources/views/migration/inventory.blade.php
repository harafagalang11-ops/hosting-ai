<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventaris File | Hosting AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/app-layout.css') }}" rel="stylesheet">
    <link href="{{ asset('css/theme.css') }}" rel="stylesheet">
</head>
<body class="{{ auth()->user()->theme === 'dark' ? 'theme-dark' : '' }}">
<div class="d-flex">
    @include('partials.sidebar')
    <main class="content">
        <div class="mb-4">
            <div class="text-primary small fw-bold mb-2">HOSTING MANAGEMENT</div>
            <h1>Inventaris File Hosting</h1>
            <p class="text-secondary">Ringkasan struktur direktori dan ukuran file.</p>
        </div>

        <div class="alert alert-info">
            <strong>Mode Simulasi SFTP.</strong>
            Inventaris ini berasal dari data contoh lokal. Tidak ada koneksi ke server hosting.
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5>{{ $endpoint->name }}</h5>
                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <div class="text-secondary small">Jumlah folder</div>
                        <h3>{{ number_format($directories) }}</h3>
                    </div>
                    <div class="col-md-4">
                        <div class="text-secondary small">Jumlah file</div>
                        <h3>{{ number_format($files) }}</h3>
                    </div>
                    <div class="col-md-4">
                        <div class="text-secondary small">Total ukuran file</div>
                        <h3>{{ number_format($totalSize) }} byte</h3>
                    </div>
                </div>
            </div>
        </div>

        @if ($truncated)
            <div class="alert alert-warning">
                Inventaris dibatasi maksimal {{ $maxEntries }} entri dan kedalaman {{ $maxDepth }} tingkat.
                Sebagian data mungkin tidak ditampilkan.
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <h5 class="mb-3">Daftar File dan Direktori</h5>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Lokasi</th>
                                <th>Tipe</th>
                                <th>Kedalaman</th>
                                <th>Ukuran</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr>
                                    <td class="text-break">{{ $item['path'] }}</td>
                                    <td>{{ ucfirst($item['type']) }}</td>
                                    <td>{{ $item['depth'] }}</td>
                                    <td>{{ $item['type'] === 'file' ? number_format($item['size']) . ' byte' : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center">Tidak ada data.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <a href="{{ route('migration-endpoints.files', $endpoint->id) }}"
                   class="btn btn-outline-secondary btn-sm">Kembali ke Penjelajah File</a>
            </div>
        </div>
    </main>
</div>
</body>
</html>
