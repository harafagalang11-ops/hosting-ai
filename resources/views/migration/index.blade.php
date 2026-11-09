<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrasi Hosting | Hosting AI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app-layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">

    <style>
        .page-heading {
            margin-bottom: 28px;
        }

        .page-heading h1 {
            font-size: 30px;
            font-weight: 750;
            margin-bottom: 7px;
        }

        .page-heading p,
        .migration-description {
            color: var(--muted, #9ca3af);
        }

        .migration-card {
            height: auto;
            padding: 25px;
            border: 1px solid var(--card-border, #374151);
            border-radius: 17px;
            background: var(--card-bg, #1f2937);
        }

        .migration-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: rgba(37, 99, 235, .15);
            color: #60a5fa;
            font-size: 22px;
            margin-bottom: 18px;
        }

        .migration-flow {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 12px 8px;
    color: var(--text, #e5e7eb);
}

.migration-node {
    text-align: center;
    min-width: 90px;
}

.migration-node i {
    display: block;
    font-size: 24px;
    color: #3b82f6;
    margin-bottom: 4px;
}

.migration-arrow {
    font-size: 18px;
    color: #3b82f6;
}

        .migration-notice {
            padding: 16px;
            border: 1px solid rgba(59, 130, 246, .3);
            border-radius: 12px;
            background: rgba(59, 130, 246, .08);
            color: var(--text, #e5e7eb);
        }

        @media(max-width: 700px) {
            .page-heading h1 {
                font-size: 24px;
            }

            .migration-card {
                padding: 18px;
            }
        }
    </style>
</head>

<body class="{{ auth()->user()->theme === 'dark' ? 'theme-dark' : '' }}">
<div class="d-flex">
    @include('partials.sidebar')

    <main class="content">
        <div class="page-heading">
            <div class="text-primary small fw-bold mb-2">
                <i class="bi bi-arrow-left-right me-1"></i>
                HOSTING MANAGEMENT
            </div>

            <h1>Migrasi Hosting</h1>
            <p>Kelola proses pemindahan website dari satu lingkungan hosting ke lingkungan lainnya.</p>
        </div>

        <div class="migration-card mb-4">
            <h5 class="fw-bold mb-1">Alur Migrasi Website</h5>
            <p class="migration-description small mb-0">
                Simulasi pemindahan website antar dua lingkungan localhost.
            </p>

            <div class="migration-flow">
                <div class="migration-node">
                    <i class="bi bi-hdd-network"></i>
                    <strong>Localhost 1</strong>
                    <div class="small migration-description">Server Sumber</div>
                </div>

                <div class="migration-arrow">
                    <i class="bi bi-arrow-right-circle-fill"></i>
                </div>

                <div class="migration-node">
                    <i class="bi bi-hdd-stack"></i>
                    <strong>Localhost 2</strong>
                    <div class="small migration-description">Server Tujuan</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="migration-card">
                    <div class="migration-icon">
                        <i class="bi bi-cloud-arrow-up"></i>
                    </div>
                    <h5 class="fw-bold">Server Sumber</h5>
                    <p class="migration-description small">
                        Lingkungan localhost yang berisi website dan database asal.
                    </p>
                    <span class="badge {{ $sourceStatus ? 'text-bg-success' : 'text-bg-danger' }}">
                        {{ $sourceStatus ? 'Terhubung · HTTP 200' : 'Tidak terhubung' }}
                    </span>
                    <p class="small migration-description mt-2 mb-0">127.0.0.1:8081</p>
                </div>
            </div>

            <div class="col-md-6">
                <div class="migration-card">
                    <div class="migration-icon">
                        <i class="bi bi-cloud-arrow-down"></i>
                    </div>
                    <h5 class="fw-bold">Server Tujuan</h5>
                    <p class="migration-description small">
                        Lingkungan localhost yang akan menerima salinan website dan database.
                    </p>
                    <span class="badge {{ $targetStatus ? 'text-bg-success' : 'text-bg-danger' }}">
                        {{ $targetStatus ? 'Terhubung · HTTP 200' : 'Tidak terhubung' }}
                    </span>
                    <p class="small migration-description mt-2 mb-0">127.0.0.1:8082</p>
                </div>
            </div>
        </div>


        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if (session('migration_result'))
            @php($result = session('migration_result'))
            <div class="migration-card mb-4">
                <h5 class="fw-bold text-success">
                    <i class="bi bi-check-circle-fill me-2"></i>Migrasi Berhasil
                </h5>
                <p class="mb-1">Jumlah file: {{ $result['files'] }}</p>
                <p class="mb-1">Integritas database: {{ $result['database'] }}</p>
                <p class="mb-1">Verifikasi website: {{ $result['verification'] ?? 'Belum tersedia' }}</p>
                <p class="mb-1">HTTP tujuan: {{ $result['http_status'] ?? '-' }}</p>
                <p class="mb-1">Backup: {{ $result['backup'] }}</p>
                <p class="mb-0">Tujuan: {{ $result['target'] }}</p>
            </div>
        @endif

        <div class="migration-card mb-4">
    <h5 class="fw-bold mb-1">Konfigurasi Migrasi</h5>

    <p class="migration-description small mb-4">
        Pilih endpoint sumber, website simulasi, dan endpoint tujuan.
    </p>

    @if ($hostingEndpoints->count() >= 2)
        <form method="POST" action="{{ route('migration.run') }}" class="mt-3 mb-3">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="source_endpoint" class="form-label fw-semibold">
                        Hosting Sumber
                    </label>
                    <select id="source_endpoint" name="source_endpoint_id"
                        class="form-select" required>
                        <option value="">Pilih hosting sumber</option>
                        @foreach ($hostingEndpoints as $endpoint)
                            <option value="{{ $endpoint->id }}"
                                @selected(old('source_endpoint_id', 1) == $endpoint->id)>
                                {{ $endpoint->name }} — {{ $endpoint->host }}
                            </option>
                        @endforeach
                    </select>
                    @error('source_endpoint_id')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="target_endpoint" class="form-label fw-semibold">
                        Hosting Tujuan
                    </label>
                    <select id="target_endpoint" name="target_endpoint_id"
                        class="form-select" required>
                        <option value="">Pilih hosting tujuan</option>
                        @foreach ($hostingEndpoints as $endpoint)
                            <option value="{{ $endpoint->id }}"
                                @selected(old('target_endpoint_id', 2) == $endpoint->id)>
                                {{ $endpoint->name }} — {{ $endpoint->host }}
                            </option>
                        @endforeach
                    </select>
                    @error('target_endpoint_id')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="col-12">
                    <label for="website_migration" class="form-label fw-semibold">
                        Website yang Dimigrasikan
                    </label>
                    <select id="website_migration" name="website_id"
                        class="form-select" required>
                        <option value="">Pilih website</option>
                        @foreach ($websites as $website)
                            <option value="{{ $website->id }}"
                                data-endpoint-id="{{ $website->migration_endpoint_id }}"
                                @selected(old('website_id') == $website->id)>
                                {{ $website->name }} — {{ $website->url }} — {{ $website->document_root ?: 'Document Root belum diisi' }}
                            </option>
                        @endforeach
                    </select>
                    @error('website_id')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-3"
                onclick="return confirm('Jalankan simulasi pemindahan data localhost?')">
                <i class="bi bi-arrow-left-right me-2"></i>
                Jalankan Simulasi Migrasi Localhost
            </button>
        </form>

        <div class="alert alert-info mt-3 mb-0">
            Pilihan endpoint digunakan sebagai metadata simulasi saja.
            Tidak ada koneksi SSH/SFTP atau cPanel API. Proses migrasi
            tetap berjalan pada laboratorium localhost (8081 ke 8082).
        </div>
    @elseif ($hostingEndpoints->count() === 1)
        <div class="alert alert-warning mb-0">
            Baru ada satu hosting terdaftar. Tambahkan hosting tujuan melalui
            <a href="{{ route('migration-endpoints.index') }}">Kelola Hosting</a>.
        </div>
    @else
        <div class="alert alert-warning mb-0">
            Belum ada hosting terdaftar.
            <a href="{{ route('migration-endpoints.index') }}">Tambahkan hosting</a>
            terlebih dahulu.
        </div>
    @endif
</div>

        <div class="migration-card mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="fw-bold mb-1">Riwayat Migrasi</h5>
                    <p class="migration-description small mb-0">
                        10 aktivitas migrasi website terakhir.
                    </p>
                </div>
                <span class="badge text-bg-primary">
                    {{ $migrationHistories->count() }} Riwayat
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Website</th>
                            <th>Sumber</th>
                            <th>Tujuan</th>
                            <th>Status</th>
                            <th>Jumlah File</th>
                            <th>HTTP</th>
                            <th>Backup</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($migrationHistories as $history)
                            <tr>
                                <td class="small">
                                    {{ $history->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td>{{ $history->website_name ?? '-' }}</td>
                                <td>{{ $history->source }}</td>
                                <td>{{ $history->target }}</td>
                                <td>
                                    @if ($history->status === 'success')
                                        <span class="badge text-bg-success">Berhasil</span>
                                    @elseif ($history->status === 'failed')
                                        <span class="badge text-bg-danger">Gagal</span>
                                    @else
                                        <span class="badge text-bg-warning">Berjalan</span>
                                    @endif
                                </td>
                                <td>{{ $history->file_count ?? '-' }}</td>
                                <td>{{ $history->http_status ?? '-' }}</td>
                                <td class="small">{{ $history->backup_file ?? '-' }}</td>
                            </tr>
                            @if ($history->error_message)
                                <tr>
                                    <td colspan="8" class="small text-danger">
                                        Keterangan: {{ $history->error_message }}
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="8" class="text-center migration-description py-4">
                                    Belum ada riwayat migrasi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="migration-notice">
            <div class="d-flex gap-3">
                <i class="bi bi-info-circle-fill text-primary fs-5"></i>
                <div>
                    <strong>Mode Simulasi Lokal</strong>
                    <p class="small mb-0 mt-1">
                        Migrasi saat ini berjalan dalam mode simulasi menggunakan
                        dua server localhost. File dan database disalin ke server tujuan,
                        diverifikasi, dan dilindungi dengan backup sebelum pemindahan.
                        Belum terhubung ke hosting produksi.
                    </p>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
(function () {
    const source = document.getElementById('source_endpoint');
    const target = document.getElementById('target_endpoint');
    const website = document.getElementById('website_migration');

    if (!source || !target || !website) return;

    const websiteOptions = Array.from(website.options)
        .filter(option => option.dataset.endpointId);

    const placeholder = website.options[0];

    function updateOptions(resetWebsite = false) {
        const sourceValue = source.value;
        const targetValue = target.value;

        Array.from(target.options).forEach(option => {
            const same = option.value !== '' &&
                option.value === sourceValue;

            option.disabled = same;
            option.hidden = same;
        });

        Array.from(source.options).forEach(option => {
            const same = option.value !== '' &&
                option.value === targetValue;

            option.disabled = same;
            option.hidden = same;
        });

        if (sourceValue && sourceValue === targetValue) {
            target.value = '';
        }

        let availableCount = 0;

        websiteOptions.forEach(option => {
            const matches = sourceValue !== '' &&
                option.dataset.endpointId === sourceValue;

            option.hidden = !matches;
            option.disabled = !matches;

            if (matches) availableCount++;
        });

        const selectedOption = website.selectedOptions[0];

        if (
            resetWebsite ||
            (selectedOption &&
                selectedOption.dataset.endpointId !== sourceValue)
        ) {
            website.value = '';
        }

        if (placeholder) {
            placeholder.hidden = false;
            placeholder.disabled = false;
            placeholder.textContent =
                sourceValue && availableCount === 0
                    ? 'Tidak ada website pada hosting sumber ini'
                    : 'Pilih website';
        }

        website.disabled = !sourceValue || availableCount === 0;
    }

    source.addEventListener('change', () => updateOptions(true));
    target.addEventListener('change', updateOptions);

    updateOptions();
})();
</script>

</body>
</html>
