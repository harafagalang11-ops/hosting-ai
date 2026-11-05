<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Riwayat Monitoring - Hosting AI</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset("favicon.png") }}">
    <link rel="stylesheet" href="{{ asset("css/app-layout.css") }}">
    <link rel="stylesheet" href="{{ asset("css/theme.css") }}">
</head>
<body class="{{ auth()->user()->theme === 'dark' ? 'theme-dark' : '' }}">
<div class="d-flex">
    @include('partials.sidebar')

    <main class="content">
        <h2>Riwayat Monitoring</h2>
        <p class="text-secondary">Catatan hasil pemeriksaan website yang tersimpan di database.</p>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card border-0 shadow-sm p-4 mt-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Daftar Riwayat</h5>
                @if($logs->total() > 0)
                    <form method="POST" action="/riwayat/hapus-semua"
                        onsubmit="return confirm('Yakin ingin menghapus SELURUH riwayat monitoring? Tindakan ini tidak dapat dibatalkan.')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger btn-sm">Hapus Semua</button>
                    </form>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Website</th>
                            <th>Status</th>
                            <th>HTTP</th>
                            <th>Response Time</th>
                            <th>Waktu Pemeriksaan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="fw-bold">{{ $log->website_name }}</td>
                                <td>
                                    @if ($log->status === 'NORMAL')
                                        <span class="badge bg-success">Normal</span>
                                    @elseif ($log->status === 'WARNING')
                                        <span class="badge bg-warning text-dark">Peringatan</span>
                                    @else
                                        <span class="badge bg-danger">Gangguan</span>
                                    @endif
                                </td>
                                <td>{{ $log->http_code ?? '-' }}</td>
                                <td>{{ $log->response_time ?? '-' }} detik</td>
                                <td>{{ $log->checked_at->timezone('Asia/Jakarta')->format('d-m-Y H:i:s') }} WIB</td>
                                <td>
                                    <form method="POST" action="/riwayat/{{ $log->id }}"
                                        onsubmit="return confirm('Hapus riwayat {{ $log->website_name }} ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger btn-sm">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-secondary">
                                    Belum ada riwayat pemeriksaan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $logs->links('pagination::bootstrap-5') }}
        </div>
    </main>
</div>
</body>
</html>
